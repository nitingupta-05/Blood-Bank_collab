<?php
/**
 * Dispatcher for web routes.
 *
 * The routes file is `routes/web.php` which returns an array of
 *   [method, name, handler, options]
 * tuples.  This function loads that file and dispatches the request.
 */

function dispatch_web_route(string $route, string $method, PDO $pdo): void {
    $routes = require __DIR__ . '/web.php';

    foreach ($routes as $entry) {
        [$m, $name, $handler] = $entry;
        $opts = $entry[3] ?? [];
        if ($m !== $method || $name !== $route) continue;

        /* Auth gate. */
        if (!empty($opts['roles'])) {
            RoleMiddleware::requireRole($opts['roles']);
        }

        /* Call handler. */
        if (is_array($handler) && is_string($handler[0])) {
            [$class, $action] = $handler;
            (new $class($pdo))->{$action}([]);
            return;
        }
        if (is_callable($handler)) {
            $handler();
            return;
        }
        http_response_code(500);
        echo 'Invalid route handler.';
        return;
    }

    http_response_code(404);
    if (is_file(__DIR__ . '/../app/views/404.php')) {
        require __DIR__ . '/../app/views/404.php';
    } else {
        echo '404 Not Found';
    }
}

function view(string $name, array $data = []): void {
    extract($data, EXTR_SKIP);
    $file = __DIR__ . '/../app/views/' . $name . '.php';
    if (!is_file($file)) {
        http_response_code(500);
        echo 'View not found: ' . htmlspecialchars($name);
        return;
    }
    require $file;
}
