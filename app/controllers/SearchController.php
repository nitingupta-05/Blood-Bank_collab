<?php
class SearchController {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function globalSearch(string $q, string $type = 'all'): array {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $like = '%' . $q . '%';
        $items = [];
        $tpdo = tenant_pdo();
        $master = MASTER_DB;

        if ($type === 'all' || $type === 'donors') {
            $dpdo = $tpdo ?: $this->pdo;
            $stmt = $dpdo->prepare(
                "SELECT 'Donor' AS type, CONCAT(u.first_name, ' ', u.last_name) AS title,
                        CONCAT(d.blood_group, ' donor in ', COALESCE(u.city, 'Unknown')) AS subtitle,
                        '?route=donors' AS url
                 FROM `donors` d JOIN `{$master}`.`users` u ON u.id = d.user_id
                 WHERE u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR d.blood_group LIKE ?
                 LIMIT 8"
            );
            $stmt->execute([$like, $like, $like, $like, $like]);
            $items = array_merge($items, $stmt->fetchAll());
        }

        if ($type === 'all' || $type === 'blood_units') {
            $bpdo = $tpdo ?: $this->pdo;
            $stmt = $bpdo->prepare(
                "SELECT 'Blood Unit' AS type, CONCAT(blood_group, ' unit ', barcode) AS title,
                        CONCAT(status, ' - ', storage_location) AS subtitle,
                        '?route=inventory' AS url
                 FROM `blood_units`
                 WHERE blood_group LIKE ? OR barcode LIKE ? OR status LIKE ? OR storage_location LIKE ?
                 LIMIT 8"
            );
            $stmt->execute([$like, $like, $like, $like]);
            $items = array_merge($items, $stmt->fetchAll());
        }

        if ($type === 'all' || $type === 'blood_banks') {
            $stmt = $this->pdo->prepare(
                "SELECT 'Blood Bank' AS type, name AS title,
                        CONCAT(city, ' - ', phone) AS subtitle,
                        '?route=storage' AS url
                 FROM `blood_banks`
                 WHERE name LIKE ? OR city LIKE ? OR phone LIKE ? OR email LIKE ?
                 LIMIT 8"
            );
            $stmt->execute([$like, $like, $like, $like]);
            $items = array_merge($items, $stmt->fetchAll());
        }

        if ($type === 'all' || $type === 'emergency') {
            $stmt = $this->pdo->prepare(
                "SELECT 'Emergency' AS type, CONCAT(blood_group, ' for ', COALESCE(patient_name, 'patient')) AS title,
                        CONCAT(urgency_level, ' - ', status, ' - ', location) AS subtitle,
                        '?route=emergency' AS url
                 FROM `emergency_requests`
                 WHERE blood_group LIKE ? OR patient_name LIKE ? OR contact_phone LIKE ? OR location LIKE ? OR status LIKE ?
                 LIMIT 8"
            );
            $stmt->execute([$like, $like, $like, $like, $like]);
            $items = array_merge($items, $stmt->fetchAll());
        }

        return array_slice($items, 0, 20);
    }
}
