<?php
/**
 * Application configuration.
 * Edit the values below or override via environment variables.
 */

define('APP_NAME',  getenv('APP_NAME')  ?: 'RedPulse Blood Bank');
define('APP_VERSION', '1.0.0');
define('APP_URL',   getenv('APP_URL')   ?: 'http://localhost/blood-bank-system/public');
define('APP_URL_SCHEME', parse_url(APP_URL, PHP_URL_SCHEME) ?: 'http');
define('BASE_PATH', '/blood-bank-system/public');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// Session
define('SESSION_TIMEOUT', 1800);              // 30 minutes
define('SESSION_LIFETIME', 0);                // 0 = until browser close
define('SESSION_NAME',    'blood_bank_session');

// Auth hardening
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// Business rules
define('BLOOD_GROUPS', ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']);
define('ROLES', [
    'super_admin'      => 'Super Admin',
    'blood_bank_admin' => 'Blood Bank Admin',
    'staff'            => 'Staff',
    'donor'            => 'Donor',
    'patient'          => 'Patient',
    'hospital'         => 'Hospital',
]);
define('BLOOD_UNIT_STATUS', ['available', 'reserved', 'used', 'expired', 'discarded']);
define('BOOKING_STATUS',   ['pending', 'approved', 'rejected', 'completed', 'cancelled']);
define('EMERGENCY_STATUS', ['active', 'fulfilled', 'cancelled', 'expired']);
define('URGENCY_LEVELS',   ['critical', 'moderate', 'low']);
define('NOTIFICATION_TYPES',   ['low_stock', 'expiry', 'emergency', 'booking', 'temperature', 'eligibility', 'system']);
define('NOTIFICATION_CHANNELS', ['sms', 'email', 'in_app']);

// Eligibility
define('MIN_DONOR_AGE', 18);
define('MAX_DONOR_AGE', 65);
define('MIN_DONOR_WEIGHT', 50);
define('DONATION_INTERVAL', 56);              // days between donations
define('BLOOD_EXPIRY_DAYS', 42);
define('EXPIRY_WARNING_DAYS', 7);
define('LOW_STOCK_THRESHOLD', 10);
define('STORAGE_TEMP_MIN', 2);
define('STORAGE_TEMP_MAX', 6);
define('EMERGENCY_RADIUS', 15);
define('EMERGENCY_ESCALATION_TIME', 7200);
define('BOOKING_HOLD_TIME', 86400);

// Urgent-request TTL (hours) by urgency
define('EMERGENCY_TTL_HOURS', [
    'critical' => 24,
    'moderate' => 36,
    'low'      => 48,
]);

// Pagination
define('ITEMS_PER_PAGE', 20);

// File uploads
define('MAX_UPLOAD_SIZE',    5 * 1024 * 1024);   // 5 MB
define('ALLOWED_UPLOAD_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
define('UPLOAD_DIR', __DIR__ . '/../public/uploads/');

// Storage
define('REPORT_DIR', __DIR__ . '/../storage/reports/');
define('LOG_DIR',    __DIR__ . '/../storage/logs/');
define('CACHE_DIR',  __DIR__ . '/../storage/cache/');

// AI insights (Gemini)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL',   'gemini-1.5-flash');

// Security headers
function send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=()');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
