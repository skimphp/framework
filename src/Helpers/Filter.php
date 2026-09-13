<?php declare(strict_types=1);

namespace Skim\Helpers;

/**
 * Input validation and sanitization helper — returns typed values or false. #AI:class
 *
 * Use in controllers to validate user input before passing to models or queries.
 * All methods return the typed value on success or false on invalid input —
 * never null. false means "invalid input, discard it".
 *
 * Example:
 *   $age   = Filter::int($req->post('age'), min: 18, max: 120);
 *   $email = Filter::email($req->post('email'));
 *   if ($age === false || $email === false) {
 *       return $res->status(422)->json(['error' => 'Invalid input']);
 *   }
 *
 * Testing: All methods are pure functions — test directly, no mocking needed.
 *
 * #AI:class
 */
final class Filter {
    /**
     * Validates and returns an integer, or false if invalid. #AI:int
     *
     * Rejects floats (1.5), non-numeric strings, and values outside the
     * optional [$min, $max] range.
     *
     * @param mixed    $value Input to validate.
     * @param int|null $min   Minimum allowed value (inclusive).
     * @param int|null $max   Maximum allowed value (inclusive).
     */
    public static function int(mixed $value, ?int $min = null, ?int $max = null): int|false {
        if (!is_numeric($value)) {
            return false;
        }
        $v = (int) $value;
        if ((string) $v !== ltrim((string) $value, '+')) {
            return false;
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
     * Validates a positive integer (min: 1). #AI:intPositive
     *
     * @param mixed $value Input to validate.
     */
    public static function intPositive(mixed $value): int|false {
        return self::int($value, min: 1);
    }

    /**
     * Validates a natural number (min: 0). #AI:intNatural
     *
     * @param mixed $value Input to validate.
     */
    public static function intNatural(mixed $value): int|false {
        return self::int($value, min: 0);
    }

    /**
     * Validates and returns a float, or false if invalid. #AI:float
     *
     * @param mixed      $value Input to validate.
     * @param float|null $min   Minimum allowed value (inclusive).
     * @param float|null $max   Maximum allowed value (inclusive).
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
     * Converts truthy/falsy string representations to bool. #AI:bool
     *
     * Accepts '1','true','yes','on',true → true; '0','false','no','off',false → false.
     * Returns false for anything else.
     *
     * @param mixed $value Input to convert.
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
     * Validates and returns a Y-m-d date string, or false if invalid. #AI:date
     *
     * @param mixed $value Input to validate.
     */
    public static function date(mixed $value): string|false {
        if (!is_string($value) && !is_int($value)) {
            return false;
        }
        $ts = strtotime((string) $value);
        return $ts !== false ? date('Y-m-d', $ts) : false;
    }

    /**
     * Validates and returns an H:i:s time string, or false if invalid. #AI:time
     *
     * @param mixed $value Input to validate.
     */
    public static function time(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        $ts = strtotime('1970-01-01 ' . $value);
        return $ts !== false ? date('H:i:s', $ts) : false;
    }

    /**
     * Validates an IPv4 or IPv6 address. #AI:ip
     *
     * @param mixed $value Input to validate.
     */
    public static function ip(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        return filter_var($value, \FILTER_VALIDATE_IP) !== false ? $value : false;
    }

    /**
     * Validates a domain name (no scheme, no path). #AI:domain
     *
     * @param mixed $value Input to validate.
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
     * Validates and lowercases an email address. #AI:email
     *
     * @param mixed $value Input to validate.
     */
    public static function email(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        $filtered = filter_var(strtolower(trim($value)), \FILTER_VALIDATE_EMAIL);
        return $filtered !== false ? $filtered : false;
    }

    /**
     * Validates a URL. #AI:url
     *
     * @param mixed $value Input to validate.
     */
    public static function url(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        $filtered = filter_var($value, \FILTER_VALIDATE_URL);
        return $filtered !== false ? $filtered : false;
    }

    /**
     * Validates a username: [a-zA-Z0-9_@.\-]+ only. #AI:username
     *
     * @param mixed $value Input to validate.
     */
    public static function username(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[a-zA-Z0-9_@.\-]+$/', $value) === 1 ? $value : false;
    }

    /**
     * Validates a password: minimum 8 characters. #AI:password
     *
     * @param mixed $value Input to validate.
     */
    public static function password(mixed $value): string|false {
        if (!is_string($value) || strlen($value) < 8) {
            return false;
        }
        return $value;
    }

    /**
     * Validates a slug: [a-z0-9\-]+ only. #AI:slug
     *
     * @param mixed $value Input to validate.
     */
    public static function slug(mixed $value): string|false {
        if (!is_string($value)) {
            return false;
        }
        return preg_match('/^[a-z0-9\-]+$/', $value) === 1 ? $value : false;
    }

    /**
     * Validates a string against a custom regex pattern. #AI:regex
     *
     * @param mixed  $value   Input to validate.
     * @param string $pattern PCRE pattern to match against.
     */
    public static function regex(mixed $value, string $pattern): string|false {
        if (!is_string($value)) {
            return false;
        }
        return preg_match($pattern, $value) === 1 ? $value : false;
    }

    /**
     * Returns the value only if it is in the allowed list. #AI:in
     *
     * @param mixed $value   Input to check.
     * @param array $allowed Whitelist of allowed values.
     */
    public static function in(mixed $value, array $allowed): mixed {
        return in_array($value, $allowed, true) ? $value : false;
    }

    /**
     * Returns the value only if it falls within [$min, $max]. #AI:range
     *
     * @param int|float $value Input to check.
     * @param int|float $min   Minimum (inclusive).
     * @param int|float $max   Maximum (inclusive).
     */
    public static function range(int|float $value, int|float $min, int|float $max): int|float|false {
        return $value >= $min && $value <= $max ? $value : false;
    }

    // --- array variants ---

    /**
     * Filters an array, keeping only valid ints. #AI:arrInt
     *
     * @param array    $values Input values to validate.
     * @param int|null $min    Minimum allowed value.
     * @param int|null $max    Maximum allowed value.
     */
    public static function arrInt(array $values, ?int $min = null, ?int $max = null): array {
        return array_values(array_filter(
            array_map(fn($v) => self::int($v, $min, $max), $values),
            fn($v) => $v !== false,
        ));
    }

    /**
     * Filters an array, keeping only positive ints. #AI:arrIntPositive
     *
     * @param array $values Input values to validate.
     */
    public static function arrIntPositive(array $values): array {
        return self::arrInt($values, min: 1);
    }

    /**
     * Filters an array, keeping only values present in $allowed. #AI:arrIn
     *
     * @param array $values  Input values to check.
     * @param array $allowed Whitelist of allowed values.
     */
    public static function arrIn(array $values, array $allowed): array {
        return array_values(array_filter($values, fn($v) => in_array($v, $allowed, true)));
    }
}

#AI:class
#AI symbol: Skim\Helpers\Filter
#AI source_path: src/Helpers/Filter.php
#AI title: filter
#AI description: Input validation helper returning typed values or false — never null.
#AI role: input validation helper
#AI layer: helpers
#AI badges: [helper; validation; filter; stateless]
#AI intro: `filter` validates and sanitizes user input. Every method returns the typed value on success or `false` on invalid input — never `null`. Use in controllers before passing data to models or queries.
#AI lifecycle: stateless — all methods are pure functions
#AI fallback: none
#AI test_seam: test directly — no mocking needed
#AI invariants: [all methods return typed value or false, never null; int() rejects floats; email() lowercases output; strict type checking with === false]
#AI core_behaviors: [int() uses string comparison to reject floats like 1.5; bool() accepts common truthy/falsy strings; array variants filter and re-index results]
#AI warnings: []
#AI notes: Use Filter::int() for strict integer validation — it rejects float-like strings. Use Validate::make() for form-level validation with error messages.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [int; intPositive; intNatural; float; bool; date; time; ip; domain; email; url; username; password; slug; regex; in; range; arrInt; arrIntPositive; arrIn]
#AI config_reads: []
#AI non_goals: [Does not provide error messages — use Validate::make() for that; Does not sanitize HTML — use e() for output escaping]
#AI side_effects: []
#AI flow: controller -> Filter::method(input) -> typed value or false -> model/query
#AI lifecycle_steps: [controller receives input; -> Filter::method() validates; -> typed value or false; -> controller branches on false]
#AI section_order: [Numeric; Boolean; Date & Time; Network; String Patterns; Range & Enum; Array Variants]
#AI architectural_notes: Complements Validate::make() — filter is for single-value type checking, validate is for form-level rules with error messages.

#AI:int
#AI group: Numeric
#AI frequency: high
#AI signature: public static function int(mixed $value, ?int $min = null, ?int $max = null): int|false
#AI contract: Returns int if $value is a valid integer string. Rejects floats (1.5), non-numeric strings, and values outside [$min, $max].
#AI param_details: [{name: $value | type: mixed | required: true | desc: Input to validate.}; {name: $min | type: ?int | required: false | desc: Minimum allowed value (inclusive).}; {name: $max | type: ?int | required: false | desc: Maximum allowed value (inclusive).}]
#AI return_detail: {type: int|false | desc: Validated integer or false.}

#AI:intPositive
#AI group: Numeric
#AI frequency: medium
#AI signature: public static function intPositive(mixed $value): int|false
#AI contract: Alias for int() with min:1.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Input to validate.}]
#AI return_detail: {type: int|false | desc: Positive integer or false.}

#AI:intNatural
#AI group: Numeric
#AI frequency: medium
#AI signature: public static function intNatural(mixed $value): int|false
#AI contract: Alias for int() with min:0.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Input to validate.}]
#AI return_detail: {type: int|false | desc: Natural number or false.}

#AI:float
#AI group: Numeric
#AI frequency: medium
#AI signature: public static function float(mixed $value, ?float $min = null, ?float $max = null): float|false
#AI contract: Returns float if numeric, false otherwise. Optional min/max range check.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Input to validate.}; {name: $min | type: ?float | required: false | desc: Minimum allowed value.}; {name: $max | type: ?float | required: false | desc: Maximum allowed value.}]
#AI return_detail: {type: float|false | desc: Validated float or false.}

#AI:bool
#AI group: Boolean
#AI frequency: medium
#AI signature: public static function bool(mixed $value): bool
#AI contract: Converts truthy/falsy string representations to bool. Accepts '1','true','yes','on' → true; '0','false','no','off' → false. Returns false for unrecognized input.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Input to convert.}]
#AI return_detail: {type: bool | desc: Converted boolean value.}

#AI:date
#AI group: Date & Time
#AI frequency: medium
#AI signature: public static function date(mixed $value): string|false
#AI contract: Returns Y-m-d string for valid dates, false otherwise. Uses strtotime() for parsing.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Date string or timestamp.}]
#AI return_detail: {type: string|false | desc: Y-m-d formatted date or false.}

#AI:time
#AI group: Date & Time
#AI frequency: low
#AI signature: public static function time(mixed $value): string|false
#AI contract: Returns H:i:s string for valid time strings, false otherwise.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Time string.}]
#AI return_detail: {type: string|false | desc: H:i:s formatted time or false.}

#AI:ip
#AI group: Network
#AI frequency: low
#AI signature: public static function ip(mixed $value): string|false
#AI contract: Validates IPv4 or IPv6 address using FILTER_VALIDATE_IP.
#AI param_details: [{name: $value | type: mixed | required: true | desc: IP address string.}]
#AI return_detail: {type: string|false | desc: Valid IP string or false.}

#AI:domain
#AI group: Network
#AI frequency: low
#AI signature: public static function domain(mixed $value): string|false
#AI contract: Validates a domain name. Returns the host portion only (no scheme, no path).
#AI param_details: [{name: $value | type: mixed | required: true | desc: Domain string.}]
#AI return_detail: {type: string|false | desc: Domain host or false.}

#AI:email
#AI group: String Patterns
#AI frequency: high
#AI signature: public static function email(mixed $value): string|false
#AI contract: Validates and lowercases an email address using FILTER_VALIDATE_EMAIL.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Email string.}]
#AI return_detail: {type: string|false | desc: Lowercased email or false.}

#AI:url
#AI group: String Patterns
#AI frequency: medium
#AI signature: public static function url(mixed $value): string|false
#AI contract: Validates a URL using FILTER_VALIDATE_URL.
#AI param_details: [{name: $value | type: mixed | required: true | desc: URL string.}]
#AI return_detail: {type: string|false | desc: Valid URL or false.}

#AI:username
#AI group: String Patterns
#AI frequency: medium
#AI signature: public static function username(mixed $value): string|false
#AI contract: Validates against [a-zA-Z0-9_@.\-]+ pattern.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Username string.}]
#AI return_detail: {type: string|false | desc: Valid username or false.}

#AI:password
#AI group: String Patterns
#AI frequency: medium
#AI signature: public static function password(mixed $value): string|false
#AI contract: Validates minimum 8-character password.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Password string.}]
#AI return_detail: {type: string|false | desc: Password if >= 8 chars, false otherwise.}

#AI:slug
#AI group: String Patterns
#AI frequency: medium
#AI signature: public static function slug(mixed $value): string|false
#AI contract: Validates against [a-z0-9\-]+ pattern.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Slug string.}]
#AI return_detail: {type: string|false | desc: Valid slug or false.}

#AI:regex
#AI group: String Patterns
#AI frequency: low
#AI signature: public static function regex(mixed $value, string $pattern): string|false
#AI contract: Validates a string against a custom PCRE pattern.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Input string.}; {name: $pattern | type: string | required: true | desc: PCRE pattern.}]
#AI return_detail: {type: string|false | desc: Value if matches, false otherwise.}

#AI:in
#AI group: Range & Enum
#AI frequency: medium
#AI signature: public static function in(mixed $value, array $allowed): mixed
#AI contract: Returns the value only if it is in the allowed list. Uses strict comparison.
#AI param_details: [{name: $value | type: mixed | required: true | desc: Input to check.}; {name: $allowed | type: array | required: true | desc: Whitelist of allowed values.}]
#AI return_detail: {type: mixed | desc: Value if in list, false otherwise.}

#AI:range
#AI group: Range & Enum
#AI frequency: low
#AI signature: public static function range(int|float $value, int|float $min, int|float $max): int|float|false
#AI contract: Returns the value only if it falls within [$min, $max] inclusive.
#AI param_details: [{name: $value | type: int|float | required: true | desc: Input to check.}; {name: $min | type: int|float | required: true | desc: Minimum (inclusive).}; {name: $max | type: int|float | required: true | desc: Maximum (inclusive).}]
#AI return_detail: {type: int|float|false | desc: Value if in range, false otherwise.}

#AI:arrInt
#AI group: Array Variants
#AI frequency: medium
#AI signature: public static function arrInt(array $values, ?int $min = null, ?int $max = null): array
#AI contract: Filters an array, keeping only valid ints within optional range. Re-indexes the result.
#AI param_details: [{name: $values | type: array | required: true | desc: Input values.}; {name: $min | type: ?int | required: false | desc: Minimum allowed value.}; {name: $max | type: ?int | required: false | desc: Maximum allowed value.}]
#AI return_detail: {type: array | desc: Filtered and re-indexed array of valid ints.}

#AI:arrIntPositive
#AI group: Array Variants
#AI frequency: low
#AI signature: public static function arrIntPositive(array $values): array
#AI contract: Filters an array, keeping only positive ints (min:1).
#AI param_details: [{name: $values | type: array | required: true | desc: Input values.}]
#AI return_detail: {type: array | desc: Filtered array of positive ints.}

#AI:arrIn
#AI group: Array Variants
#AI frequency: medium
#AI signature: public static function arrIn(array $values, array $allowed): array
#AI contract: Filters an array, keeping only values present in $allowed. Re-indexes the result.
#AI param_details: [{name: $values | type: array | required: true | desc: Input values.}; {name: $allowed | type: array | required: true | desc: Whitelist.}]
#AI return_detail: {type: array | desc: Filtered and re-indexed array.}
