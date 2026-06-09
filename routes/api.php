<?php
/**
 * API routes — JSON endpoints (called by /api.php?route=...).
 *
 * Each entry: [endpoint, method, handler, options]
 *   options may contain:
 *     'roles'    => array of allowed roles
 *     'public'   => true to skip auth check
 *     'csrf'     => false to skip CSRF for this route (default: true for non-public writes)
 *     'auth'     => 'optional' to attempt auth but not require it (hospital-fallback writes)
 *
 * CSRF note: public POSTs and web-form-backed writes require X-CSRF-TOKEN.
 * Dashboard API writes are same-site authenticated requests and currently opt
 * out route-by-route with csrf=false.
 */

return [
    // ---- public (no auth, no CSRF) ----
    ['public/stats',                'GET',  [ApiController::class, 'publicStats'],         ['public' => true]],
    ['public/blood-stock',          'GET',  [ApiController::class, 'publicBloodStock'],    ['public' => true]],
    ['public/blood-banks',          'GET',  [ApiController::class, 'publicBloodBanks'],    ['public' => true]],
    ['blood-search',                'GET',  [ApiController::class, 'bloodSearch'],         ['public' => true]],
    ['emergency/feed',              'GET',  [ApiController::class, 'publicEmergencyFeed'],  ['public' => true]],
    ['public/booking',              'POST', [ApiController::class, 'publicBooking'],       ['public' => true, 'auth' => 'optional']],
    ['public/emergency',            'POST', [ApiController::class, 'publicEmergency'],     ['public' => true, 'auth' => 'optional']],
    ['emergency/create',            'POST', [ApiController::class, 'publicEmergency'],     ['public' => true, 'auth' => 'optional']],
    ['blood-banks/register',        'POST', [ApiController::class, 'registerBloodBank'],   ['public' => true]],

    // ---- authenticated reads (admin + hospital) ----
    ['dashboard/stats',             'GET',  [ApiController::class, 'dashboardStats'],            ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['dashboard/blood-stock',       'GET',  [ApiController::class, 'dashboardBloodStock'],       ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['dashboard/monthly-donations', 'GET',  [ApiController::class, 'dashboardMonthly'],          ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['emergency/stats',             'GET',  [ApiController::class, 'emergencyStats'],            ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['bookings/stats',              'GET',  [ApiController::class, 'bookingStats'],              ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['bookings/list',               'GET',  [ApiController::class, 'bookingsList'],              ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['donors/list',                 'GET',  [ApiController::class, 'donorsList'],                ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['donors/stats',                'GET',  [ApiController::class, 'donorStats'],                ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['storage/list',                'GET',  [ApiController::class, 'storageList'],               ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['storage/stats',               'GET',  [ApiController::class, 'storageStats'],              ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['blood-units/list',            'GET',  [ApiController::class, 'bloodUnitsList'],            ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['reports/blood-stock',         'GET',  [ApiController::class, 'reportsBloodStock'],         ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['reports/ai-insights',         'GET',  [ApiController::class, 'reportsAiInsights'],         ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['reports/preview',             'GET',  [ApiController::class, 'reportsPreview'],            ['roles' => array_merge(admin_roles(), ['hospital'])]],
    ['search/global',               'GET',  [ApiController::class, 'globalSearch'],              ['roles' => array_merge(admin_roles(), ['hospital'])]],
    // ---- authenticated reads (any authenticated user, scoped by user_id) ----
    ['profile/current',             'GET',  [ApiController::class, 'profileCurrent']],
    ['notifications/list',          'GET',  [ApiController::class, 'notificationsList']],
    ['notifications/count',         'GET',  [ApiController::class, 'notificationsCount']],
    ['settings/hospital',           'GET',  [ApiController::class, 'getHospitalDetails']],
    ['settings/notifications',      'GET',  [ApiController::class, 'getNotificationPrefs']],

    // ---- admin-only reads ----
    ['settings/system-stats',       'GET',  [ApiController::class, 'systemStats'],         ['roles' => admin_roles()]],
    ['reports/export-csv',          'GET',  [ApiController::class, 'reportsExportCsv'],    ['roles' => admin_roles()]],

    // ---- authenticated writes (session-cookie based CSRF protection via SameSite=Strict) ----
    ['blood-units/add',             'POST', [ApiController::class, 'bloodUnitsAdd'],       ['roles' => admin_roles(), 'csrf' => false]],
    ['blood-units/update-status',   'POST', [ApiController::class, 'bloodUnitsUpdateStatus'], ['roles' => admin_roles(), 'csrf' => false]],
    ['blood-units/delete',          'POST', [ApiController::class, 'bloodUnitsDelete'],    ['roles' => admin_roles(), 'csrf' => false]],

    ['donors/add',                  'POST', [ApiController::class, 'donorsAdd'],           ['roles' => admin_roles(), 'csrf' => false]],

    ['storage/add',                 'POST', [ApiController::class, 'storageAdd'],          ['roles' => admin_roles(), 'csrf' => false]],
    ['storage/log-temperature',     'POST', [ApiController::class, 'storageLogTemp'],      ['roles' => admin_roles(), 'csrf' => false]],

    ['bookings/:id/approve',        'POST', [ApiController::class, 'bookingApprove'],      ['roles' => admin_roles(), 'csrf' => false]],
    ['bookings/:id/reject',         'POST', [ApiController::class, 'bookingReject'],       ['roles' => admin_roles(), 'csrf' => false]],

    ['emergency/:id/fulfill',       'POST', [ApiController::class, 'emergencyFulfill'],    ['roles' => admin_roles(), 'csrf' => false]],

    ['settings/hospital',           'POST', [ApiController::class, 'updateHospitalDetails'], ['csrf' => false]],
    ['settings/notifications',      'POST', [ApiController::class, 'updateNotificationPrefs'], ['csrf' => false]],

    ['notifications/mark-read',     'POST', [ApiController::class, 'notificationsMarkRead'], ['csrf' => false]],
    ['notifications/send',          'POST', [ApiController::class, 'notificationsSend'],   ['roles' => admin_roles(), 'csrf' => false]],
];
