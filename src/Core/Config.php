<?php declare(strict_types=1);

namespace Skim\Core;

/**
 * Plain-PHP config registry with dot-notation access across all config files.
 *
 * Use when any part of the app needs to read configuration values.
 * Call load() once during boot to scan a directory of *.php files; each
 * filename becomes a top-level key (db.php → 'db'). Config is frozen
 * after boot — subsequent load() calls are no-ops.
 *
 * Example:
 *   Config::load(__DIR__ . '/../config');
 *   $host = Config::get('db.default.host', 'localhost');
 *
 * Testing: Use set() to inject values without files, reset() to clear state.
 *
 * #AI:class
 */
final class Config {
    private static array $data   = [];
    private static bool  $booted = false;

    /**
     * Scans a directory for *.php config files and loads them once. #AI:load
     *
     * Each file must return an array. The top-level key is the filename
     * without extension (db.php → 'db'). Idempotent — subsequent calls
     * after the first are silently ignored.
     *
     * Example:
     *   Config::load(__DIR__ . '/../config');
     *   // config/db.php  → Config::get('db.default.host')
     *   // config/app.php → Config::get('app.name')
     *
     * @param string $dir Absolute path to the config directory.
     */
    public static function load(string $dir): void {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $val = require $file;
            if (is_array($val)) {
                self::$data[$key] = $val;
            }
        }
    }

    /**
     * Reads a config value using dot-notation traversal. #AI:get
     *
     * Auto-loads config directory on first call when not yet booted.
     * Walks nested arrays segment by segment. Returns $default when any
     * segment is missing or a non-array intermediate is encountered.
     *
     * @param string $key     Dot-separated path such as 'db.default.host'.
     * @param mixed  $default Fallback returned when the path does not resolve.
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (!self::$booted) {
            if (!self::loadCompiledCache()) {
                self::load(basePath('config'));
            }
        }

        $parts   = explode('.', $key);
        $current = self::$data;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }

        return $current;
    }

    /**
     * Injects a config value by dot-notation key (testing only). #AI:set
     *
     * Creates intermediate arrays as needed. Sets the booted flag to prevent
     * auto-load from overwriting test values. Call reset() in tearDown().
     *
     * Example:
     *   Config::set('db.default.host', 'sqlite::memory:');
     *   // ... run tests ...
     *   Config::reset();
     *
     * @param string $key   Dot-separated path to write.
     * @param mixed  $value Value to store at the resolved path.
     */
    public static function set(string $key, mixed $value): void {
        self::$booted = true;

        $parts   = explode('.', $key);
        $current = &self::$data;

        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) {
                $current[$part] = $value;
            } else {
                if (!isset($current[$part]) || !is_array($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }
    }

    /**
     * Clears all loaded config and resets the boot flag (testing only). #AI:reset
     *
     * After reset(), the next load() call will re-scan the config directory.
     */
    public static function reset(): void {
        self::$data   = [];
        self::$booted = false;
    }

    /**
     * Attempts to load config from a pre-compiled PHP array cache. #AI:loadCompiledCache
     *
     * WHY: Pure arrays allow OPcache shared-memory hit with zero parse overhead.
     * Returns false when the cache file is missing or stale (dev mode with
     * APP_DEBUG=true and any config file newer than cache), so the caller
     * falls back to scanning the config directory.
     */
    private static function loadCompiledCache(): bool {
        $cachePath = storagePath('config_cache/config.php');
        if (!is_file($cachePath)) {
            return false;
        }

        $debug = \Skim\Core\Env::get('APP_DEBUG', false);
        if ($debug) {
            $configDir = basePath('config');
            $cacheTime = filemtime($cachePath);
            foreach (glob($configDir . '/*.php') ?: [] as $file) {
                if (filemtime($file) > $cacheTime) {
                    return false;
                }
            }
        }

        self::$data   = require $cachePath;
        self::$booted = true;
        return true;
    }

    /**
     * Returns the entire config data array. #AI:all
     */
    public static function all(): array {
        if (!self::$booted) {
            if (!self::loadCompiledCache()) {
                self::load(basePath('config'));
            }
        }
        return self::$data;
    }
}

