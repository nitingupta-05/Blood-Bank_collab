<?php
class BankController {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function register(array $data): array {
        $name     = ValidationHelper::nonEmptyString($data['name'] ?? null, 255);
        $email    = ValidationHelper::email($data['email'] ?? null);
        $phone    = ValidationHelper::phone($data['phone'] ?? null);
        $address  = ValidationHelper::nonEmptyString($data['address'] ?? null, 500);
        $city     = ValidationHelper::nonEmptyString($data['city'] ?? null, 100);
        $password = ValidationHelper::password($data['password'] ?? null);
        $capacity = isset($data['capacity']) ? max(0, (int) $data['capacity']) : null;

        if (!$name || !$email || !$phone || !$address || !$city || !$password) {
            return ['ok' => false, 'error' => 'Name, valid email, phone, address, city, and password (min 8 chars) are required'];
        }

        $this->pdo->beginTransaction();
        try {
            $bankId = (int) (new BloodBank($this->pdo))->create([
                'name'           => $name,
                'address'        => $address,
                'city'           => $city,
                'contact_person' => ValidationHelper::nonEmptyString($data['contact_person'] ?? null, 100),
                'phone'          => $phone,
                'email'          => $email,
                'capacity'       => $capacity,
            ]);

            $userModel = new User($this->pdo);
            if ($userModel->findByEmail($email)) {
                throw new RuntimeException('Email already registered');
            }

            $contactPerson = trim((string) ($data['contact_person'] ?? '')) ?: $name;
            $parts = preg_split('/\s+/', $contactPerson);
            $first = $parts[0] ?? $name;
            $last  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Blood Bank';

            $userId = $userModel->create([
                'role'       => 'blood_bank_admin',
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $email,
                'password'   => $password,
                'phone'      => $phone,
                'address'    => $address,
                'city'       => $city,
                'is_active'  => 1,
            ]);

            $this->pdo->commit();
            audit($this->pdo, 'bank.register', (int) $userId, 'blood_banks:' . $bankId);
            return ['ok' => true, 'blood_bank_id' => $bankId, 'user_id' => (int) $userId];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function listPublic(): array {
        return $this->pdo->query("SELECT id, name, city, address, phone FROM `blood_banks` ORDER BY city, name")->fetchAll();
    }

    public function bloodSearch(string $bloodGroup, string $city, int $units, ?float $lat = null, ?float $lon = null): array {
        $stmt = $this->pdo->prepare(
            "SELECT bb.id, bb.name, bb.address, bb.city, bb.phone, bb.latitude, bb.longitude,
                    COUNT(bu.id) AS available_units
             FROM `blood_banks` bb
             LEFT JOIN `blood_units` bu
                    ON bu.blood_bank_id = bb.id
                   AND bu.blood_group   = ?
                   AND bu.status        = 'available'
                   AND bu.expiry_date   > CURDATE()
             WHERE bb.city LIKE ?
             GROUP BY bb.id
             HAVING available_units >= ?
             ORDER BY available_units DESC, bb.name ASC
             LIMIT 50"
        );
        $stmt->execute([$bloodGroup, '%' . $city . '%', $units]);
        $banks = $stmt->fetchAll();

        if ($lat !== null && $lon !== null) {
            foreach ($banks as &$b) {
                $b['available_units'] = (int) $b['available_units'];
                $b['distance_km'] = ($b['latitude'] !== null && $b['longitude'] !== null)
                    ? LocationHelper::calculateDistance($lat, $lon, (float) $b['latitude'], (float) $b['longitude'])
                    : null;
            }
            unset($b);
            usort($banks, fn($a, $b) => ($a['distance_km'] ?? PHP_FLOAT_MAX) <=> ($b['distance_km'] ?? PHP_FLOAT_MAX));
        } else {
            foreach ($banks as &$b) { $b['distance_km'] = null; }
            unset($b);
        }

        $donors = (new Donor($this->pdo))->findEligibleByGroupAndCity($bloodGroup, $city);
        if ($lat !== null && $lon !== null) {
            foreach ($donors as &$d) {
                $d['distance_km'] = ($d['latitude'] !== null && $d['longitude'] !== null)
                    ? LocationHelper::calculateDistance($lat, $lon, (float) $d['latitude'], (float) $d['longitude'])
                    : null;
            }
            unset($d);
        }

        return ['banks' => $banks, 'donors' => $donors];
    }
}
