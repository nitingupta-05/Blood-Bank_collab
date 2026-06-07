<?php
/**
 * Demo data adder — non-destructive.  Adds the following on top of
 * the existing seed (seed-data.php) so the dashboard feels populated:
 *
 *   - 2 blood banks (Pune, Hyderabad) with admin users
 *   - 2 more staff users (one per new bank)
 *   - 4 more donor users + donor records
 *   - 6 more storage areas (3 per new bank)
 *   - 60 more blood units (12 per new bank)
 *   - 3 pending emergency requests
 *   - 2 pending bookings
 *   - 2 temperature logs
 *
 * Idempotency: stops early if the new banks already exist (so re-running
 * won't double-insert).  CLI only.
 *
 *   & "C:\xampp\php\php.exe" public/add-demo-data.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require __DIR__ . '/../bootstrap/app.php';

$now = date('Y-m-d H:i:s');

/* --- Bail if already run ------------------------------------------- */
$existing = (int) $pdo->query("SELECT COUNT(*) FROM `blood_banks` WHERE email IN ('admin@punebb.org', 'admin@hydbb.org')")->fetchColumn();
if ($existing > 0) {
    fwrite(STDERR, "[skip] demo data already added (found $existing new banks).  Wipe the DB and re-seed first.\n");
    exit(0);
}

/* --- New blood_bank_admin users ----------------------------------- */
$userModel = new User($pdo);
$newUserIds = [];

$newUserIds['admin@punebb.org'] = $userModel->create([
    'role' => 'blood_bank_admin', 'first_name' => 'Rohit', 'last_name' => 'Deshmukh',
    'email' => 'admin@punebb.org',  'password' => 'Password123',
    'phone' => '+91-9000000010', 'city' => 'Pune', 'is_active' => 1,
]);
$newUserIds['admin@hydbb.org'] = $userModel->create([
    'role' => 'blood_bank_admin', 'first_name' => 'Ananya', 'last_name' => 'Reddy',
    'email' => 'admin@hydbb.org',  'password' => 'Password123',
    'phone' => '+91-9000000011', 'city' => 'Hyderabad', 'is_active' => 1,
]);

/* --- 2 more staff users ------------------------------------------- */
$newUserIds['staff2@centralbb.org'] = $userModel->create([
    'role' => 'staff', 'first_name' => 'Deepa', 'last_name' => 'Kulkarni',
    'email' => 'staff2@centralbb.org', 'password' => 'Password123',
    'phone' => '+91-9000000012', 'city' => 'Mumbai', 'is_active' => 1,
]);
$newUserIds['staff@punebb.org'] = $userModel->create([
    'role' => 'staff', 'first_name' => 'Sanjay', 'last_name' => 'Jadhav',
    'email' => 'staff@punebb.org', 'password' => 'Password123',
    'phone' => '+91-9000000013', 'city' => 'Pune', 'is_active' => 1,
]);

/* --- 4 more donor users ------------------------------------------- */
$donorSpecs = [
    ['vijay@example.com',  'Vijay',  'Iyer',     'Chennai',  '+91-9000000020', 'O+', 35, 75, 'male',   null],
    ['anjali@example.com', 'Anjali', 'Kapoor',   'Mumbai',   '+91-9000000021', 'B-', 26, 58, 'female', '2025-10-10'],
    ['karthik@example.com','Karthik','Nair',     'Bengaluru','+91-9000000022', 'AB+', 45, 80, 'male',   '2025-09-05'],
    ['meera@example.com',  'Meera',  'Saxena',   'Delhi',    '+91-9000000023', 'A-', 31, 63, 'female', '2025-11-20'],
];
foreach ($donorSpecs as $d) {
    [$email, $fn, $ln, $city, $phone, $bg, $age, $weight, $gender, $lastDonation] = $d;
    $newUserIds[$email] = $userModel->create([
        'role' => 'donor', 'first_name' => $fn, 'last_name' => $ln,
        'email' => $email, 'password' => 'Password123',
        'phone' => $phone, 'city' => $city, 'is_active' => 1,
    ]);
}
fwrite(STDERR, "[ok] " . (count($newUserIds)) . " new users (2 admins, 2 staff, 4 donors)\n");

