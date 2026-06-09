<?php
/**
 * Manages tenant database creation and connections.
 *
 * Each blood bank gets its own MySQL database (blood_bank_{id})
 * for operational data. The master database holds shared auth tables.
 */

/**
 * Quick-access helper: returns the tenant PDO for the current request,
 * or NULL if no tenant context is available.
 */
function tenant_pdo(): ?PDO {
    return $GLOBALS['tenantPdo'] ?? null;
}
class TenantHelper {

    private static array $connections = [];

    public static function ensureMasterSchema(PDO $pdo): void {
        $table = $pdo->query("SHOW TABLES LIKE 'blood_banks'")->fetch();
        if (!$table) return;
        $stmt = $pdo->query("SHOW COLUMNS FROM `blood_banks` LIKE 'tenant_db_name'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE `blood_banks` ADD COLUMN `tenant_db_name` VARCHAR(64) DEFAULT NULL AFTER `capacity`, ADD KEY `idx_banks_tenant_db` (`tenant_db_name`)");
        }
    }

    /**
     * Create a new tenant database and run the tenant migration.
     */
    public static function createDatabase(PDO $pdo, int $bankId): string {
        $dbName = 'blood_bank_' . $bankId;
        self::assertDatabaseName($dbName);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $tenantPdo = self::connect($dbName);
        $migration = __DIR__ . '/../../database/migrations/002_create_tenant.sql';
        if (is_file($migration)) {
            $sql = file_get_contents($migration);
            $lines = preg_grep('/^\s*--/', explode("\n", $sql), PREG_GREP_INVERT);
            $sqlNoComments = implode("\n", $lines);
            $queries = array_filter(array_map('trim', preg_split('/;\s*(?:\n|$)/', $sqlNoComments)));
            foreach ($queries as $q) {
                if ($q === '') continue;
                if (stripos($q, 'DROP TABLE') === 0) continue;
                $q = preg_replace('/^CREATE\s+TABLE\s+`/i', 'CREATE TABLE IF NOT EXISTS `', $q);
                $tenantPdo->exec($q);
            }
        }
        return $dbName;
    }

    /**
     * Connect to a tenant database by name.
     * Caches connections so each tenant DB is only opened once per request.
     */
    public static function connect(string $dbName): PDO {
        self::assertDatabaseName($dbName);
        if (isset(self::$connections[$dbName])) {
            return self::$connections[$dbName];
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, $dbName, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        self::$connections[$dbName] = $pdo;
        return $pdo;
    }

    /**
     * Resolve the tenant database name for the current user's bank context.
     */
    public static function resolveDbName(?array $user, PDO $masterPdo): ?string {
        $bankId = current_blood_bank_id($user, $masterPdo);
        if (!$bankId) return null;
        $stmt = $masterPdo->prepare("SELECT tenant_db_name FROM `blood_banks` WHERE id = ?");
        $stmt->execute([$bankId]);
        $row = $stmt->fetch();
        return $row && $row['tenant_db_name'] ? $row['tenant_db_name'] : null;
    }

    /**
     * Get the tenant PDO for the current user's bank context.
     * Returns null if no tenant context (super_admin, donor without bank, etc.)
     */
    public static function getTenantPdo(?array $user, PDO $masterPdo): ?PDO {
        $dbName = self::resolveDbName($user, $masterPdo);
        if (!$dbName) return null;
        return self::connect($dbName);
    }

    public static function tenantDbNames(PDO $masterPdo): array {
        self::ensureMasterSchema($masterPdo);
        $rows = $masterPdo->query(
            "SELECT tenant_db_name FROM `blood_banks`
             WHERE tenant_db_name IS NOT NULL AND tenant_db_name <> ''
             ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter(array_map('strval', $rows), fn($db) => preg_match('/^blood_bank_[0-9]+$/', $db)));
    }

    public static function publicStats(PDO $masterPdo): array {
        $tenantDbs = self::tenantDbNames($masterPdo);
        $tenantPlaceholders = $tenantDbs ? implode(',', array_fill(0, count($tenantDbs), '?')) : '';

        $donorsSql = "SELECT COUNT(*)
                      FROM `donors` d
                      LEFT JOIN `blood_banks` bb ON bb.id = d.preferred_blood_bank_id";
        $donorsParams = [];
        if ($tenantDbs) {
            $donorsSql .= " WHERE d.preferred_blood_bank_id IS NULL OR bb.tenant_db_name IS NULL OR bb.tenant_db_name = '' OR bb.tenant_db_name NOT IN ({$tenantPlaceholders})";
            $donorsParams = $tenantDbs;
        }
        $donorsStmt = $masterPdo->prepare($donorsSql);
        $donorsStmt->execute($donorsParams);

        $unitsSql = "SELECT COUNT(*)
                     FROM `blood_units` bu
                     LEFT JOIN `blood_banks` bb ON bb.id = bu.blood_bank_id
                     WHERE bu.status = 'available' AND bu.expiry_date > CURDATE()";
        $unitsParams = [];
        if ($tenantDbs) {
            $unitsSql .= " AND (bb.tenant_db_name IS NULL OR bb.tenant_db_name = '' OR bb.tenant_db_name NOT IN ({$tenantPlaceholders}))";
            $unitsParams = $tenantDbs;
        }
        $unitsStmt = $masterPdo->prepare($unitsSql);
        $unitsStmt->execute($unitsParams);

        $stats = [
            'donors' => (int) $donorsStmt->fetchColumn(),
            'units'  => (int) $unitsStmt->fetchColumn(),
            'banks'  => (int) $masterPdo->query("SELECT COUNT(*) FROM `blood_banks`")->fetchColumn(),
            'emerg'  => (int) $masterPdo->query("SELECT COUNT(*) FROM `emergency_requests` WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        ];

        foreach ($tenantDbs as $dbName) {
            try {
                $tenantPdo = self::connect($dbName);
                $stats['donors'] += (int) $tenantPdo->query("SELECT COUNT(*) FROM `donors`")->fetchColumn();
                $stats['units'] += (int) $tenantPdo->query("SELECT COUNT(*) FROM `blood_units` WHERE status = 'available' AND expiry_date > CURDATE()")->fetchColumn();
            } catch (Throwable $e) {
                error_log('[tenant-stats] ' . $dbName . ': ' . $e->getMessage());
            }
        }

        return $stats;
    }

    public static function publicBloodStock(PDO $masterPdo): array {
        $counts = array_fill_keys(BLOOD_GROUPS, 0);
        $tenantDbs = self::tenantDbNames($masterPdo);
        $tenantPlaceholders = $tenantDbs ? implode(',', array_fill(0, count($tenantDbs), '?')) : '';

        $sql = "SELECT bu.blood_group, COUNT(*) AS c
                FROM `blood_units` bu
                LEFT JOIN `blood_banks` bb ON bb.id = bu.blood_bank_id
                WHERE bu.status = 'available' AND bu.expiry_date > CURDATE()";
        $params = [];
        if ($tenantDbs) {
            $sql .= " AND (bb.tenant_db_name IS NULL OR bb.tenant_db_name = '' OR bb.tenant_db_name NOT IN ({$tenantPlaceholders}))";
            $params = $tenantDbs;
        }
        $sql .= " GROUP BY bu.blood_group";
        $stmt = $masterPdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            if (isset($counts[$row['blood_group']])) $counts[$row['blood_group']] += (int) $row['c'];
        }

        foreach ($tenantDbs as $dbName) {
            try {
                $rows = self::connect($dbName)->query(
                    "SELECT blood_group, COUNT(*) AS c
                     FROM `blood_units`
                     WHERE status = 'available' AND expiry_date > CURDATE()
                     GROUP BY blood_group"
                )->fetchAll();
                foreach ($rows as $row) {
                    if (isset($counts[$row['blood_group']])) $counts[$row['blood_group']] += (int) $row['c'];
                }
            } catch (Throwable $e) {
                error_log('[tenant-stock] ' . $dbName . ': ' . $e->getMessage());
            }
        }

        return array_map(fn($group) => $counts[$group], BLOOD_GROUPS);
    }

    private static function assertDatabaseName(string $dbName): void {
        if (!preg_match('/^blood_bank_[0-9]+$/', $dbName)) {
            throw new InvalidArgumentException('Invalid tenant database name.');
        }
    }
}
