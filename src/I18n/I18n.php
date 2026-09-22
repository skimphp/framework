<?php declare(strict_types=1);

namespace Skim\I18n;

use Skim\Worker\Resettable;

/**
 * Minimal i18n facade — PHP array translation files with dot-notation keys.
 *
 * Use for translating UI strings with locale fallback and parameter
 * interpolation. Translation files live in lang/{locale}/*.php, each
 * returning a flat or nested array. Supports simple pipe-based pluralization.
 *
 * Example:
 *   I18n::locale('fr');
 *   I18n::t('auth.login.title');              // lang/fr/auth.php['login']['title']
 *   I18n::t('items.count', ['count' => 3]);   // "3 items" (plural form)
 *
 * Testing: Use reset() in tearDown() to clear locale and loaded files.
 *
 * #AI:class
 */
final class I18n implements \Skim\Worker\Resettable {
    private static string  $locale      = 'en';
    private static string  $fallback    = 'en';
    private static string  $langPath   = '';
    private static array   $loaded      = [];
    private static mixed   $loader      = null;

    /**
     * Resets the locale to fallback between requests in worker mode. #AI:resetRequest
     */
    public static function resetRequest(): void {
        self::$locale = self::$fallback;
    }

    /**
     * Sets the active locale for all subsequent t() calls. #AI:locale
     *
     * @param string $locale Locale code, e.g. 'fr', 'es', 'de'.
     */
    public static function locale(string $locale): void {
        self::$locale = $locale;
    }

    /**
     * Returns the current active locale string. #AI:currentLocale
     */
    public static function currentLocale(): string {
        return self::$locale;
    }

    /**
     * Sets the path to the lang/ directory containing locale subdirectories. #AI:setPath
     *
     * @param string $path Absolute path to the lang directory.
     */
    public static function setPath(string $path): void {
        self::$langPath = rtrim($path, '/');
    }

    /**
     * Translates a dot-notation key using the active locale with fallback. #AI:t
     *
     * Resolves the key in the current locale first, then falls back to the
     * configured fallback locale. Returns the key unchanged when no translation
     * is found. Supports pipe-based pluralization ("One item|Many items") when
     * a 'count' param is present, and :param interpolation.
     *
     * Example:
     *   I18n::t('auth.welcome', ['name' => 'John']);  // "Welcome, John"
     *   I18n::t('items.count', ['count' => 1]);        // "1 item"
     *   I18n::t('items.count', ['count' => 5]);        // "5 items"
     *
     * @param string $key    Dot-notation key: 'file.path.to.key'.
     * @param array  $params Interpolation params (:name) and pluralization (count).
     */
    public static function t(string $key, array $params = []): string {
        $value = self::resolve($key, self::$locale)
            ?? self::resolve($key, self::$fallback)
            ?? $key;

        if (str_contains($value, '|') && isset($params['count'])) {
            $parts = explode('|', $value, 2);
            $value = (int) $params['count'] === 1 ? $parts[0] : $parts[1];
        }

        foreach ($params as $param => $val) {
            $value = str_replace(':' . $param, (string) $val, $value);
        }

        return trim($value);
    }

    /**
     * Injects a custom translation loader callable. #AI:setLoader
     *
     * The loader receives (locale, key) and must return a string or null.
     * Use to integrate symfony/translation or database-backed translations.
     *
     * @param callable $loader Function(string $locale, string $key): ?string.
     */
    public static function setLoader(callable $loader): void {
        self::$loader = $loader;
    }

    /**
     * Clears all state: locale, loaded files, and custom loader. #AI:reset
     *
     * Use in test tearDown() to isolate translation state between cases.
     */
    public static function reset(): void {
        self::$locale   = 'en';
        self::$fallback = 'en';
        self::$loaded   = [];
        self::$loader   = null;
    }

