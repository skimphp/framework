<?php declare(strict_types=1);

namespace skim\helpers;

// Input filter/sanitize helper. All methods return the typed value or false.
// Never returns null — false means "invalid input, discard it".
// Use in controllers before passing data to models or queries.
final class filter {
    /**
     * @ai-contract returns int if $value is numeric, false otherwise
     * @ai-contract returns false if outside [$min, $max] range when set
     */
    public static function int(mixed $value, ?int $min = null, ?int $max = null): int|false {
        if (!is_numeric($value)) {
            return false;
        }
        $v = (int) $value;
        if ((string) $v !== ltrim((string) $value, '+')) {
            return false;   // reject floats like 1.5
        }
        if ($min !== null && $v < $min) {
            return false;
        }
        if ($max !== null && $v > $max) {
            return false;
        }
        return $v;
    }

    /**
     * @ai-contract alias for int() with min:1
     */
    public static function int_positive(mixed $value): int|false {
        return self::int($value, min: 1);
    }

    /**
     * @ai-contract alias for int() with min:0
     */
    public static function int_natural(mixed $value): int|false {
        return self::int($value, min: 0);
    }

    /**
     * @ai-contract returns float if numeric, false otherwise; optional min/max range check
     */
    public static function float(mixed $value, ?float $min = null, ?float $max = null): float|false {
        if (!is_numeric($value)) {
            return false;
        }
        $v = (float) $value;
        if ($min !== null && $v < $min) {
            return false;
        }
        if ($max !== null && $v > $max) {
            return false;
        }
        return $v;
    }

    /**
     * @ai-contract accepts '1','true','yes','on',true → true; '0','false','no','off',false → false
     * @ai-contract returns false for anything else
     */
    public static function bool(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        $lower = strtolower((string) $value);
        if (in_array($lower, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($lower, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
        return false;
    }

    /**
     * @ai-contract returns Y-m-d or Y-m-d H:i:s string, false if not a valid date
     */
    public static function date(mixed $value): string|false {
        if (!is_string($value) && !is_int($value)) {
            return false;
        }
        $ts = strtotime((string) $value);
        return $ts !== false ? date('Y-m-d', $ts) : false;
    }

    /**
     * @ai-contract returns H:i:s string for valid time, false otherwise
     */
    public static function time(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        $ts = strtotime('1970-01-01 ' . $value);
        return $ts !== false ? date('H:i:s', $ts) : false;
    }

    /**
     * @ai-contract returns valid IPv4 or IPv6 string, false for invalid
     */
    public static function ip(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, \FILTER_VALIDATE_IP) !== false ? $value : false;
    }

    /**
     * @ai-contract returns domain string (no scheme, no path), false for invalid
     */
    public static function domain(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        $result = filter_var('http://' . $value, \FILTER_VALIDATE_URL);
        if ($result === false) {
            return false;
        }
        $host = parse_url($result, \PHP_URL_HOST);
        return $host !== null && $host !== false ? $host : false;
    }

    /**
     * @ai-contract returns lowercased email address, false for invalid format
     */
    public static function email(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        $filtered = filter_var(strtolower(trim($value)), \FILTER_VALIDATE_EMAIL);
        return $filtered !== false ? $filtered : false;
    }

    /**
     * @ai-contract returns validated URL string, false for invalid
     */
    public static function url(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        $filtered = filter_var($value, \FILTER_VALIDATE_URL);
        return $filtered !== false ? $filtered : false;
    }

    /**
     * @ai-contract returns value if matches [a-zA-Z0-9_@.\-]+, false otherwise
     */
    public static function username(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[a-zA-Z0-9_@.\-]+$/', $value) === 1 ? $value : false;
    }

    /**
     * @ai-contract returns value if password meets minimum requirements (8+ chars), false otherwise
     */
    public static function password(mixed $value): string|false {
        if (!is_string($value) || strlen($value) < 8) {
            return false;
        }
        return $value;
    }

    /**
     * @ai-contract returns value if matches [a-z0-9\-]+, false otherwise
     */
    public static function slug(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[a-z0-9\-]+$/', $value) === 1 ? $value : false;
    }

    /**
     * @ai-contract returns value if matches $pattern, false otherwise
     */
    public static function regex(mixed $value, string $pattern): string|false {
        if (!is_string($value)) {
            return false;
        }
        return preg_match($pattern, $value) === 1 ? $value : false;
    }

    /**
     * @ai-contract returns value if it is in $allowed list, false otherwise
     */
    public static function in(mixed $value, array $allowed): mixed {
        return in_array($value, $allowed, true) ? $value : false;
    }

    /**
     * @ai-contract returns value if $min <= $value <= $max, false otherwise
     */
    public static function range(int|float $value, int|float $min, int|float $max): int|float|false {
        return $value >= $min && $value <= $max ? $value : false;
    }

    // --- array variants ---

    /**
     * @ai-contract filters array, returns only valid ints (false values removed)
     */
    public static function arr_int(array $values, ?int $min = null, ?int $max = null): array {
        return array_values(array_filter(
            array_map(fn($v) => self::int($v, $min, $max), $values),
            fn($v) => $v !== false,
        ));
    }

    /**
     * @ai-contract filters array, returns only positive ints
     */
    public static function arr_int_positive(array $values): array {
        return self::arr_int($values, min: 1);
    }

    /**
     * @ai-contract filters array, returns only values present in $allowed
     */
    public static function arr_in(array $values, array $allowed): array {
        return array_values(array_filter($values, fn($v) => in_array($v, $allowed, true)));
    }
}
