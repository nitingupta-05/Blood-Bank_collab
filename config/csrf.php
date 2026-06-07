<?php
/**
 * CSRF protection helpers.
 *
 * Usage:
 *   In forms:    <?= csrf_token_field() ?>
 *   AJAX header: X-CSRF-TOKEN: <?= generate_csrf_token() ?>
 *   Validate:    require_csrf();
 */

function generate_csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function ensure_csrf_token(): string {
    return generate_csrf_token();
}

function csrf_token_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['_csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

function require_csrf(): void {
    $token = $_POST['_csrf_token']
        ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!verify_csrf_token($token)) {
        $isJson = (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json'))
              || (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api.php'));

        if ($isJson) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Invalid or missing CSRF token']);
            exit;
        }
        $_SESSION['error'] = 'Invalid form submission. Please try again.';
        $back = $_SERVER['HTTP_REFERER'] ?? '?route=home';
        header('Location: ' . $back);
        exit;
    }
}
