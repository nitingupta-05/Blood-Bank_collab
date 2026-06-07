<?php
/**
 * Email sender.
 *
 * Default: PHP's mail().  In a real deployment, swap for PHPMailer or similar.
 * Returns true if the call was made without an immediate error.
 * Failures are logged, never raised.
 */
class EmailService {

    public static function send(string $to, string $subject, string $body): bool {
        $to = filter_var($to, FILTER_VALIDATE_EMAIL);
        if (!$to) return false;

        $subject = '[' . APP_NAME . '] ' . $subject;
        $headers = [
            'From: ' . APP_NAME . ' <noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>',
            'Reply-To: noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
            'X-Mailer: PHP/' . PHP_VERSION,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$sent) {
            self::log("mail() failed to {$to}");
        }
        return $sent;
    }

    private static function log(string $msg): void {
        $dir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../../storage/logs/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($dir . 'email.log', '[' . date('c') . "] {$msg}\n", FILE_APPEND);
    }
}
