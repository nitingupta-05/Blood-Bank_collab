<?php
class DashboardController {
    private PDO $pdo;
    private ?PDO $tpdo;
    public function __construct(PDO $pdo) { 
        $this->pdo = $pdo; 
        $this->tpdo = tenant_pdo() ?: $pdo;
    }

    public function stats(): array {
        $stats = [];
        $master = MASTER_DB;
        $rows = [
            'total_units'              => "SELECT COUNT(*) FROM `blood_units` WHERE status = 'available'",
            'available_donors'         => "SELECT COUNT(*) FROM `donors` WHERE eligibility_status = 'eligible'",
            'active_emergency_requests'=> "SELECT COUNT(*) FROM `{$master}`.`emergency_requests` WHERE status = 'active'",
            'fulfilled_today'          => "SELECT COUNT(*) FROM `{$master}`.`emergency_requests` WHERE status = 'fulfilled' AND fulfilled_at >= CURDATE()",
            'expiring_soon_units'      => "SELECT COUNT(*) FROM `blood_units` WHERE status = 'available' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
        ];
        foreach ($rows as $k => $sql) {
            $stats[$k] = (int) $this->tpdo->query($sql)->fetchColumn();
        }
        return $stats;
    }

    public function bloodStock(int $currentUserId, NotificationDispatcher $notif): array {
        $rows = $this->tpdo->query(
            "SELECT blood_group, COUNT(*) AS c
             FROM `blood_units` WHERE status = 'available' AND expiry_date > CURDATE()
             GROUP BY blood_group ORDER BY blood_group"
        )->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['blood_group']] = (int) $r['c'];

        $available = [];
        foreach (BLOOD_GROUPS as $g) {
            $count = $map[$g] ?? 0;
            $available[] = $count;
            if ($currentUserId > 0 && $count > 0 && $count < LOW_STOCK_THRESHOLD) {
                $notif->send($currentUserId, 'low_stock', "Low stock: {$g} has only {$count} units available", 'in_app');
            }
        }

        $expiring = $this->tpdo->query(
            "SELECT blood_group, COUNT(*) AS c
             FROM `blood_units`
             WHERE status = 'available' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             GROUP BY blood_group"
        )->fetchAll();
        if ($currentUserId > 0) {
            foreach ($expiring as $r) {
                $notif->send($currentUserId, 'expiry', "Expiring soon: {$r['blood_group']} ({$r['c']} units within 7 days)", 'in_app');
            }
        }

        return ['labels' => BLOOD_GROUPS, 'available' => $available];
    }

    public function monthlyDonations(): array {
        $rows = $this->tpdo->query(
            "SELECT DATE_FORMAT(collection_date, '%Y-%m') AS month, COUNT(*) AS c
             FROM `blood_units`
             WHERE collection_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(collection_date, '%Y-%m')
             ORDER BY month ASC"
        )->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[$r['month']] = (int) $r['c'];

        $labels = [];
        $data   = [];
        for ($i = 11; $i >= 0; $i--) {
            $dt = new DateTime('first day of this month');
            $dt->modify("-{$i} months");
            $labels[] = $dt->format('M');
            $data[]   = $map[$dt->format('Y-m')] ?? 0;
        }
        return ['labels' => $labels, 'donations' => $data];
    }
}
