<?php
/**
 * Input validation helpers.
 */

class ValidationHelper {

    public static function email(?string $v): ?string {
        $v = trim((string) $v);
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
    }

    public static function password(?string $v): ?string {
        $v = (string) $v;
        if (strlen($v) < 8) return null;
        if (!preg_match('/[A-Z]/', $v)) return null;
        if (!preg_match('/[a-z]/', $v)) return null;
        if (!preg_match('/[0-9]/', $v)) return null;
        return $v;
    }

    public static function phone(?string $v): ?string {
        $v = trim((string) $v);
        return preg_match('/^[0-9+\-\s()]{10,20}$/', $v) ? $v : null;
    }

    public static function bloodGroup(?string $v): ?string {
        $v = (string) $v;
        return in_array($v, BLOOD_GROUPS, true) ? $v : null;
    }

    public static function age($v): ?int {
        $n = (int) $v;
        if ($n < MIN_DONOR_AGE || $n > MAX_DONOR_AGE) return null;
        return $n;
    }

    public static function weight($v): ?float {
        $f = (float) $v;
        return $f >= MIN_DONOR_WEIGHT ? $f : null;
    }

    public static function positiveInt($v, int $min = 1, int $max = PHP_INT_MAX): ?int {
        $n = filter_var($v, FILTER_VALIDATE_INT);
        if ($n === false || $n < $min || $n > $max) return null;
        return $n;
    }

    public static function nonEmptyString(?string $v, int $max = 255): ?string {
        $v = trim((string) $v);
        if ($v === '' || mb_strlen($v) > $max) return null;
        return $v;
    }

    public static function dateYmd(?string $v): ?string {
        $v = trim((string) $v);
        if (!$v) return null;
        $d = DateTime::createFromFormat('Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) return null;
        return $v;
    }

    public static function inEnum(string $value, array $allowed): bool {
        return in_array($value, $allowed, true);
    }

    public static function sanitizeForOutput(?string $v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    public static function requireFields(array $data, array $required): array {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}
