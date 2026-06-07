<?php
/**
 * Thin API front controller.  All /api/* JSON requests are dispatched via
 * routes/api.php.
 *
 * URL convention:
 *   /api.php?route=blood-units/list              (no leading slash, no prefix)
 *   /api.php?route=public/booking
 *   /api.php?route=emergencies/12/fulfill
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap/app.php';

/* JSON-only entry point. */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

/* CSRF token refresh on GET (clients fetch this on page load). */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && (($_GET['route'] ?? '') === 'csrf')) {
    json_out(['ok' => true, 'csrf' => ensure_csrf_token()]);
}

BackgroundJobs::runAll($pdo);

$route = (string) ($_GET['route'] ?? '');
if ($route === '') json_out(['ok' => false, 'error' => 'Missing route'], 400);

try {
    dispatch_api_route($route, $_SERVER['REQUEST_METHOD'] ?? 'GET', $pdo);
} catch (Throwable $e) {
    error_log('[api] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_out(['ok' => false, 'error' => APP_DEBUG ? $e->getMessage() : 'Server error.'], 500);
}
