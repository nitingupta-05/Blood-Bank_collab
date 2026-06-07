<?php
/**
 * Tiny input/output helpers used by API handlers.
 */

if (!function_exists('json_out')) {
    function json_out(array $data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('read_json_body')) {
    function read_json_body(): array {
        $raw = file_get_contents('php://input');
        if (!$raw) return [];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('require_api_auth')) {
    function require_api_auth(array $roles = []): array {
        enforce_session_timeout();
        $user = current_user();
        if (!$user) {
            json_out(['ok' => false, 'error' => 'Unauthorized'], 401);
        }
        if (!empty($roles) && !in_array($user['role'], $roles, true)) {
            json_out(['ok' => false, 'error' => 'Forbidden'], 403);
        }
        return $user;
    }
}

if (!function_exists('get_int')) {
    function get_int(string $key, int $default = 0, int $min = 0, int $max = PHP_INT_MAX): int {
        $v = filter_var($_GET[$key] ?? null, FILTER_VALIDATE_INT);
        if ($v === false) return $default;
        return max($min, min($max, $v));
    }
}

if (!function_exists('get_str')) {
    function get_str(string $key, string $default = ''): string {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }
}

if (!function_exists('get_post')) {
    /**
     * Pull a field from JSON body OR $_POST (POST form-encoded).
     */
    function get_post(string $key, $default = null) {
        static $json = null;
        if ($json === null) $json = read_json_body();
        if (array_key_exists($key, $json)) return $json[$key];
        return $_POST[$key] ?? $default;
    }
}
