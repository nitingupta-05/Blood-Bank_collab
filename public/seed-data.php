<?php
/**
 * Seeder: wipes the database, runs the migration, and inserts a small set of
 * known accounts + 50 blood units across blood groups.
 *
 *   php public/seed-data.php
 *
 * Default accounts (all password: "Password123"):
 *   super_admin@example.com  / super_admin
 *   admin@centralbb.org      / blood_bank_admin
 *   city@metrohosp.org       / hospital
 *   ramesh@example.com       / donor
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require __DIR__ . '/../bootstrap/app.php';

$now  = date('Y-m-d H:i:s');
$today= date('Y-m-d');

/* Run migration. */
$migration = __DIR__ . '/../database/migrations/001_create_tables.sql';
$sql = file_get_contents($migration);
/* Strip comment lines first, then split on `;`. */
$lines = preg_grep('/^\s*--/', explode("\n", $sql), PREG_GREP_INVERT);
$sqlNoComments = implode("\n", $lines);
$queries = array_filter(array_map('trim', preg_split('/;\s*(?:\n|$)/', $sqlNoComments)));
foreach ($queries as $q) {
    if ($q === '') continue;
    $pdo->exec($q);
}
fwrite(STDERR, "[ok] migration applied (" . count($queries) . " statements)\n");

/* Wipe (in case migration didn't because of partial state). */
$tables = ['notifications', 'blood_bookings', 'emergency_requests',
           'temperature_logs', 'blood_units', 'donors', 'users',
           'storage_areas', 'blood_banks', 'login_attempts',
           'notification_settings', 'audit_logs'];
