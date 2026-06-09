<?php
/**
 * Dispatches /api.php?route=... to a handler from routes/api.php.
 */

function dispatch_api_route(string $route, string $method, PDO $pdo): void {
    $routes = require __DIR__ . '/api.php';

    foreach ($routes as $entry) {
        [$pattern, $m, $handler] = $entry;
        $opts = $entry[3] ?? [];
        if ($m !== $method) continue;

        $params = [];
        if (!match_pattern($pattern, $route, $params)) continue;

        /* Auth gate. */
        if (empty($opts['public'])) {
            $roles = $opts['roles'] ?? [];
            if (empty($roles)) $roles = admin_roles();
            $user = RoleMiddleware::requireRole($roles, true);
        } else {
            $user = (($opts['auth'] ?? null) === 'optional') ? current_user() : null;
        }

        /* CSRF for state-changing methods unless explicitly disabled. */
        if (in_array($method, ['POST', 'PUT', 'DELETE'], true)
            && ($opts['csrf'] ?? true)) {
            require_csrf();
        }

        [$class, $action] = $handler;
        (new $class($pdo))->{$action}(array_merge($params, ['_user' => $user]));
        return;
    }
    json_out(['ok' => false, 'error' => 'Endpoint not found'], 404);
}

/**
 * Convert a route pattern like "bookings/:id/approve" to a regex,
 * match against the given endpoint, and return extracted params.
 */
function match_pattern(string $pattern, string $endpoint, array &$params): bool {
    $params = [];
    if ($pattern === $endpoint) return true;
    if (!str_contains($pattern, ':')) return false;

    $regex = '#^' . preg_replace_callback('/:([A-Za-z_]+)/', function ($m) use (&$params) {
        $params[$m[1]] = null;
        return '([^/]+)/';
    }, $pattern) . '$#';

    if (!preg_match($regex, $endpoint, $m)) return false;
    array_shift($m);
    $i = 0;
    foreach (array_keys($params) as $k) {
        $params[$k] = $m[$i++] ?? null;
    }
    return true;
}
