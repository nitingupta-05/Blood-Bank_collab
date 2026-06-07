<?php
/**
 * Authentication helpers: session, timeout, role gates, lockout, login attempts.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name(SESSION_NAME);
    session_start();
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'         => (int) $_SESSION['user_id'],
        'role'       => (string) ($_SESSION['role'] ?? ''),
        'email'      => (string) ($_SESSION['email'] ?? ''),
        'first_name' => (string) ($_SESSION['first_name'] ?? ''),
        'last_name'  => (string) ($_SESSION['last_name'] ?? ''),
        'city'       => (string) ($_SESSION['city'] ?? ''),
    ];
}

function enforce_session_timeout(): void {
    if (empty($_SESSION['user_id'])) return;
    $timeout = SESSION_TIMEOUT;
    $last    = (int) ($_SESSION['last_active'] ?? 0);
    if ($last && (time() - $last) > $timeout) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['error'] = 'Session expired. Please login again.';
        header('Location: ' . app_url('login'));
        exit;
    }
    $_SESSION['last_active'] = time();
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['role']       = (string) $user['role'];
    $_SESSION['email']      = (string) ($user['email'] ?? '');
    $_SESSION['first_name'] = (string) ($user['first_name'] ?? '');
    $_SESSION['last_name']  = (string) ($user['last_name'] ?? '');
    $_SESSION['city']       = (string) ($user['city'] ?? '');
    $_SESSION['last_active'] = time();
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function app_url(string $route = ''): string {
    return APP_URL . ($route ? '/index.php?route=' . urlencode($route) : '');
}

function admin_roles(): array {
    return ['super_admin', 'blood_bank_admin', 'staff'];
}

function is_admin_role(?string $role): bool {
    return in_array($role ?? '', admin_roles(), true);
}

function record_login_attempt(PDO $pdo, string $email, bool $success): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, successful) VALUES (?, ?, ?)");
        $stmt->execute([$email, client_ip(), $success ? 1 : 0]);
    } catch (Throwable $e) {
        // never block login because logging failed
    }
}

function is_account_locked(PDO $pdo, string $email): bool {
    try {
        $since = date('Y-m-d H:i:s', time() - (LOGIN_LOCKOUT_MINUTES * 60));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM login_attempts
             WHERE email = ? AND successful = 0 AND attempted_at >= ?"
        );
        $stmt->execute([$email, $since]);
        $count = (int) $stmt->fetch()['c'];
        if ($count >= LOGIN_MAX_ATTEMPTS) {
            $stmt = $pdo->prepare(
                "SELECT attempted_at FROM login_attempts
                 WHERE email = ? AND successful = 0 AND attempted_at >= ?
                 ORDER BY attempted_at DESC LIMIT 1"
            );
            $stmt->execute([$email, $since]);
            $last = $stmt->fetch();
            if ($last && strtotime($last['attempted_at'] . ' +' . LOGIN_LOCKOUT_MINUTES . ' minutes') > time()) {
                return true;
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    return false;
}

function client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return explode(',', $_SERVER[$key])[0];
        }
    }
    return '0.0.0.0';
}

function audit(PDO $pdo, string $action, ?int $userId = null, ?string $record = null, $details = null): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs (user_id, action_type, affected_record, details, ip_address)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $action,
            $record,
            is_string($details) ? $details : json_encode($details, JSON_UNESCAPED_UNICODE),
            client_ip(),
        ]);
    } catch (Throwable $e) {
        // never break the request because of audit failure
    }
}
