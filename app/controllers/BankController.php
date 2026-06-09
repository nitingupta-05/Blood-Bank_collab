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
            return ['ok' => false, 'error' => 'Name, valid email, phone, address, city, and password (min 8 chars with uppercase, lowercase, and a number) are required'];
        }

        $userModel = new User($this->pdo);
        if ($userModel->findByEmail($email)) {
            return ['ok' => false, 'error' => 'Email already registered'];
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

            $tenantDbName = TenantHelper::createDatabase($this->pdo, $bankId);
            $this->pdo->prepare("UPDATE `blood_banks` SET tenant_db_name = ? WHERE id = ?")
                ->execute([$tenantDbName, $bankId]);

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
            return ['ok' => true, 'blood_bank_id' => $bankId, 'tenant_db_name' => $tenantDbName, 'user_id' => (int) $userId];
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function listPublic(): array {
        return $this->pdo->query("SELECT id, name, city, address, phone FROM `blood_banks` ORDER BY city, name")->fetchAll();
    }

    public function bloodSearch(string $bloodGroup, string $city, int $units, ?float $lat = null, ?float $lon = null): array {
        TenantHelper::ensureMasterSchema($this->pdo);
        $stmt = $this->pdo->prepare(
            "SELECT bb.id, bb.name, bb.address, bb.city, bb.phone, bb.latitude, bb.longitude, bb.tenant_db_name,
                    COUNT(bu.id) AS available_units
             FROM `blood_banks` bb
             LEFT JOIN `blood_units` bu
                    ON bu.blood_bank_id = bb.id
                   AND bu.blood_group   = ?
                   AND bu.status        = 'available'
                   AND bu.expiry_date   > CURDATE()
             WHERE bb.city LIKE ?
               AND (bb.tenant_db_name IS NULL OR bb.tenant_db_name = '')
             GROUP BY bb.id
             HAVING available_units >= ?
             ORDER BY available_units DESC, bb.name ASC
             LIMIT 50"
        );
        $stmt->execute([$bloodGroup, '%' . $city . '%', $units]);
        $banks = $stmt->fetchAll();

        $tenantStmt = $this->pdo->prepare(
            "SELECT id, name, address, city, phone, latitude, longitude, tenant_db_name
             FROM `blood_banks`
             WHERE city LIKE ? AND tenant_db_name IS NOT NULL AND tenant_db_name <> ''
             ORDER BY name ASC
             LIMIT 100"
        );
        $tenantStmt->execute(['%' . $city . '%']);
        foreach ($tenantStmt->fetchAll() as $bank) {
            try {
                $tenantPdo = TenantHelper::connect((string) $bank['tenant_db_name']);
                $countStmt = $tenantPdo->prepare(
                    "SELECT COUNT(*) FROM `blood_units`
                     WHERE blood_group = ? AND status = 'available' AND expiry_date > CURDATE()"
                );
                $countStmt->execute([$bloodGroup]);
                $available = (int) $countStmt->fetchColumn();
                if ($available >= $units) {
                    $bank['available_units'] = $available;
                    $banks[] = $bank;
                }
            } catch (Throwable $e) {
                error_log('[tenant-search] bank ' . ($bank['id'] ?? '?') . ': ' . $e->getMessage());
            }
        }

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
            foreach ($banks as &$b) {
                $b['available_units'] = (int) $b['available_units'];
                $b['distance_km'] = null;
            }
            unset($b);
            usort($banks, fn($a, $b) => ((int) $b['available_units'] <=> (int) $a['available_units']) ?: strcmp((string) $a['name'], (string) $b['name']));
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
