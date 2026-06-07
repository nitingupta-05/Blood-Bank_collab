<?php
/**
 * Web routes — page requests (GET / POST through the same index.php).
 *
 * Each entry is a [method, pattern, handler] tuple.
 * Patterns use the :param syntax; pattern is matched against the full route key.
 * The handler is a closure that receives the matched $params and is expected to
 * either echo a view, redirect, or terminate the request.
 *
 * Routes are processed in order.  Put specific routes before generic ones.
 */

return [
    // ---------- public pages ----------
    ['GET',  'home',                  fn() => view('home')],
    ['GET',  'login',                 fn() => view('auth/login')],
    ['GET',  'forgot-password',        fn() => view('auth/forgot-password')],
    ['GET',  'reset-password',         fn() => view('auth/reset-password')],
    ['GET',  'register',               fn() => view('auth/register')],
    ['GET',  'register-blood-bank',    fn() => view('register-blood-bank')],
    ['GET',  'find-blood',             fn() => view('find-blood')],
    ['GET',  'book-blood',             fn() => view('book-blood')],
    ['GET',  'emergency-request',      fn() => view('emergency-request')],
    ['GET',  'emergency-requests',     fn() => view('emergency-feed')],

    // ---------- auth + registration POSTs ----------
    ['POST', 'login',                 [AuthController::class, 'login']],
    ['POST', 'register',              [AuthController::class, 'registerDonor']],
    ['POST', 'forgot-password',        [AuthController::class, 'forgotPassword']],
    ['POST', 'reset-password',         [AuthController::class, 'resetPassword']],
    ['GET',  'logout',                 [AuthController::class, 'logout']],

    // ---------- admin / hospital pages (auth required) ----------
    ['GET',  'dashboard',  [PageController::class, 'dashboard'],  ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['GET',  'inventory',  [PageController::class, 'inventory'],  ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['GET',  'donors',     [PageController::class, 'donors'],     ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['GET',  'storage',    [PageController::class, 'storage'],    ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['GET',  'emergency',  [PageController::class, 'emergency'],  ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['GET',  'bookings',   [PageController::class, 'bookings'],   ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['GET',  'reports',    [PageController::class, 'reports'],    ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['GET',  'settings',   [PageController::class, 'settings'],   ['roles' => array_merge(admin_roles(), ['hospital'])]],

    // 404 catch-all
    ['GET',  '404',        fn() => view('404')],
];

