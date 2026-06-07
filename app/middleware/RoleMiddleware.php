<?php
/**
 * Role-based authorization gate for web routes and API endpoints.
 */
class RoleMiddleware {

    /**
     * Require the current user to hold one of the given roles.
     * Halts with 403/redirect otherwise.
     */
    public static function requireRole(array $roles, bool $jsonResponse = false): array {
        enforce_session_timeout();
        $user = current_user();
        if (!$user) {
            if ($jsonResponse) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
                exit;
            }
            $_SESSION['error'] = 'Please login to continue.';
            header('Location: ' . app_url('login'));
            exit;
        }
        if (!empty($roles) && !in_array($user['role'], $roles, true)) {
            if ($jsonResponse) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Forbidden']);
                exit;
            }
            $_SESSION['error'] = 'Access denied.';
            header('Location: ' . app_url('home'));
            exit;
        }
        return $user;
    }
}
