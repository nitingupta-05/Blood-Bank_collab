<?php
class BloodBank extends Model {
    protected string $table = 'blood_banks';

    public function getByEmail(string $email): ?array {
        return $this->findBy('email', $email);
    }

    public function getByCity(string $city): array {
        return $this->where(['city' => $city], 'name ASC');
    }

    public function listAll(): array {
        return $this->all('city ASC, name ASC');
    }

    public function findByBankAdmin(int $userId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT u.email, u.city FROM `users` u WHERE u.id = ?"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) return null;
        return $this->getByEmail((string) $user['email'])
            ?: ($this->where(['city' => $user['city']], 'id ASC', 1)[0] ?? null);
    }
}

class EmergencyRequest extends Model {
    protected string $table = 'emergency_requests';

    public function expireOverdue(): int {
        return $this->pdo->exec(
            "UPDATE `emergency_requests` SET status='expired'
             WHERE status='active' AND expires_at IS NOT NULL AND expires_at <= NOW()"
        );
    }

    public function activeFeed(int $limit = 50): array {
        $stmt = $this->pdo->prepare(
            "SELECT er.*, u.first_name, u.last_name, u.city, COALESCE(er.contact_phone, u.phone) AS phone
             FROM `emergency_requests` er
             JOIN `users` u ON u.id = er.hospital_id
             WHERE er.status = 'active'
             ORDER BY er.expires_at ASC, er.created_at DESC
             LIMIT " . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getStats(): array {
        $stats = [
            'active'           => 0,
            'fulfilled_today'  => 0,
            'expired'          => 0,
            'avg_response_hours' => 0,
        ];
        $stmt = $this->pdo->query("SELECT COUNT(*) AS c FROM `emergency_requests` WHERE status='active'");
        $stats['active'] = (int) $stmt->fetch()['c'];

        $stmt = $this->pdo->query("SELECT COUNT(*) AS c FROM `emergency_requests` WHERE status='fulfilled' AND fulfilled_at >= CURDATE()");
        $stats['fulfilled_today'] = (int) $stmt->fetch()['c'];

        $stmt = $this->pdo->query("SELECT COUNT(*) AS c FROM `emergency_requests` WHERE status='expired'");
        $stats['expired'] = (int) $stmt->fetch()['c'];

        $stmt = $this->pdo->query(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, fulfilled_at)) AS m
             FROM `emergency_requests`
             WHERE status='fulfilled' AND fulfilled_at IS NOT NULL"
        );
        $m = (float) ($stmt->fetch()['m'] ?? 0);
        $stats['avg_response_hours'] = $m > 0 ? round($m / 60, 1) : 0;
        return $stats;
    }
}

class Booking extends Model {
    protected string $table = 'blood_bookings';

    public function getStats(): array {
        $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0];
        $rows = $this->pdo->query("SELECT status, COUNT(*) AS c FROM `blood_bookings` GROUP BY status")->fetchAll();
        foreach ($rows as $r) {
            $stats['total'] += (int) $r['c'];
            if (array_key_exists($r['status'], $stats)) $stats[$r['status']] = (int) $r['c'];
        }
        return $stats;
    }

    public function listRecent(int $limit = 20, int $offset = 0): array {
        $stmt = $this->pdo->prepare(
            "SELECT b.*, u.first_name, u.last_name, u.phone, u.city
             FROM `blood_bookings` b
             JOIN `users` u ON u.id = b.hospital_id
             ORDER BY b.created_at DESC
             LIMIT " . (int) $limit . " OFFSET " . (int) $offset
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

class StorageArea extends Model {
    protected string $table = 'storage_areas';

    public function listForBank(?int $bankId = null): array {
        $sql = "SELECT sa.*, bb.name AS blood_bank_name FROM `storage_areas` sa
                LEFT JOIN `blood_banks` bb ON bb.id = sa.blood_bank_id";
        if ($bankId) $sql .= " WHERE sa.blood_bank_id = " . (int) $bankId;
        $sql .= " ORDER BY sa.id ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function getStats(): array {
        $fridges = (int) $this->pdo->query("SELECT COUNT(*) AS c FROM `storage_areas`")->fetch()['c'];
        $capacity = (int) ($this->pdo->query("SELECT COALESCE(SUM(capacity),0) AS c FROM `storage_areas`")->fetch()['c']);
        $stored   = (int) ($this->pdo->query("SELECT COALESCE(SUM(current_occupancy),0) AS c FROM `storage_areas`")->fetch()['c']);
        $avg = (float) ($this->pdo->query("SELECT AVG(current_temperature) AS a FROM `storage_areas` WHERE current_temperature IS NOT NULL")->fetch()['a'] ?? 0);
        return [
            'fridges'         => $fridges,
            'capacity'        => $capacity,
            'stored'          => $stored,
            'avg_temperature' => $avg ? round($avg, 1) : null,
        ];
    }

    public function logTemperature(int $storageId, float $temp): void {
        $this->pdo->prepare("INSERT INTO `temperature_logs` (storage_area_id, temperature) VALUES (?, ?)")
                  ->execute([$storageId, $temp]);
        $this->pdo->prepare(
            "UPDATE `storage_areas` SET current_temperature = ?, last_temperature_check = NOW() WHERE id = ?"
        )->execute([$temp, $storageId]);
    }

    public function getTemperatureHistory(int $storageId, int $hours = 24): array {
        $stmt = $this->pdo->prepare(
            "SELECT temperature, recorded_at AS `timestamp`
             FROM `temperature_logs`
             WHERE storage_area_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY recorded_at ASC"
        );
        $stmt->execute([$storageId, $hours]);
        return $stmt->fetchAll();
    }
}