#AI:class
#AI symbol: Skim\Core\Config
#AI source_path: src/Core/Config.php
#AI title: config
#AI description: Static config registry with lazy-loading and dot-notation read access across plain PHP array files.
#AI role: static config facade
#AI layer: core
#AI badges: [facade; config; dot-notation; lazy-load; frozen-after-boot]
#AI intro: `Skim\Core\Config` is the single source of configuration for the entire application. It lazy-loads a directory of plain PHP array files on first `get()` call, namespaces each file's return value by its filename, and exposes dot-notation reads. After the first load() call the registry is frozen — further load() calls are silently ignored.
#AI lifecycle: static facade, lazy-loaded on first get() call, frozen afterwards
#AI test_seam: set(), reset()
#AI invariants: [load() is idempotent — only the first call has effect; get() auto-loads config directory when not yet booted; get() returns $default when any path segment is missing; set() creates intermediate arrays and sets booted flag; all() returns the raw nested array]
#AI core_behaviors: [Each config file (db.php, app.php, etc.) becomes a top-level key in the registry; Dot-notation traversal walks nested arrays segment by segment; Config is frozen after boot to prevent runtime mutations from load()]
#AI warnings: [Config is frozen after boot — set() sets the boot flag and should only be used in tests]
#AI notes: Config files must return arrays. Non-array return values from a config file are silently skipped during load().
#AI owns: config data map, boot flag
#AI entry_points: [get; load]
#AI config_reads: []
#AI non_goals: [Does not write config back to disk; Does not validate config structure; Does not support runtime config reloading]
#AI side_effects: [load() sets the boot flag preventing further loads; get() triggers lazy load on first call; set() sets boot flag and mutates the in-memory config map]
#AI flow: Config::get(key) -> auto-load if !$booted -> explode('.') -> traverse $data; Config::load(dir) -> glob *.php -> require each -> store by filename
#AI lifecycle_steps: [Config::get(key); -> !$booted check; -> auto-load basePath('config'); -> idempotent check; -> glob *.php; -> require each; -> store by filename; -> explode('.'); -> traverse $data]
#AI section_order: [Read API; Testing Hooks]
#AI architectural_notes: Config is intentionally simple — plain PHP arrays with no parser overhead. IDE autocomplete works natively on the returned arrays.

#AI:load
#AI group: Read API
#AI frequency: low
#AI signature: public static function load(string $dir): void
#AI contract: Scans $dir for *.php files, requires each one, and stores the returned array under a key derived from the filename (without extension). Only the first call takes effect; subsequent calls are no-ops.
#AI param_details: [{name: $dir | type: string | required: true | desc: Absolute path to the directory containing config PHP files.}]
#AI side_effects: [Sets the boot flag to true; Populates the internal data map from disk files]
#AI examples: [{label: Boot config | code: Config::load(basePath('config'));}]

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public static function get(string $key, mixed $default = null): mixed
#AI contract: Auto-loads config directory on first call when not yet booted. Traverses the config data using dot-separated segments. Returns $default when any segment is missing or a non-array intermediate is encountered.
#AI param_details: [{name: $key | type: string | required: true | desc: Dot-separated path such as 'db.default.host'.}; {name: $default | type: mixed | required: false | desc: Fallback returned when the path does not resolve.}]
#AI return_detail: {type: mixed | desc: The resolved config value, or $default if the path is not found.}

#AI:set
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function set(string $key, mixed $value): void
#AI contract: Writes a value at the given dot-notation path, creating intermediate arrays as needed. Sets the boot flag to prevent auto-load from overwriting test values. Intended for test setup only.
#AI param_details: [{name: $key | type: string | required: true | desc: Dot-separated path to write.}; {name: $value | type: mixed | required: true | desc: Value to store at the resolved path.}]
#AI side_effects: [Sets boot flag to true; Mutates the in-memory config map]
#AI notes: Always pair with reset() in tearDown() to avoid leaking state between tests.

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears all loaded config data and resets the boot flag so the next load() or get() call will re-scan the config directory.
#AI side_effects: [Clears the internal data map; Resets boot flag to false]

#AI:all
#AI group: Read API
#AI frequency: low
#AI signature: public static function all(): array
#AI contract: Returns the entire internal config data array as-is. Auto-loads on first call.
#AI return_detail: {type: array | desc: The full nested config map keyed by filename then array keys.}

#AI:loadCompiledCache
#AI group: Read API
#AI frequency: internal
#AI signature: private static function loadCompiledCache(): bool
#AI contract: Attempts to load config from a pre-compiled PHP array cache at storage/config_cache/config.php. Returns false when the cache file is missing or stale (APP_DEBUG=true and any config/*.php file newer than cache). Sets booted flag on success.
#AI return_detail: {type: bool | desc: True if cache was loaded, false if caller should fall back to load().}
#AI side_effects: [Populates self::$data from compiled file; Sets self::$booted to true on success]
