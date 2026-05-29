<?php declare(strict_types=1);

namespace skim\helpers;

/**
 * String utility methods — slugs, truncation, UUIDs, case conversion. #AI:class
 *
 * Use for common string transformations that appear across controllers,
 * models, and CLI commands. All methods are pure static — no state.
 *
 * Example:
 *   str::slug('Hello World!');     // 'hello-world'
 *   str::uuid();                   // '550e8400-e29b-41d4-a716-446655440000'
 *   str::to_snake('UserProfile');  // 'user_profile'
 *
 * Testing: All methods are pure functions — test directly, no mocking needed.
 *
 * #AI:class
 */
final class str {
    /**
     * Converts text to a URL-safe slug. #AI:slug
     *
     * Lowercases, strips non-alphanumeric characters (Unicode-aware),
     * collapses whitespace and dashes.
     *
     * @param string $text Input text to slugify.
     */
    public static function slug(string $text): string {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $text);
        $text = (string) preg_replace('/[\s\-]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * Truncates text to a maximum length, appending a suffix if cut. #AI:excerpt
     *
     * When $word_boundary is true, avoids cutting mid-word by backing up
     * to the last space within the limit.
     *
     * @param string $text          Input text to truncate.
     * @param int    $length        Maximum character count before truncation.
     * @param string $suffix        Appended when text is truncated.
     * @param bool   $word_boundary Back up to last space to avoid mid-word cuts.
     */
    public static function excerpt(string $text, int $length = 100, string $suffix = '...', bool $word_boundary = true): string {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $truncated = mb_substr($text, 0, $length);
        if ($word_boundary) {
            $last_space = mb_strrpos($truncated, ' ');
            if ($last_space !== false) {
                $truncated = mb_substr($truncated, 0, $last_space);
            }
        }
        return rtrim($truncated) . $suffix;
    }

    /**
     * Generates a cryptographically random alphanumeric string. #AI:random
     *
     * @param int $length Number of characters in the output.
     */
    public static function random(int $length = 32): string {
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max    = strlen($chars) - 1;
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }
        return $result;
    }

    /**
     * Generates an RFC 4122 UUID v4. #AI:uuid
     */
    public static function uuid(): string {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Returns true when $haystack contains $needle. #AI:contains
     *
     * @param string $haystack String to search in.
     * @param string $needle   Substring to look for.
     */
    public static function contains(string $haystack, string $needle): bool {
        return str_contains($haystack, $needle);
    }

    /**
     * Returns true when $str starts with $prefix. #AI:starts_with
     *
     * @param string $str    String to test.
     * @param string $prefix Expected prefix.
     */
    public static function starts_with(string $str, string $prefix): bool {
        return str_starts_with($str, $prefix);
    }

    /**
     * Returns true when $str ends with $suffix. #AI:ends_with
     *
     * @param string $str    String to test.
     * @param string $suffix Expected suffix.
     */
    public static function ends_with(string $str, string $suffix): bool {
        return str_ends_with($str, $suffix);
    }

    /**
     * Converts CamelCase to snake_case. #AI:to_snake
     *
     * @param string $str CamelCase or PascalCase input.
     */
    public static function to_snake(string $str): string {
        $str = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $str);
        $str = (string) preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $str);
        return strtolower($str);
    }

    /**
     * Converts snake_case to camelCase. #AI:to_camel
     *
     * @param string $str snake_case input.
     */
    public static function to_camel(string $str): string {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }
}

#AI:class
#AI symbol: skim\helpers\str
#AI source_path: src/helpers/str.php
#AI title: str
#AI description: Static string utility methods for slugs, truncation, UUIDs, random strings, and case conversion.
#AI role: string utility helper
#AI layer: helpers
#AI badges: [helper; string; stateless]
#AI intro: `str` provides common string transformations as pure static methods. Used across controllers, models, and CLI commands for slug generation, text truncation, UUID creation, and case conversion.
#AI lifecycle: stateless — all methods are pure functions
#AI fallback: none
#AI test_seam: test directly — no mocking needed
#AI invariants: [all methods are static and pure; slug() is Unicode-aware via mb_* and \p{L}; random() and uuid() use random_int/random_bytes for cryptographic safety]
#AI core_behaviors: [slug() lowercases, strips non-alphanumeric, collapses separators; excerpt() respects word boundaries; to_snake() handles consecutive capitals]
#AI warnings: []
#AI notes: contains/starts_with/ends_with are thin wrappers over PHP 8.0+ str_* functions for API consistency.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [slug; excerpt; random; uuid; contains; starts_with; ends_with; to_snake; to_camel]
#AI config_reads: []
#AI non_goals: [Does not handle HTML entities; Does not perform locale-aware collation]
#AI side_effects: []
#AI flow: caller -> str::method() -> pure return value
#AI lifecycle_steps: [caller invokes static method; -> pure computation; -> return value]
#AI section_order: [Slugs & Truncation; Generation; Predicates; Case Conversion]
#AI architectural_notes: Thin, focused utility class. Prefer these over ad-hoc string manipulation for consistency.

