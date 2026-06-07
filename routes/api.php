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
 * CSRF note:  all non-public write routes set csrf=false.  The session cookie
 * uses SameSite=Strict, which is sufficient CSRF protection for same-site
 * authenticated requests.  Public POSTs (login/registration, public booking,
 * public emergency) are already exempt from CSRF here.
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

    // ---- authenticated reads ----
    ['dashboard/stats',             'GET',  [ApiController::class, 'dashboardStats']],
    ['dashboard/blood-stock',       'GET',  [ApiController::class, 'dashboardBloodStock']],
    ['dashboard/monthly-donations', 'GET',  [ApiController::class, 'dashboardMonthly']],
    ['profile/current',             'GET',  [ApiController::class, 'profileCurrent']],
    ['notifications/list',          'GET',  [ApiController::class, 'notificationsList']],
    ['notifications/count',         'GET',  [ApiController::class, 'notificationsCount']],
    ['settings/hospital',           'GET',  [ApiController::class, 'getHospitalDetails']],
    ['settings/notifications',      'GET',  [ApiController::class, 'getNotificationPrefs']],
    ['emergency/stats',             'GET',  [ApiController::class, 'emergencyStats']],
    ['bookings/stats',              'GET',  [ApiController::class, 'bookingStats']],
    ['bookings/list',               'GET',  [ApiController::class, 'bookingsList']],
    ['donors/list',                 'GET',  [ApiController::class, 'donorsList']],
    ['donors/stats',                'GET',  [ApiController::class, 'donorStats']],
    ['storage/list',                'GET',  [ApiController::class, 'storageList']],
    ['storage/stats',               'GET',  [ApiController::class, 'storageStats']],
    ['blood-units/list',            'GET',  [ApiController::class, 'bloodUnitsList']],
    ['reports/blood-stock',         'GET',  [ApiController::class, 'reportsBloodStock']],
    ['reports/ai-insights',         'GET',  [ApiController::class, 'reportsAiInsights']],
    ['reports/preview',             'GET',  [ApiController::class, 'reportsPreview']],
    ['search/global',               'GET',  [ApiController::class, 'globalSearch']],

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
