<?php
/**
 * Shared bootstrap for both web and API front controllers.
 * Sets up session, loads helpers, opens PDO, configures errors.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/php_errors.log');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) require $autoload;

/* Manual class autoloader.  PSR-4: app\Foo\Bar → app/Foo/Bar.php
 * Falls back to scanning app/models and app/controllers by class basename. */
spl_autoload_register(function (string $class): void {
    $prefix = 'app\\';
    if (strpos($class, $prefix) === 0) {
        $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = __DIR__ . '/../app/' . $rel . '.php';
        if (is_file($file)) { require $file; return; }
    }
    /* Fallback: search by short class name. */
    $short = ltrim($class, '\\');
    foreach ([__DIR__ . '/../app/models', __DIR__ . '/../app/controllers'] as $dir) {
        $candidate = $dir . '/' . $short . '.php';
        if (is_file($candidate)) { require $candidate; return; }
    }
});

/* Eager-load all model + controller files so they're available without
 * relying on autoload order (avoids "class not found" surprises). */
foreach (glob(__DIR__ . '/../app/models/*.php') as $f) require_once $f;
foreach (glob(__DIR__ . '/../app/controllers/*.php') as $f) require_once $f;

/* Config files (they define constants and the $pdo global). */
require __DIR__ . '/../config/app.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/csrf.php';

/* Helpers. */
foreach (glob(__DIR__ . '/../app/helpers/*.php') as $h) require_once $h;

/* Middleware. */
foreach (glob(__DIR__ . '/../app/middleware/*.php') as $m) require_once $m;

/* Services. */
foreach (glob(__DIR__ . '/../app/services/*.php') as $s) require_once $s;

/* Route dispatchers (they self-load the routes/*.php files). */
require_once __DIR__ . '/../routes/dispatcher.php';
require_once __DIR__ . '/../routes/api_dispatcher.php';

/* Models + controllers are loaded by the autoloader as needed. */

/* Open DB connection. */
$pdo = db_connect();
$GLOBALS['pdo'] = $pdo;

/* Session is started by AuthHelper when its file is loaded.
 * If the helper hasn't started it yet (e.g. for routes that don't use auth),
 * ensure the session is up before continuing. */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => APP_URL_SCHEME === 'https',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name(SESSION_NAME);
    session_start();
}
enforce_session_timeout();

/* Refresh the CSRF token on first use. */
ensure_csrf_token();

/* Security headers. */
send_security_headers();
