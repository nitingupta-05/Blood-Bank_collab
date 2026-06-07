<?php
class NotificationController {
    private PDO $pdo;
    private NotificationDispatcher $dispatcher;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->dispatcher = new NotificationDispatcher($pdo);
    }

    public function list(int $userId, int $limit = 50): array {
        $stmt = $this->pdo->prepare(
            "SELECT id, type, message, channel, delivery_status, related_type, related_id, created_at
             FROM `notifications` WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function countPending(int $userId): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS c FROM `notifications` WHERE user_id = ? AND delivery_status = 'pending'");
        $stmt->execute([$userId]);
        return (int) $stmt->fetch()['c'];
    }

    public function markRead(int $userId, int $notificationId): bool {
        $stmt = $this->pdo->prepare("UPDATE `notifications` SET delivery_status = 'read' WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    }

    public function markAllRead(int $userId): int {
        $stmt = $this->pdo->prepare("UPDATE `notifications` SET delivery_status = 'read' WHERE user_id = ? AND delivery_status IN ('pending','sent')");
        $stmt->execute([$userId]);
        return $stmt->rowCount();
    }

    public function send(int $userId, string $type, string $message, string $channel = 'in_app'): int {
        return $this->dispatcher->send($userId, $type, $message, $channel);
    }
}
