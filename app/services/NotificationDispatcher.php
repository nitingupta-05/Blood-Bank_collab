<?php
/**
 * In-app notification dispatcher.
 *
 * Always writes a row to the `notifications` table (in_app channel).
 * Optionally also sends via email (EmailService) or SMS (if a sender is configured).
 *
 * All dispatches fail-soft: if a channel fails, the in_app row is still saved.
 */
class NotificationDispatcher {

    private PDO $pdo;
    private ?PDO $tpdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->tpdo = tenant_pdo();
    }

    public function send(int $userId, string $type, string $message, string $channel = 'in_app', ?string $relatedType = null, ?int $relatedId = null): int {
        $userId = (int) $userId;
        $type = in_array($type, NOTIFICATION_TYPES, true) ? $type : 'system';
        $channel = in_array($channel, NOTIFICATION_CHANNELS, true) ? $channel : 'in_app';
        $tpdo = $this->tpdo ?: $this->pdo;

        $status = 'pending';
        try {
            $stmt = $tpdo->prepare(
                "INSERT INTO `notifications` (user_id, type, message, channel, delivery_status, related_type, related_id)
                 VALUES (?, ?, ?, ?, 'pending', ?, ?)"
            );
            $stmt->execute([$userId, $type, $message, $channel, $relatedType, $relatedId]);
            $id = (int) $tpdo->lastInsertId();

            $status = match ($channel) {
                'email' => $this->sendEmail($userId, $message) ? 'sent' : 'failed',
                'sms'   => $this->sendSms($userId, $message)   ? 'sent' : 'failed',
                default => 'sent',
            };
            $tpdo->prepare("UPDATE `notifications` SET delivery_status = ? WHERE id = ?")
                 ->execute([$status, $id]);
            return $id;
        } catch (Throwable $e) {
            error_log('[Notification] ' . $e->getMessage());
            return 0;
        }
    }

    public function sendToMany(array $userIds, string $type, string $message, string $channel = 'in_app'): int {
        $count = 0;
        foreach ($userIds as $uid) {
            if ($this->send((int) $uid, $type, $message, $channel)) $count++;
        }
        return $count;
    }

    public function sendToAdmins(string $type, string $message, string $channel = 'in_app'): int {
        $stmt = $this->pdo->query("SELECT id FROM `users` WHERE role IN ('super_admin','blood_bank_admin','staff') AND is_active = 1");
        $ids = array_map(fn($r) => (int) $r['id'], $stmt->fetchAll());
        return $this->sendToMany($ids, $type, $message, $channel);
    }

    public function sendToEligibleDonorsInCity(string $city, string $type, string $message, string $channel = 'in_app'): int {
        $tpdo = $this->tpdo ?: $this->pdo;
        $master = MASTER_DB;
        $stmt = $tpdo->prepare(
            "SELECT u.id FROM `{$master}`.`users` u
             JOIN `donors` d ON d.user_id = u.id
             WHERE u.is_active = 1 AND u.city LIKE ? AND d.eligibility_status = 'eligible'"
        );
        $stmt->execute(['%' . $city . '%']);
        $ids = array_map(fn($r) => (int) $r['id'], $stmt->fetchAll());
        return $this->sendToMany($ids, $type, $message, $channel);
    }

    private function sendEmail(int $userId, string $message): bool {
        try {
            $stmt = $this->pdo->prepare("SELECT email FROM `users` WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch();
            if (!$u) return false;
            return EmailService::send((string) $u['email'], APP_NAME, $message);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function sendSms(int $userId, string $message): bool {
        // No SMS provider configured by default.  Returns false so the row stays as "pending".
        return false;
    }
}
