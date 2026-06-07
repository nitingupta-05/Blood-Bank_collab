<?php
/**
 * Finish-up script for the demo data that add-demo-data.php couldn't
 * complete on the first run.  Inserts the missing bookings, temperature
 * logs, and notifications.  Idempotent.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require __DIR__ . '/../bootstrap/app.php';

$now = date('Y-m-d H:i:s');

$hospitalId = (int) $pdo->query("SELECT id FROM `users` WHERE email = 'city@metrohosp.org'")->fetchColumn();
if (!$hospitalId) { fwrite(STDERR, "Hospital user not found\n"); exit(1); }

$existing = (int) $pdo->query("SELECT COUNT(*) FROM `blood_bookings`")->fetchColumn();
if ($existing === 0) {
    $insBook = $pdo->prepare(
        "INSERT INTO `blood_bookings`
         (hospital_id, blood_group, quantity, required_date, patient_name, status, notes, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)"
    );
    $bookings = [
        ['A+',  3, date('Y-m-d', strtotime('+5 days')),  'Geeta Krishnan', 'Knee replacement surgery scheduled'],
        ['O+',  5, date('Y-m-d', strtotime('+10 days')), 'Mohan Lal',      'Cardiac procedure — need 5 units reserve'],
    ];
    foreach ($bookings as $b) {
        [$bg, $qty, $reqDate, $patient, $notes] = $b;
        $insBook->execute([$hospitalId, $bg, $qty, $reqDate, $patient, $notes, $now, $now]);
    }
    fwrite(STDERR, "[ok] " . count($bookings) . " pending bookings\n");
} else {
    fwrite(STDERR, "[skip] bookings already present ($existing rows)\n");
}

$existing = (int) $pdo->query("SELECT COUNT(*) FROM `temperature_logs`")->fetchColumn();
if ($existing === 0) {
    $newAreaIds = $pdo->query(
        "SELECT id FROM `storage_areas`
         WHERE blood_bank_id IN (SELECT id FROM `blood_banks` WHERE email IN ('admin@punebb.org', 'admin@hydbb.org'))
         ORDER BY id LIMIT 2"
    )->fetchAll(PDO::FETCH_COLUMN);
    if (count($newAreaIds) < 2) {
        fwrite(STDERR, "[warn] not enough new storage areas for temp logs (have " . count($newAreaIds) . ")\n");
    } else {
        $insTemp = $pdo->prepare(
            "INSERT INTO `temperature_logs` (storage_area_id, temperature, recorded_at)
             VALUES (?, ?, ?)"
        );
        $insTemp->execute([$newAreaIds[0], 4.2,   $now]);
        $insTemp->execute([$newAreaIds[1], -24.7, $now]);
        fwrite(STDERR, "[ok] 2 temperature logs\n");
    }
} else {
    fwrite(STDERR, "[skip] temperature_logs already present ($existing rows)\n");
}

$existing = (int) $pdo->query("SELECT COUNT(*) FROM `notifications` WHERE type = 'system'")->fetchColumn();
if ($existing < 3) {
    $insNotif = $pdo->prepare(
        "INSERT INTO `notifications` (user_id, type, message, channel, delivery_status, created_at)
         VALUES (?, ?, ?, 'in_app', 'sent', ?)"
    );
    $rows = $pdo->query("SELECT id, email FROM `users` WHERE email IN ('admin@punebb.org', 'admin@hydbb.org')")->fetchAll(PDO::FETCH_ASSOC);
    $added = 0;
    foreach ($rows as $r) {
        $msg = "Welcome to " . ($r['email'] === 'admin@punebb.org' ? 'Pune Regional Blood Bank' : 'Hyderabad Life Savers') . '!';
        $insNotif->execute([$r['id'], 'system', $msg, $now]);
        $added++;
    }
    fwrite(STDERR, "[ok] $added welcome notifications\n");
} else {
    fwrite(STDERR, "[skip] welcome notifications already present ($existing system rows)\n");
}
