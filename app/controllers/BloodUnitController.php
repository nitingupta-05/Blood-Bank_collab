<?php
class BloodUnitController {
    private PDO $pdo;
    private BloodUnit $model;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->model = new BloodUnit();
    }

    public function add(array $data, int $actorId, int $bankId): array {
        $bg   = ValidationHelper::bloodGroup($data['blood_group'] ?? null);
        $coll = ValidationHelper::dateYmd($data['collection_date'] ?? null);
        $exp  = ValidationHelper::dateYmd($data['expiry_date'] ?? null);
        $loc  = ValidationHelper::nonEmptyString($data['storage_location'] ?? null, 100);

        if (!$bg)   return ['ok' => false, 'error' => 'Invalid blood group'];
        if (!$coll || !$exp) return ['ok' => false, 'error' => 'Invalid date(s)'];
        if (!$loc)  return ['ok' => false, 'error' => 'Storage location is required'];
        if (strtotime($exp) <= strtotime($coll)) {
            return ['ok' => false, 'error' => 'Expiry date must be after collection date'];
        }
        if ($bankId <= 0) return ['ok' => false, 'error' => 'No blood bank context for this user'];

        $donorId = !empty($data['donor_id']) ? (int) $data['donor_id'] : null;
        $barcode = $this->model->generateBarcode();

        try {
            $id = $this->model->create([
                'barcode'          => $barcode,
                'blood_group'      => $bg,
                'collection_date'  => $coll,
                'expiry_date'      => $exp,
                'donor_id'         => $donorId,
                'storage_location' => $loc,
                'status'           => 'available',
                'created_by'       => $actorId,
            ]);
            audit($this->pdo, 'blood_unit.add', $actorId, 'blood_units:' . $id, ['barcode' => $barcode, 'bg' => $bg]);
            return ['ok' => true, 'id' => $id, 'barcode' => $barcode];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Failed to add blood unit'];
        }
    }

    public function list(array $filters, ?int $bankId, int $page = 1, int $perPage = 20): array {
        $pdo = $this->model->pdo();
        $master = MASTER_DB;
        $sql = "SELECT bu.*, bb.name AS blood_bank_name, u.first_name AS donor_first, u.last_name AS donor_last
                FROM `blood_units` bu
                LEFT JOIN `{$master}`.`blood_banks` bb ON bb.id = ?
                LEFT JOIN `{$master}`.`users` u       ON u.id  = bu.donor_id
                WHERE 1=1";
        $params = [$bankId ?: 0];
        if (!empty($filters['blood_group']) && in_array($filters['blood_group'], BLOOD_GROUPS, true)) {
            $sql .= " AND bu.blood_group = ?"; $params[] = $filters['blood_group'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], BLOOD_UNIT_STATUS, true)) {
            $sql .= " AND bu.status = ?"; $params[] = $filters['status'];
        }
        if (!empty($filters['storage_location'])) {
            $sql .= " AND bu.storage_location = ?"; $params[] = $filters['storage_location'];
        }
        $offset = max(0, ($page - 1) * $perPage);
        $sql .= " ORDER BY bu.expiry_date ASC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status, int $actorId): array {
        if (!in_array($status, BLOOD_UNIT_STATUS, true)) {
            return ['ok' => false, 'error' => 'Invalid status'];
        }
        $ok = $this->model->updateStatus($id, $status);
        if ($ok) audit($this->pdo, 'blood_unit.status', $actorId, 'blood_units:' . $id, ['status' => $status]);
        return ['ok' => $ok];
    }

    public function delete(int $id, int $actorId): bool {
        $ok = $this->model->delete($id);
        if ($ok) audit($this->pdo, 'blood_unit.delete', $actorId, 'blood_units:' . $id);
        return $ok;
    }
}
