<?php
/**
 * Server-Sent Events stream of new notifications for the current user.
 * The browser EventSource client reconnects on its own; we just push rows
 * added to `notifications` after the previous event id.
 */
require __DIR__ . '/../../bootstrap/app.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}
enforce_session_timeout();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) ob_end_flush();
set_time_limit(0);

$userId = (int) $_SESSION['user_id'];
$lastEventId = (int) ($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0);

echo "retry: 5000\n\n";
@flush();

try {
    while (!connection_aborted()) {
        $stmt = $pdo->prepare(
            "SELECT id, type, message, channel, delivery_status, related_type, related_id, created_at
             FROM `notifications` WHERE user_id = ? AND id > ? ORDER BY id ASC LIMIT 20"
        );
        $stmt->execute([$userId, $lastEventId]);
        $rows = $stmt->fetchAll();

        if ($rows) {
            foreach ($rows as $r) {
                $lastEventId = (int) $r['id'];
                echo "id: {$lastEventId}\n";
                echo "event: notification\n";
                echo "data: " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }
        } else {
            echo ": keep-alive\n\n";
            @flush();
        }
        sleep(2);
    }
} catch (Throwable $e) {
    error_log('[sse] ' . $e->getMessage());
}
