<?php
/**
 * Background-style jobs that run on every request to keep derived data fresh.
 *
 * Each method is idempotent.  Failures never bubble up; they're logged.
 */
class BackgroundJobs {

    public static function runAll(PDO $pdo): void {
        try { self::expireEmergencies($pdo); }   catch (Throwable $e) { error_log($e->getMessage()); }
        try { self::markExpiredBloodUnits($pdo); } catch (Throwable $e) { error_log($e->getMessage()); }
        try { self::refreshDonorEligibility($pdo); } catch (Throwable $e) { error_log($e->getMessage()); }
    }

    public static function expireEmergencies(PDO $pdo): int {
        return $pdo->exec(
            "UPDATE `emergency_requests`
             SET status = 'expired'
             WHERE status = 'active' AND expires_at IS NOT NULL AND expires_at <= NOW()"
        );
    }

    public static function markExpiredBloodUnits(PDO $pdo): int {
        $total = 0;
        $banks = $pdo->query("SELECT id, tenant_db_name FROM `blood_banks` WHERE tenant_db_name IS NOT NULL")->fetchAll();
        foreach ($banks as $bank) {
            try {
                $tpdo = TenantHelper::connect($bank['tenant_db_name']);
                $total += $tpdo->exec(
                    "UPDATE `blood_units` SET status = 'expired' WHERE status = 'available' AND expiry_date <= CURDATE()"
                );
            } catch (Throwable $e) {
                error_log('[BackgroundJobs] Tenant ' . $bank['id'] . ': ' . $e->getMessage());
            }
        }
        return $total;
    }

    public static function refreshDonorEligibility(PDO $pdo): int {
        $total = 0;
        $master = MASTER_DB;
        $banks = $pdo->query("SELECT id, tenant_db_name FROM `blood_banks` WHERE tenant_db_name IS NOT NULL")->fetchAll();
        foreach ($banks as $bank) {
            try {
                $tpdo = TenantHelper::connect($bank['tenant_db_name']);
                $total += $tpdo->exec(
                    "UPDATE `donors` d
                     JOIN `{$master}`.`users` u ON u.id = d.user_id
                     SET d.eligibility_status = CASE
                        WHEN d.age < " . (int) MIN_DONOR_AGE . " OR d.age > " . (int) MAX_DONOR_AGE . " THEN 'ineligible'
                        WHEN d.weight < " . (float) MIN_DONOR_WEIGHT . " THEN 'ineligible'
                        WHEN d.next_eligible_date IS NOT NULL AND d.next_eligible_date > CURDATE() THEN 'ineligible'
                        ELSE 'eligible'
                     END
                     WHERE u.is_active = 1"
                );
            } catch (Throwable $e) {
                error_log('[BackgroundJobs] Tenant ' . $bank['id'] . ': ' . $e->getMessage());
            }
        }
        return $total;
    }
}
