<?php declare(strict_types=1);

namespace skim\helpers;

final class str {
    /**
     * @ai-contract converts string to URL-safe slug: 'Hello World!' → 'hello-world'
     */
    public static function slug(string $text): string {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $text);
        $text = (string) preg_replace('/[\s\-]+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * @ai-contract truncates text to $length chars, appends '...' if truncated
     * @ai-contract does not cut in the middle of a word when $word_boundary=true
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
     * @ai-contract returns cryptographically random alphanumeric string of $length chars
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
     * @ai-contract returns RFC 4122 UUID v4
     */
    public static function uuid(): string {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function contains(string $haystack, string $needle): bool {
        return str_contains($haystack, $needle);
    }

    public static function starts_with(string $str, string $prefix): bool {
        return str_starts_with($str, $prefix);
    }

    public static function ends_with(string $str, string $suffix): bool {
        return str_ends_with($str, $suffix);
    }

    /**
     * @ai-contract converts CamelCase to snake_case: 'UserProfile' → 'user_profile'
     */
    public static function to_snake(string $str): string {
        $str = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $str);
        $str = (string) preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $str);
        return strtolower($str);
    }

    /**
     * @ai-contract converts snake_case to camelCase: 'user_profile' → 'userProfile'
     */
    public static function to_camel(string $str): string {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }
}