/* --- New blood banks ---------------------------------------------- */
$insBank = $pdo->prepare(
    "INSERT INTO `blood_banks` (name, address, city, phone, email, latitude, longitude, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$insBank->execute(['Pune Regional Blood Bank', 'FC Road, Pune',          'Pune',      '020-11112222', 'admin@punebb.org', 18.5204, 73.8567, $now]);
$puneBankId = (int) $pdo->lastInsertId();
$insBank->execute(['Hyderabad Life Savers',     'Banjara Hills, Hyderabad','Hyderabad', '040-33334444', 'admin@hydbb.org', 17.3850, 78.4867, $now]);
$hydBankId  = (int) $pdo->lastInsertId();

/* Existing 3 bank ids (for spreading blood units). */
$existingBankIds = $pdo->query("SELECT id FROM `blood_banks` ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$existingBankIds = array_map('intval', $existingBankIds);
$allBankIds      = $existingBankIds;   // [mumbai, delhi, blr, pune, hyd]
$newBankIds      = [$puneBankId, $hydBankId];
fwrite(STDERR, "[ok] 2 new blood banks (Pune=#$puneBankId, Hyderabad=#$hydBankId)\n");

/* --- Storage areas for the 2 new banks ---------------------------- */
$insArea = $pdo->prepare(
    "INSERT INTO `storage_areas` (blood_bank_id, name, capacity, current_occupancy, current_temperature, created_at)
     VALUES (?, ?, ?, 0, ?, ?)"
);
$areaNamesNew = ['Refrigerator A', 'Freezer 1', 'Plasma Storage'];
$newAreaIds = [];
foreach ($newBankIds as $bid) {
    foreach ($areaNamesNew as $i => $n) {
        $temp = $i === 0 ? 4.0 : ($i === 1 ? -25.0 : 22.0);
        $insArea->execute([$bid, $n, 60, $temp, $now]);
        $newAreaIds[] = (int) $pdo->lastInsertId();
    }
}
fwrite(STDERR, "[ok] " . count($newAreaIds) . " new storage areas\n");

/* --- 4 more donor records ----------------------------------------- */
$insDonor = $pdo->prepare(
    "INSERT INTO `donors` (user_id, blood_group, age, weight, gender, last_donation_date, eligibility_status, next_eligible_date, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'eligible', NULL, ?)"
);
$donorRecords = [
    ['vijay@example.com',   'O+',  35, 75, 'male',   null],
    ['anjali@example.com',  'B-',  26, 58, 'female', '2025-10-10'],
    ['karthik@example.com', 'AB+', 45, 80, 'male',   '2025-09-05'],
    ['meera@example.com',   'A-',  31, 63, 'female', '2025-11-20'],
];
foreach ($donorRecords as $d) {
    [$email, $bg, $age, $weight, $gender, $lastDonation] = $d;
    $insDonor->execute([
        $newUserIds[$email], $bg, $age, $weight, $gender, $lastDonation, $now,
    ]);
}
fwrite(STDERR, "[ok] " . count($donorRecords) . " new donor records\n");

/* --- 60 more blood units across the 5 banks ----------------------- */
$insUnit = $pdo->prepare(
    "INSERT INTO `blood_units`
     (barcode, donor_id, blood_bank_id, blood_group, collection_date, expiry_date, status, storage_location, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'available', ?, ?)"
);
$barcodeSeed = function (int $i): string {
    return 'BB' . date('Ymd', strtotime('+1 day')) . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
};
$areaNames = ['Refrigerator A', 'Refrigerator B', 'Freezer 1', 'Freezer 2', 'Cold Room North', 'Cold Room South', 'Plasma Storage', 'Platelet Incubator'];

$unitCount = 0;
foreach (BLOOD_GROUPS as $group) {
    /* 1.5 units per group per bank → 1.5 × 5 banks × 8 groups = 60 units. */
    foreach ($allBankIds as $bid) {
        for ($i = 0; $i < 2; $i++) {
            $unitCount++;
            $barcode = $barcodeSeed(9000 + $unitCount);
            $coll    = date('Y-m-d', strtotime('-' . rand(2, 25) . ' days'));
            $exp     = date('Y-m-d', strtotime($coll . ' +42 days'));
            $area    = $areaNames[($unitCount) % count($areaNames)];
            $insUnit->execute([$barcode, null, $bid, $group, $coll, $exp, $area, $now]);
        }
    }
    /* Add a third unit for the 2 new banks to reach 1.5 avg. */
    foreach ($newBankIds as $bid) {
        $unitCount++;
        $barcode = $barcodeSeed(9000 + $unitCount);
        $coll    = date('Y-m-d', strtotime('-' . rand(2, 25) . ' days'));
        $exp     = date('Y-m-d', strtotime($coll . ' +42 days'));
        $area    = $areaNames[($unitCount) % count($areaNames)];
        $insUnit->execute([$barcode, null, $bid, $group, $coll, $exp, $area, $now]);
    }
}
fwrite(STDERR, "[ok] $unitCount new blood units\n");

/* --- 3 pending emergency requests --------------------------------- */
$hospitalId = (int) $pdo->query("SELECT id FROM `users` WHERE email = 'city@metrohosp.org'")->fetchColumn();
$ttl = ['critical' => '+24 hours', 'moderate' => '+36 hours', 'low' => '+48 hours'];
$emergencies = [
    ['O-',  4, 'critical', 'Lilavati Hospital, Bandra',    'Rakesh Sharma',  'Severe accident, multiple transfusions needed', '+91-9100000001'],
    ['AB+', 2, 'moderate', 'Apollo Hospital, Hyderabad',   'Lakshmi Devi',   'Scheduled surgery tomorrow',                    '+91-9100000002'],
    ['B+',  1, 'low',      'Ruby Hall Clinic, Pune',       'Aditya Joshi',   'Postpartum complication',                       '+91-9100000003'],
];
$insEmerg = $pdo->prepare(
    "INSERT INTO `emergency_requests`
     (blood_group, quantity, urgency_level, hospital_id, location, patient_name, patient_details, contact_phone, status, expires_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?)"
);
foreach ($emergencies as $e) {
    [$bg, $qty, $urg, $loc, $patient, $details, $phone] = $e;
    $exp = date('Y-m-d H:i:s', strtotime($ttl[$urg]));
    $insEmerg->execute([$bg, $qty, $urg, $hospitalId, $loc, $patient, $details, $phone, $exp, $now, $now]);
}
fwrite(STDERR, "[ok] " . count($emergencies) . " pending emergency requests\n");

/* --- 2 pending bookings ------------------------------------------- */
$insBook = $pdo->prepare(
    "INSERT INTO `blood_bookings`
     (hospital_id, blood_group, quantity, required_date, patient_name, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)"
);
$bookings = [
    ['A+',  3, date('Y-m-d', strtotime('+5 days')), 'Geeta Krishnan', 'Knee replacement surgery scheduled'],
    ['O+',  5, date('Y-m-d', strtotime('+10 days')), 'Mohan Lal',      'Cardiac procedure — need 5 units reserve'],
];
foreach ($bookings as $b) {
    [$bg, $qty, $reqDate, $patient, $notes] = $b;
    $insBook->execute([$hospitalId, $bg, $qty, $reqDate, $patient, $notes, $now, $now]);
}
fwrite(STDERR, "[ok] " . count($bookings) . " pending bookings\n");

/* --- 2 temperature logs (for the 2 new areas) --------------------- */
$insTemp = $pdo->prepare(
    "INSERT INTO `temperature_logs` (storage_area_id, temperature, recorded_at)
     VALUES (?, ?, ?)"
);
$insTemp->execute([$newAreaIds[0], 4.2, $now]);
$insTemp->execute([$newAreaIds[3], -24.7, $now]);
fwrite(STDERR, "[ok] 2 temperature logs\n");

/* --- A couple of extra welcome notifications --------------------- */
$insNotif = $pdo->prepare(
    "INSERT INTO `notifications` (user_id, type, message, channel, delivery_status, created_at)
     VALUES (?, ?, ?, 'in_app', 'sent', ?)"
);
$insNotif->execute([$newUserIds['admin@punebb.org'], 'system', 'Welcome to Pune Regional Blood Bank!', $now]);
$insNotif->execute([$newUserIds['admin@hydbb.org'],  'system', 'Welcome to Hyderabad Life Savers!',     $now]);
fwrite(STDERR, "[ok] 2 welcome notifications\n\n");

fwrite(STDERR, "Demo data added.  New login accounts (password: Password123):\n");
fwrite(STDERR, "  admin@punebb.org        / blood_bank_admin  (Pune)\n");
fwrite(STDERR, "  admin@hydbb.org         / blood_bank_admin  (Hyderabad)\n");
fwrite(STDERR, "  staff2@centralbb.org    / staff             (Mumbai)\n");
fwrite(STDERR, "  staff@punebb.org        / staff             (Pune)\n");
fwrite(STDERR, "  vijay@example.com       / donor             (Chennai)\n");
fwrite(STDERR, "  anjali@example.com      / donor             (Mumbai)\n");
fwrite(STDERR, "  karthik@example.com     / donor             (Bengaluru)\n");
fwrite(STDERR, "  meera@example.com       / donor             (Delhi)\n");
