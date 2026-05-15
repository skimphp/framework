<?php declare(strict_types=1);

namespace skim\i18n;

// Minimal i18n — PHP array files only, no YAML, no Symfony Translation dependency.
// Translation files live in lang/{locale}/*.php, each returning a flat array.
// Keys support dot notation: t('auth.login.title') → lang/en/auth.php['login']['title']
//
// Pluralization: t('items.count', ['count' => 3]) → uses 'count' key for plural selection.
// Simple two-form: "One item|Many items" split by pipe.
// For complex locale-aware plurals, swap in symfony/translation via set_loader().
final class i18n {
    private static string  $locale      = 'en';
    private static string  $fallback    = 'en';
    private static string  $lang_path   = '';
    private static array   $loaded      = [];    // [locale][file] => translations
    private static ?callable $loader    = null;  // optional custom loader

    /**
     * @ai-contract sets active locale — affects all subsequent t() calls
     */
    public static function locale(string $locale): void {
        self::$locale = $locale;
    }

    /**
     * @ai-contract returns the current locale string
     */
    public static function current_locale(): string {
        return self::$locale;
    }

    /**
     * @ai-contract sets path to lang/ directory containing locale subdirectories
     */
    public static function set_path(string $path): void {
        self::$lang_path = rtrim($path, '/');
    }

    /**
     * @ai-contract translates $key using current locale, falls back to $fallback locale
     * @ai-contract $params values are interpolated: :name, :count
     * @ai-contract returns $key unchanged when no translation found (never throw)
     */
    public static function t(string $key, array $params = []): string {
        $value = self::resolve($key, self::$locale)
            ?? self::resolve($key, self::$fallback)
            ?? $key;

        // Pluralization: "One item|Many items"
        if (str_contains($value, '|') && isset($params['count'])) {
            $parts = explode('|', $value, 2);
            $value = (int) $params['count'] === 1 ? $parts[0] : $parts[1];
        }

        // Interpolate :param placeholders
        foreach ($params as $param => $val) {
            $value = str_replace(':' . $param, (string) $val, $value);
        }

        return trim($value);
    }

    /**
     * @ai-contract inject a custom translation loader (e.g. symfony/translation)
     * @ai-contract loader receives (locale, key) and returns string or null
     */
    public static function set_loader(callable $loader): void {
        self::$loader = $loader;
    }

    /**
     * @ai-contract for tests — reset all state
     */
    public static function reset(): void {
        self::$locale   = 'en';
        self::$fallback = 'en';
        self::$loaded   = [];
        self::$loader   = null;
    }

    // --- internals ---

    private static function resolve(string $key, string $locale): ?string {
        if (self::$loader !== null) {
            return (self::$loader)($locale, $key);
        }

        // key format: 'file.dotted.path' → lang/en/file.php['dotted']['path']
        $parts = explode('.', $key);
        $file  = array_shift($parts);

        $translations = self::load_file($locale, $file);
        if ($translations === null) {
            return null;
        }

        $current = $translations;
        foreach ($parts as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return is_string($current) ? $current : null;
    }

    private static function load_file(string $locale, string $file): ?array {
        if (isset(self::$loaded[$locale][$file])) {
            return self::$loaded[$locale][$file];
        }

        $path = (self::$lang_path ?: base_path('lang')) . "/{$locale}/{$file}.php";

        if (!is_file($path)) {
            return null;
        }

        $data = require $path;
        self::$loaded[$locale][$file] = is_array($data) ? $data : [];
        return self::$loaded[$locale][$file];
    }
}
