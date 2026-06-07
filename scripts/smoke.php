<?php
/**
 * Smoke test for the public API + all web views.
 *
 * Phase 1 (no auth): exercises public API endpoints, all public pages,
 *                    and all auth pages (login, register, etc.).
 * Phase 2 (auth):     logs in as a super_admin and hits every dashboard
 *                    page, then runs an authenticated API call.
 *
 * Exit code 0 = all pass, 1 = any failure.
 */

$base = 'http://localhost/blood-bank-system/public';
$cookieJar = __DIR__ . '/_cookies.txt';
@unlink($cookieJar);

function hit(string $url, array $opts = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => __DIR__ . '/_cookies.txt',
        CURLOPT_COOKIEFILE     => __DIR__ . '/_cookies.txt',
        CURLOPT_HEADER         => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'json' => json_decode((string) $body, true)];
}

function checkPage(string $url, string $expectedSubstr): string {
    $r = hit($url);
    if ($r['code'] !== 200) {
        return "FAIL: expected 200, got {$r['code']} for $url";
    }
    if (stripos($r['body'], $expectedSubstr) === false) {
        return "FAIL: '$expectedSubstr' not found in $url (len=" . strlen($r['body']) . ")";
    }
    return 'OK';
}

$tests = [];

/* ---------- Phase 1a: public API endpoints ---------- */
$tests[] = fn() => (($r = hit("$base/api.php?route=public/stats"))['json']['ok'] ?? false)
    ? "public/stats OK (units=" . ($r['json']['stats']['units'] ?? '?') . ")"
    : "public/stats FAIL: {$r['body']}";

$tests[] = fn() => (($r = hit("$base/api.php?route=public/blood-stock"))['json']['ok'] ?? false) && count($r['json']['labels'] ?? []) === 8
    ? "public/blood-stock OK (8 groups)"
    : "public/blood-stock FAIL: {$r['body']}";

$tests[] = fn() => (($r = hit("$base/api.php?route=blood-search&blood_group=A%2B&city=Mumbai&units_required=1"))['json']['ok'] ?? false)
    ? "blood-search OK (banks=" . count($r['json']['banks'] ?? []) . ", donors=" . count($r['json']['donors'] ?? []) . ")"
    : "blood-search FAIL: {$r['body']}";

$tests[] = fn() => (($r = hit("$base/api.php?route=public/blood-banks"))['json']['ok'] ?? false)
    ? "public/blood-banks OK (n=" . count($r['json']['items'] ?? []) . ")"
    : "public/blood-banks FAIL: {$r['body']}";

$tests[] = fn() => (($r = hit("$base/api.php?route=emergency/feed"))['json']['ok'] ?? false)
    ? "emergency/feed OK"
    : "emergency/feed FAIL: {$r['body']}";

