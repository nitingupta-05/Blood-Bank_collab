<?php
class BloodUnit extends Model {

    protected string $table = 'blood_units';

    public function generateBarcode(): string {
        return 'BB' . date('Ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function listForBank(int $bankId, array $filters = [], int $page = 1, int $perPage = 20): array {
        $where = ['bu.blood_bank_id = ?'];
        $params = [$bankId];

        if (!empty($filters['blood_group']) && in_array($filters['blood_group'], BLOOD_GROUPS, true)) {
            $where[] = 'bu.blood_group = ?';
            $params[] = $filters['blood_group'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], BLOOD_UNIT_STATUS, true)) {
            $where[] = 'bu.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['storage_location'])) {
            $where[] = 'bu.storage_location = ?';
            $params[] = $filters['storage_location'];
        }

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT bu.*, bb.name AS blood_bank_name,
                       u.first_name AS donor_first, u.last_name AS donor_last
                FROM `blood_units` bu
                LEFT JOIN `blood_banks` bb ON bb.id = bu.blood_bank_id
                LEFT JOIN `users` u       ON u.id  = bu.donor_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY bu.expiry_date ASC
                LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getAvailableByBankAndGroup(int $bankId, string $bloodGroup): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS c FROM `blood_units`
             WHERE blood_bank_id = ? AND blood_group = ? AND status = 'available' AND expiry_date > CURDATE()"
        );
        $stmt->execute([$bankId, $bloodGroup]);
        return (int) $stmt->fetch()['c'];
    }

    public function getInventorySummary(): array {
        $rows = $this->pdo->query(
            "SELECT blood_group,
                    SUM(status = 'available' AND expiry_date > CURDATE()) AS available,
                    SUM(status = 'reserved')  AS reserved,
                    SUM(status = 'used')      AS used,
                    SUM(status = 'expired' OR (status = 'available' AND expiry_date <= CURDATE())) AS expired
             FROM `blood_units`
             GROUP BY blood_group
             ORDER BY blood_group"
        )->fetchAll();

        $out = array_fill_keys(BLOOD_GROUPS, ['available'=>0,'reserved'=>0,'used'=>0,'expired'=>0]);
        foreach ($rows as $r) {
            $out[$r['blood_group']] = [
                'available' => (int) $r['available'],
                'reserved'  => (int) $r['reserved'],
                'used'      => (int) $r['used'],
                'expired'   => (int) $r['expired'],
            ];
        }
        return $out;
    }

    public function markExpired(int $id): bool {
        return $this->update($id, ['status' => 'expired']);
    }

    public function updateStatus(int $id, string $status): bool {
        if (!in_array($status, BLOOD_UNIT_STATUS, true)) {
            throw new InvalidArgumentException('Invalid status');
        }
        return $this->update($id, ['status' => $status]);
    }
}
