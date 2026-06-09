<?php
/**
 * Thin web front controller.  All HTML requests come through here and are
 * dispatched to a controller method via routes/web.php.
 *
 * URL convention:
 *   /index.php?route=<name>           (e.g. ?route=login, ?route=dashboard, ?route=api/blood-units/list)
 *   /                                  → redirect to home
 */

declare(strict_types=1);

define('APP_START', microtime(true));

require __DIR__ . '/../bootstrap/app.php';

$route = (string) ($_GET['route'] ?? '');

/* Home page is a plain view (no auth). */
if ($route === '' || $route === 'home') {
    $public = current_user() === null;
    $stats = TenantHelper::publicStats($pdo);
    view('home', ['public' => $public, 'donors' => $stats['donors'], 'units' => $stats['units'], 'banks' => $stats['banks'], 'emerg' => $stats['emerg']]);
    exit;
}

/* Auth pages are also plain views (GET only).  POSTs go to the dispatcher. */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $viewOnly = [
        'login'           => 'auth/login',
        'register'        => 'auth/register',
        'forgot-password' => 'auth/forgot-password',
    ];
    if (isset($viewOnly[$route])) {
        view($viewOnly[$route], ['csrf' => ensure_csrf_token()]);
        exit;
    }
    if ($route === 'reset-password') {
        view('auth/reset-password', [
            'csrf'  => ensure_csrf_token(),
            'token' => (string) ($_GET['token'] ?? ''),
        ]);
        exit;
    }
}

/* Run background jobs once per request to keep derived data fresh. */
BackgroundJobs::runAll($pdo);

try {
    dispatch_web_route($route, $_SERVER['REQUEST_METHOD'] ?? 'GET', $pdo);
} catch (Throwable $e) {
    error_log('[web] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    view('errors/500', ['message' => APP_DEBUG ? $e->getMessage() : 'Server error.']);
}