    private static function resolve(string $key, string $locale): ?string {
        if (self::$loader !== null) {
            return (self::$loader)($locale, $key);
        }

        $parts = explode('.', $key);
        $file  = array_shift($parts);

        $translations = self::loadFile($locale, $file);
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

    private static function loadFile(string $locale, string $file): ?array {
        if (isset(self::$loaded[$locale][$file])) {
            return self::$loaded[$locale][$file];
        }

        $path = (self::$langPath ?: basePath('lang')) . "/{$locale}/{$file}.php";

        if (!is_file($path)) {
            return null;
        }

        $data = require $path;
        self::$loaded[$locale][$file] = is_array($data) ? $data : [];
        return self::$loaded[$locale][$file];
    }
}

#AI:class
#AI symbol: Skim\I18n\I18n
#AI source_path: src/I18n/I18n.php
#AI title: i18n
#AI description: Minimal i18n facade with PHP array files, dot-notation keys, pluralization, and custom loader support.
#AI role: static i18n facade
#AI layer: i18n
#AI badges: [facade; i18n; translation; pluralization]
#AI intro: `i18n` provides translation lookup using PHP array files organized by locale. It supports dot-notation keys, pipe-based pluralization, :param interpolation, fallback locale, and custom loaders for alternative backends.
#AI lifecycle: static facade, translation files loaded on first access per locale
#AI fallback: returns the key unchanged when no translation is found
#AI test_seam: setLoader(), reset()
#AI invariants: [t() never throws — returns key on miss; Files are loaded once and cached per locale; Custom loader bypasses file loading entirely]
#AI core_behaviors: [Dot-notation key resolution through nested arrays; Pipe-based two-form pluralization; :param interpolation; Fallback locale on miss]
#AI owns: loaded translation cache
#AI entry_points: [t; locale; setLoader; setPath]
#AI config_reads: []
#AI non_goals: [Does not support ICU plural rules; Does not handle RTL layout; Does not provide locale negotiation from Accept-Language]
#AI side_effects: [Loads PHP files from lang/ directory on first access]
#AI flow: I18n::t(key) -> resolve(key, locale) -> resolve(key, fallback) -> key -> pluralize -> interpolate
#AI section_order: [Translation API; Configuration; Testing Hooks]

#AI:locale
#AI group: Configuration
#AI frequency: medium
#AI signature: public static function locale(string $locale): void
#AI contract: Sets the active locale used by all subsequent t() calls.
#AI param_details: [{name: $locale | type: string | required: true | desc: Locale code, e.g. 'fr', 'es', 'de'.}]
#AI side_effects: [Mutates static locale state]

#AI:currentLocale
#AI group: Configuration
#AI frequency: low
#AI signature: public static function currentLocale(): string
#AI contract: Returns the currently active locale string.
#AI return_detail: {type: string | desc: Current locale code.}

#AI:setPath
#AI group: Configuration
#AI frequency: low
#AI signature: public static function setPath(string $path): void
#AI contract: Sets the base path to the lang/ directory containing locale subdirectories.
#AI param_details: [{name: $path | type: string | required: true | desc: Absolute path to the lang directory.}]
#AI side_effects: [Mutates static langPath state]

#AI:t
#AI group: Translation API
#AI frequency: high
#AI signature: public static function t(string $key, array $params = []): string
#AI contract: Translates a dot-notation key using the active locale with fallback. Supports pipe-based pluralization when 'count' is in params, and :param interpolation. Returns the key unchanged on miss.
#AI param_details: [{name: $key | type: string | required: true | desc: Dot-notation translation key, e.g. 'auth.login.title'.}; {name: $params | type: array | required: false | desc: Interpolation params (:name => value) and pluralization (count => int).}]
#AI return_detail: {type: string | desc: Translated and interpolated string, or the key itself on miss.}

#AI:setLoader
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function setLoader(callable $loader): void
#AI contract: Injects a custom translation loader that bypasses file-based loading. The callable receives (locale, key) and returns string or null.
#AI param_details: [{name: $loader | type: callable | required: true | desc: Function(string $locale, string $key): ?string.}]
#AI side_effects: [Mutates static loader state]

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears all state: locale, fallback, loaded files, and custom loader. Use in test tearDown().
#AI side_effects: [Clears all static state]

#AI:resetRequest
#AI group: Testing Hooks
#AI frequency: internal
#AI signature: public static function resetRequest(): void
#AI contract: Resets the locale to fallback between requests in worker mode.
#AI side_effects: [Mutates static $locale to $fallback value]