#AI:slug
#AI group: Slugs & Truncation
#AI frequency: high
#AI signature: public static function slug(string $text): string
#AI contract: Converts text to a URL-safe slug by lowercasing, stripping non-alphanumeric characters (Unicode-aware), and collapsing whitespace/dashes into single dashes.
#AI param_details: [{name: $text | type: string | required: true | desc: Input text to slugify.}]
#AI return_detail: {type: string | desc: URL-safe slug.}

#AI:excerpt
#AI group: Slugs & Truncation
#AI frequency: medium
#AI signature: public static function excerpt(string $text, int $length = 100, string $suffix = '...', bool $word_boundary = true): string
#AI contract: Truncates text to $length characters. When $word_boundary is true, backs up to the last space to avoid mid-word cuts. Appends $suffix only when truncation occurs.
#AI param_details: [{name: $text | type: string | required: true | desc: Input text to truncate.}; {name: $length | type: int | required: false | desc: Maximum character count before truncation. Default 100.}; {name: $suffix | type: string | required: false | desc: Appended when text is truncated. Default '...'.}; {name: $word_boundary | type: bool | required: false | desc: When true, avoids cutting mid-word. Default true.}]
#AI return_detail: {type: string | desc: Truncated text with suffix, or original if within limit.}

#AI:random
#AI group: Generation
#AI frequency: medium
#AI signature: public static function random(int $length = 32): string
#AI contract: Generates a cryptographically random alphanumeric string using random_int().
#AI param_details: [{name: $length | type: int | required: false | desc: Number of characters. Default 32.}]
#AI return_detail: {type: string | desc: Random alphanumeric string.}

#AI:uuid
#AI group: Generation
#AI frequency: medium
#AI signature: public static function uuid(): string
#AI contract: Generates an RFC 4122 UUID v4 using random_bytes().
#AI return_detail: {type: string | desc: UUID v4 string in standard format.}

#AI:contains
#AI group: Predicates
#AI frequency: low
#AI signature: public static function contains(string $haystack, string $needle): bool
#AI contract: Returns true when $haystack contains $needle. Thin wrapper over str_contains().
#AI param_details: [{name: $haystack | type: string | required: true | desc: String to search in.}; {name: $needle | type: string | required: true | desc: Substring to look for.}]
#AI return_detail: {type: bool | desc: True if needle is found.}

#AI:starts_with
#AI group: Predicates
#AI frequency: low
#AI signature: public static function starts_with(string $str, string $prefix): bool
#AI contract: Returns true when $str starts with $prefix.
#AI param_details: [{name: $str | type: string | required: true | desc: String to test.}; {name: $prefix | type: string | required: true | desc: Expected prefix.}]
#AI return_detail: {type: bool | desc: True if string starts with prefix.}

#AI:ends_with
#AI group: Predicates
#AI frequency: low
#AI signature: public static function ends_with(string $str, string $suffix): bool
#AI contract: Returns true when $str ends with $suffix.
#AI param_details: [{name: $str | type: string | required: true | desc: String to test.}; {name: $suffix | type: string | required: true | desc: Expected suffix.}]
#AI return_detail: {type: bool | desc: True if string ends with suffix.}

#AI:to_snake
#AI group: Case Conversion
#AI frequency: medium
#AI signature: public static function to_snake(string $str): string
#AI contract: Converts CamelCase or PascalCase to snake_case. Handles consecutive capitals correctly (e.g. 'HTMLParser' → 'html_parser').
#AI param_details: [{name: $str | type: string | required: true | desc: CamelCase input.}]
#AI return_detail: {type: string | desc: snake_case output.}

#AI:to_camel
#AI group: Case Conversion
#AI frequency: medium
#AI signature: public static function to_camel(string $str): string
#AI contract: Converts snake_case to camelCase (first letter lowercase).
#AI param_details: [{name: $str | type: string | required: true | desc: snake_case input.}]
#AI return_detail: {type: string | desc: camelCase output.}
