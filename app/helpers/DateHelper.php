<?php
/**
 * Date / time helpers.
 */
class DateHelper {

    public static function now(): string       { return date('Y-m-d H:i:s'); }
    public static function today(): string     { return date('Y-m-d'); }

    public static function addDays(string $date, int $days): string {
        return date('Y-m-d', strtotime($date . ' +' . (int) $days . ' days'));
    }

    public static function subtractDays(string $date, int $days): string {
        return date('Y-m-d', strtotime($date . ' -' . (int) $days . ' days'));
    }

    public static function daysBetween(string $a, string $b): int {
        $d1 = new DateTime($a);
        $d2 = new DateTime($b);
        return (int) $d1->diff($d2)->format('%r%a');
    }

    public static function isExpired(string $expiry): bool {
        return strtotime($expiry) < strtotime(self::today());
    }

    public static function isExpiringWithin(string $expiry, int $days = EXPIRY_WARNING_DAYS): bool {
        $expiryTs = strtotime($expiry);
        $warnTs   = strtotime('+' . (int) $days . ' days');
        return $expiryTs <= $warnTs && $expiryTs >= strtotime(self::today());
    }

    public static function formatDate(string $date, string $format = 'd M Y'): string {
        return $date ? date($format, strtotime($date)) : '-';
    }

    public static function formatDateTime(?string $dt, string $format = 'd M Y H:i'): string {
        return $dt ? date($format, strtotime($dt)) : '-';
    }

    public static function getNextEligibleDate(string $lastDonationDate): string {
        return self::addDays($lastDonationDate, DONATION_INTERVAL);
    }
}
