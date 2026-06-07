<?php
/**
 * Database connection.
 * Reads from env vars (.env not required) and falls back to safe XAMPP defaults.
 *
 * Returns the active PDO.  Also assigns it to $GLOBALS['pdo'] for legacy callers.
 */

function db_connect(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'blood_bank_system');
    define('DB_CHARSET', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
            exit(1);
        }
        http_response_code(500);
        if (defined('APP_DEBUG') && APP_DEBUG) {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
        }
        die('Database connection failed. Set APP_DEBUG=true in config/app.php for details.');
    }

    $GLOBALS['pdo'] = $pdo;
    return $pdo;
}
