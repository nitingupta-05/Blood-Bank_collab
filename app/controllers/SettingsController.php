<?php
class SettingsController {
    private PDO $pdo;
    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function getHospitalDetails(int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT id, email, first_name, last_name, phone, address, city, is_active FROM `users` WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        return $u ?: null;
    }

    public function updateHospitalDetails(int $userId, array $data): array {
        $phone   = ValidationHelper::phone($data['phone'] ?? null);
        $address = ValidationHelper::nonEmptyString($data['address'] ?? null, 500);
        $city    = ValidationHelper::nonEmptyString($data['city'] ?? null, 100);
        if (!$phone || !$address || !$city) {
            return ['ok' => false, 'error' => 'Phone, address, and city are required'];
        }
        $stmt = $this->pdo->prepare("UPDATE `users` SET phone = ?, address = ?, city = ? WHERE id = ?");
        $ok = $stmt->execute([$phone, $address, $city, $userId]);
        return ['ok' => $ok];
    }

    public function getNotificationSettings(int $userId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM `notification_settings` WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: ['email_notifications' => 1, 'sms_notifications' => 0, 'in_app_notifications' => 1];
    }

    public function updateNotificationSettings(int $userId, array $data): array {
        $email  = !empty($data['email_notifications']) ? 1 : 0;
        $sms    = !empty($data['sms_notifications']) ? 1 : 0;
        $inApp  = !empty($data['in_app_notifications']) ? 1 : 0;

        $stmt = $this->pdo->prepare("SELECT id FROM `notification_settings` WHERE user_id = ?");
        $stmt->execute([$userId]);

        if ($stmt->fetch()) {
            $ok = $this->pdo->prepare(
                "UPDATE `notification_settings` SET email_notifications = ?, sms_notifications = ?, in_app_notifications = ? WHERE user_id = ?"
            )->execute([$email, $sms, $inApp, $userId]);
        } else {
            $ok = $this->pdo->prepare(
                "INSERT INTO `notification_settings` (user_id, email_notifications, sms_notifications, in_app_notifications) VALUES (?, ?, ?, ?)"
            )->execute([$userId, $email, $sms, $inApp]);
        }
        return ['ok' => (bool) $ok];
    }

    public function getSystemStats(): array {
        $stats = [];
        foreach ([
            'total_units'         => "SELECT COUNT(*) FROM `blood_units`",
            'total_donors'        => "SELECT COUNT(*) FROM `donors`",
            'total_hospitals'     => "SELECT COUNT(*) FROM `users` WHERE role='hospital'",
            'active_emergencies'  => "SELECT COUNT(*) FROM `emergency_requests` WHERE status='active'",
            'total_blood_banks'   => "SELECT COUNT(*) FROM `blood_banks`",
        ] as $key => $sql) {
            $stats[$key] = (int) $this->pdo->query($sql)->fetchColumn();
        }
        return $stats;
    }

    public function getBloodStockSummary(): array {
        $model = new BloodUnit($this->pdo);
        return $model->getInventorySummary();
    }
}