foreach ($tables as $t) {
    try { $pdo->exec("DELETE FROM `{$t}`"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE `{$t}` AUTO_INCREMENT = 1"); } catch (Throwable $e) {}
}

/* --- Users ---------------------------------------------------------- */
$users = [
    ['super_admin', 'Super', 'Admin',   'super_admin@example.com', 'Mumbai',     '+91-9000000001'],
    ['blood_bank_admin', 'Aarav', 'Sharma', 'admin@centralbb.org',   'Mumbai',     '+91-9000000002'],
    ['hospital',  'Metro', 'Hospital',  'city@metrohosp.org',     'Mumbai',     '+91-9000000003'],
    ['donor',     'Ramesh','Kumar',    'ramesh@example.com',     'Pune',       '+91-9000000004'],
    ['donor',     'Priya', 'Verma',    'priya@example.com',      'Mumbai',     '+91-9000000005'],
    ['donor',     'Suresh','Patil',    'suresh@example.com',     'Nashik',     '+91-9000000006'],
    ['staff',     'Kavita','Joshi',    'staff@centralbb.org',    'Mumbai',     '+91-9000000007'],
    ['patient',   'Anil',  'Mehta',    'patient@example.com',    'Delhi',      '+91-9000000008'],
];
$userId = [];
$userModel = new User($pdo);
foreach ($users as $u) {
    $userId[$u[3]] = $userModel->create([
        'role'       => $u[0],
        'first_name' => $u[1],
        'last_name'  => $u[2],
        'email'      => strtolower($u[3]),
        'password'   => 'Password123',
        'phone'      => $u[5],
        'city'       => $u[4],
        'is_active'  => 1,
    ]);
}
fwrite(STDERR, "[ok] " . count($users) . " users\n");

/* --- Blood banks ---------------------------------------------------- */
$banks = [
    ['Central Mumbai Blood Bank', 'MG Road, Mumbai',     'Mumbai',   '022-12345678', 'admin@centralbb.org', 19.0760, 72.8777],
    ['North Delhi Blood Center',  'Civil Lines, Delhi',   'Delhi',    '011-22223333', 'north@delhibb.org',   28.7041, 77.1025],
    ['Bangalore Lifeline',        'Indiranagar, BLR',    'Bengaluru', '080-44445555', 'blr@lifeline.org',   12.9716, 77.5946],
];
$bankId = [];
$insBank = $pdo->prepare(
    "INSERT INTO `blood_banks` (name, address, city, phone, email, latitude, longitude, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
foreach ($banks as $b) {
    $insBank->execute([$b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $now]);
    $bankId[$b[2]] = (int) $pdo->lastInsertId();
}
fwrite(STDERR, "[ok] " . count($banks) . " blood banks\n");

/* --- Storage areas ------------------------------------------------- */
$insArea = $pdo->prepare(
    "INSERT INTO `storage_areas` (blood_bank_id, name, capacity, current_occupancy, current_temperature, created_at)
     VALUES (?, ?, ?, 0, ?, ?)"
);
$areaNames = ['Refrigerator A', 'Refrigerator B', 'Freezer 1', 'Freezer 2', 'Cold Room North', 'Cold Room South', 'Plasma Storage', 'Platelet Incubator'];
foreach ($banks as $b) {
    foreach ($areaNames as $i => $n) {
        $temp = $i < 4 ? 4.0 : ($i < 6 ? -25.0 : 22.0);
        $cap  = 80;
        $insArea->execute([$bankId[$b[2]], $n, $cap, $temp, $now]);
    }
}
fwrite(STDERR, "[ok] " . (count($banks) * count($areaNames)) . " storage areas\n");

/* --- Donors -------------------------------------------------------- */
$donors = [
    ['ramesh@example.com', 'A+', 32, 72, 'male',   '2025-12-01'],
    ['priya@example.com',  'O-', 28, 60, 'female', '2025-08-15'],
    ['suresh@example.com', 'B+', 41, 78, 'male',   null],
];
$insDonor = $pdo->prepare(
    "INSERT INTO `donors` (user_id, blood_group, age, weight, gender, last_donation_date, eligibility_status, next_eligible_date, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'eligible', NULL, ?)"
);
foreach ($donors as $d) {
    $insDonor->execute([
        $userId[$d[0]], $d[1], $d[2], $d[3], $d[4], $d[5], $now
    ]);
}
fwrite(STDERR, "[ok] " . count($donors) . " donors\n");

/* --- Blood units --------------------------------------------------- */
$barcodeSeed = function (int $i): string {
    return 'BB' . date('Ymd') . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
};
$unitsPerGroup = 6;   // 8 groups × 6 = 48 units
$unitCount = 0;
$insUnit = $pdo->prepare(
    "INSERT INTO `blood_units`
     (barcode, donor_id, blood_bank_id, blood_group, collection_date, expiry_date, status, storage_location, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'available', ?, ?)"
);
foreach (BLOOD_GROUPS as $group) {
    for ($i = 0; $i < $unitsPerGroup; $i++) {
        $barcode = $barcodeSeed(++$unitCount);
        $bank    = $bankId[array_keys($bankId)[$unitCount % count($bankId)]];
        $coll    = date('Y-m-d', strtotime('-' . rand(2, 20) . ' days'));
        $exp     = date('Y-m-d', strtotime($coll . ' +42 days'));
        $area    = $areaNames[$unitCount % count($areaNames)];
        $insUnit->execute([$barcode, null, $bank, $group, $coll, $exp, $area, $now]);
    }
}
fwrite(STDERR, "[ok] $unitCount blood units\n");

/* --- Initial notification ----------------------------------------- */
$insNotif = $pdo->prepare(
    "INSERT INTO `notifications` (user_id, type, message, channel, delivery_status, created_at)
     VALUES (?, ?, ?, 'in_app', 'sent', ?)"
);
$insNotif->execute([$userId['admin@centralbb.org'], 'system', 'Welcome to Central Mumbai Blood Bank!', $now]);
fwrite(STDERR, "[ok] 1 welcome notification\n");

fwrite(STDERR, "\nDone.  Login at /index.php?route=login with any of:\n");
fwrite(STDERR, "  super_admin@example.com  / super_admin\n");
fwrite(STDERR, "  admin@centralbb.org      / blood_bank_admin\n");
fwrite(STDERR, "  city@metrohosp.org       / hospital\n");
fwrite(STDERR, "  ramesh@example.com       / donor\n");
fwrite(STDERR, "Password (all accounts): Password123\n");