/* ---------- Phase 1b: public write endpoints ---------- */
$tests[] = function () use ($base) {
    $body = json_encode([
        'blood_group'   => 'O+',
        'quantity'      => 1,
        'required_date' => date('Y-m-d', strtotime('+3 days')),
        'patient_name'  => 'Smoke Test',
    ]);
    $r = hit("$base/api.php?route=public/booking", [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    return ($r['json']['ok'] ?? false) ? "public/booking OK (id={$r['json']['id']})" : "public/booking FAIL: {$r['body']}";
};

$tests[] = function () use ($base) {
    $body = json_encode([
        'blood_group'   => 'O+',
        'quantity'      => 1,
        'urgency_level' => 'critical',
        'location'      => 'Mumbai',
        'patient_name'  => 'Smoke Test',
        'contact_phone' => '+91-9000000099',
    ]);
    $r = hit("$base/api.php?route=public/emergency", [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    return ($r['json']['ok'] ?? false) ? "public/emergency OK (id={$r['json']['id']})" : "public/emergency FAIL: {$r['body']}";
};

$tests[] = function () use ($base) {
    $body = json_encode([
        'name'     => 'Smoke Test Bank',
        'email'    => 'smoke' . time() . '@example.com',
        'phone'    => '+91-9000000098',
        'address'  => '123 Test Street',
        'city'     => 'Testville',
        'password' => 'Password123',
    ]);
    $r = hit("$base/api.php?route=blood-banks/register", [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    return ($r['json']['ok'] ?? false) ? "blood-banks/register OK (bank={$r['json']['blood_bank_id']})" : "blood-banks/register FAIL: {$r['body']}";
};

/* ---------- Phase 1c: public + auth pages (no auth required) ---------- */
$publicPages = [
    'home'             => 'Blood Bank',
    'find-blood'       => 'Find Blood',
    'book-blood'       => 'Book',
    'emergency-request'=> 'Emergency',
    'emergency-requests' => 'Emergency',
    'register-blood-bank' => 'Register',
    'login'            => 'Login',
    'register'         => 'Donor',
    'forgot-password'  => 'password',
    'reset-password'   => 'Reset',
];
foreach ($publicPages as $route => $needle) {
    $tests[] = function () use ($base, $route, $needle) {
        $r = checkPage("$base/index.php?route=$route", $needle);
        return ['page ' . $route, $r === 'OK', $r === 'OK' ? 'page ' . $route . ' OK' : 'page ' . $route . ': ' . $r];
    };
}

/* ---------- Phase 1d: dashboard pages should redirect (302) when unauthenticated ---------- */
$protectedPages = ['dashboard', 'inventory', 'donors', 'storage', 'emergency', 'bookings', 'reports', 'settings'];
foreach ($protectedPages as $route) {
    $tests[] = function () use ($base, $route) {
        $r = hit("$base/index.php?route=$route");
        $ok = ($r['code'] === 302 || $r['code'] === 303);
        return [
            'auth-gate ' . $route,
            $ok,
            $ok ? "auth-gate $route OK (redirect when unauthenticated)" : "auth-gate $route FAIL: expected 302/303, got {$r['code']}"
        ];
    };
}

/* ---------- Phase 2: login + protected pages + authenticated API ---------- */
$tests[] = function () use ($base, $protectedPages) {
    $r1 = hit("$base/index.php?route=login");
    if (!preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $r1['body'], $m)) {
        return ['login', false, "login FAIL: no csrf token in form (length=" . strlen($r1['body']) . ")"];
    }
    $token = $m[1];
    $body = http_build_query(['_csrf_token' => $token, 'email' => 'admin@centralbb.org', 'password' => 'Password123']);
    $r2 = hit("$base/index.php?route=login", [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    if ($r2['code'] !== 302 && $r2['code'] !== 303) {
        return ['login', false, "login FAIL: expected redirect, got {$r2['code']}: " . substr($r2['body'], 0, 200)];
    }

    $pageNeedles = [
        'dashboard' => 'Dashboard',
        'inventory' => 'Inventory',
        'donors'    => 'Donor',
        'storage'   => 'Storage',
        'emergency' => 'Emergency',
        'bookings'  => 'Book',
        'reports'   => 'Report',
        'settings'  => 'Settings',
    ];
    $allOk = true;
    $details = [];
    foreach ($protectedPages as $route) {
        $r = hit("$base/index.php?route=$route");
        $needle = $pageNeedles[$route] ?? '404';
        if ($r['code'] !== 200) {
            $allOk = false;
            $details[] = "$route: HTTP {$r['code']}";
            continue;
        }
        if (stripos($r['body'], $needle) === false) {
            $allOk = false;
            $details[] = "$route: '$needle' missing";
        }
    }
    if (!$allOk) {
        return ['authed-pages', false, "authed pages FAIL: " . implode('; ', $details)];
    }

    $r3 = hit("$base/api.php?route=dashboard/stats");
    if (!($r3['json']['ok'] ?? false)) {
        return ['authed-api', false, "auth api FAIL: {$r3['body']}"];
    }
    return ['authed', true, "login + 8 authed pages + dashboard/stats OK (units=" . ($r3['json']['stats']['total_units'] ?? '?') . ")"];
};

/* ---------- Run ---------- */
$ok = 0; $fail = 0;
foreach ($tests as $i => $t) {
    $r = $t();
    if (is_array($r)) {
        [, $passed, $message] = $r;
    } else {
        $passed = str_contains($r, 'OK') && !str_contains($r, 'FAIL');
        $message = $r;
    }
    if ($passed) { $ok++; echo "[PASS] $message\n"; }
    else         { $fail++; echo "[FAIL] $message\n"; }
}
echo "\nsummary: $ok passed, $fail failed\n";

/* Cleanup cookies artifact. */
@unlink($cookieJar);

exit($fail > 0 ? 1 : 0);
