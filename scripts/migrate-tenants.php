<?php
/**
 * Migrate existing data to tenant databases.
 * Creates a tenant DB for each existing blood bank and copies data.
 *
 *   php scripts/migrate-tenants.php
 *
 * CLI only. Idempotent — skips banks that already have tenant_db_name set.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require __DIR__ . '/../bootstrap/app.php';

$banks = $pdo->query("SELECT id, name, tenant_db_name FROM `blood_banks` ORDER BY id")->fetchAll();

foreach ($banks as $bank) {
    $id = (int) $bank['id'];
    if (!empty($bank['tenant_db_name'])) {
        fwrite(STDERR, "[skip] Bank #{$id} ({$bank['name']}) already has tenant DB: {$bank['tenant_db_name']}\n");
        continue;
    }

    fwrite(STDERR, "[migrate] Bank #{$id} ({$bank['name']})...\n");

    $dbName = TenantHelper::createDatabase($pdo, $id);

    $tenantPdo = TenantHelper::connect($dbName);

    $tenantPdo->beginTransaction();
    try {
        // Copy donors
        $donors = $pdo->prepare("SELECT * FROM `donors` WHERE preferred_blood_bank_id = ?");
        $donors->execute([$id]);
        $insDonor = $tenantPdo->prepare(
            "INSERT INTO `donors` (id, user_id, blood_group, age, weight, gender, medical_history, last_donation_date, next_eligible_date, eligibility_status, total_donations, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)"
        );
        foreach ($donors as $d) {
            $insDonor->execute([
                $d['id'], $d['user_id'], $d['blood_group'], $d['age'], $d['weight'],
                $d['gender'], $d['medical_history'], $d['last_donation_date'],
                $d['next_eligible_date'], $d['eligibility_status'], $d['total_donations'],
                $d['created_at'], $d['updated_at'],
            ]);
        }

        // Copy blood_units
        $units = $pdo->prepare("SELECT * FROM `blood_units` WHERE blood_bank_id = ?");
        $units->execute([$id]);
        $insUnit = $tenantPdo->prepare(
            "INSERT INTO `blood_units` (id, barcode, blood_group, collection_date, expiry_date, donor_id, storage_location, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE barcode = VALUES(barcode)"
        );
        foreach ($units as $u) {
            $insUnit->execute([
                $u['id'], $u['barcode'], $u['blood_group'], $u['collection_date'],
                $u['expiry_date'], $u['donor_id'], $u['storage_location'],
                $u['status'], $u['created_by'], $u['created_at'], $u['updated_at'],
            ]);
        }

        // Copy storage_areas
        $areas = $pdo->prepare("SELECT * FROM `storage_areas` WHERE blood_bank_id = ?");
        $areas->execute([$id]);
        $insArea = $tenantPdo->prepare(
            "INSERT INTO `storage_areas` (id, name, capacity, current_occupancy, current_temperature, last_temperature_check, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name)"
        );
        foreach ($areas as $a) {
            $insArea->execute([
                $a['id'], $a['name'], $a['capacity'], $a['current_occupancy'],
                $a['current_temperature'], $a['last_temperature_check'], $a['created_at'],
            ]);
        }

        // Copy temperature_logs
        $logs = $pdo->query("SELECT tl.* FROM `temperature_logs` tl JOIN `storage_areas` sa ON sa.id = tl.storage_area_id WHERE sa.blood_bank_id = {$id}")->fetchAll();
        $insLog = $tenantPdo->prepare(
            "INSERT INTO `temperature_logs` (id, storage_area_id, temperature, recorded_at)
             VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE temperature = VALUES(temperature)"
        );
        foreach ($logs as $l) {
            $insLog->execute([$l['id'], $l['storage_area_id'], $l['temperature'], $l['recorded_at']]);
        }

        // Copy notifications for users of this bank
        $bankUsers = $pdo->prepare("SELECT id FROM `users` WHERE city = (SELECT city FROM `blood_banks` WHERE id = ?)");
        $bankUsers->execute([$id]);
        $userIds = $bankUsers->fetchAll(PDO::FETCH_COLUMN);
        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $notifs = $pdo->prepare("SELECT * FROM `notifications` WHERE user_id IN ({$placeholders})");
            $notifs->execute($userIds);
            $insNotif = $tenantPdo->prepare(
                "INSERT INTO `notifications` (id, user_id, type, message, channel, delivery_status, related_type, related_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id = VALUES(id)"
            );
            foreach ($notifs as $n) {
                $insNotif->execute([
                    $n['id'], $n['user_id'], $n['type'], $n['message'], $n['channel'],
                    $n['delivery_status'], $n['related_type'], $n['related_id'], $n['created_at'],
                ]);
            }

            $settings = $pdo->prepare("SELECT * FROM `notification_settings` WHERE user_id IN ({$placeholders})");
            $settings->execute($userIds);
            $insSetting = $tenantPdo->prepare(
                "INSERT INTO `notification_settings` (id, user_id, email_notifications, sms_notifications, in_app_notifications, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)"
            );
            foreach ($settings as $s) {
                $insSetting->execute([
                    $s['id'], $s['user_id'], $s['email_notifications'], $s['sms_notifications'],
                    $s['in_app_notifications'], $s['created_at'], $s['updated_at'],
                ]);
            }
        }

        $tenantPdo->commit();

        // Update blood_banks row with tenant DB name
        $pdo->prepare("UPDATE `blood_banks` SET tenant_db_name = ? WHERE id = ?")->execute([$dbName, $id]);

        fwrite(STDERR, "[ok] Bank #{$id} migrated to {$dbName}\n");
    } catch (Throwable $e) {
        $tenantPdo->rollBack();
        fwrite(STDERR, "[error] Bank #{$id}: " . $e->getMessage() . "\n");
    }
}

fwrite(STDERR, "\nDone.\n");
