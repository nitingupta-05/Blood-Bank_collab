<?php
class ReportController {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function inventory(array $filters = []): array {
        $where = [];
        $params = [];
        if (!empty($filters['blood_group']) && in_array($filters['blood_group'], BLOOD_GROUPS, true)) {
            $where[] = 'bu.blood_group = ?'; $params[] = $filters['blood_group'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], BLOOD_UNIT_STATUS, true)) {
            $where[] = 'bu.status = ?'; $params[] = $filters['status'];
        }
        $sql = "SELECT bu.barcode, bu.blood_group, bu.status, bu.collection_date, bu.expiry_date,
                       bu.storage_location, bb.name AS blood_bank_name,
                       CONCAT_WS(' ', u.first_name, u.last_name) AS donor_name
                FROM `blood_units` bu
                LEFT JOIN `blood_banks` bb ON bb.id = bu.blood_bank_id
                LEFT JOIN `users` u       ON u.id  = bu.donor_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY bu.blood_group, bu.expiry_date ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function donors(): array {
        return $this->pdo->query(
            "SELECT u.first_name, u.last_name, u.phone, u.city, d.blood_group, d.age, d.weight,
                    d.eligibility_status, d.last_donation_date, d.total_donations
             FROM `donors` d JOIN `users` u ON u.id = d.user_id
             ORDER BY d.created_at DESC"
        )->fetchAll();
    }

    public function emergencies(int $days = 30): array {
        $stmt = $this->pdo->prepare(
            "SELECT er.id, er.blood_group, er.quantity, er.urgency_level, er.status,
                    er.location, er.created_at, er.contact_phone,
                    u.first_name, u.last_name, u.phone
             FROM `emergency_requests` er
             JOIN `users` u ON u.id = er.hospital_id
             WHERE er.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY er.created_at DESC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function bookings(int $days = 30): array {
        $stmt = $this->pdo->prepare(
            "SELECT b.id, b.blood_group, b.quantity, b.status, b.required_date, b.patient_name,
                    b.created_at, b.approval_date,
                    u.first_name, u.last_name, u.phone, u.city
             FROM `blood_bookings` b JOIN `users` u ON u.id = b.hospital_id
             WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY b.created_at DESC"
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    public function exportCsv(array $rows, string $title, string $filename, string $orgName = ''): void {
        CsvExporter::stream($rows, $title, $filename, $orgName);
    }
}
