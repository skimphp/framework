<?php declare(strict_types=1);

namespace skim\core;

/**
 * Plain-PHP config registry with dot-notation access across all config files.
 *
 * Use when any part of the app needs to read configuration values.
 * Call load() once during boot to scan a directory of *.php files; each
 * filename becomes a top-level key (db.php → 'db'). Config is frozen
 * after boot — subsequent load() calls are no-ops.
 *
 * Example:
 *   config::load(__DIR__ . '/../config');
 *   $host = config::get('db.default.host', 'localhost');
 *
 * Testing: Use set() to inject values without files, reset() to clear state.
 *
 * #AI:class
 */
final class config {
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
     *   config::load(__DIR__ . '/../config');
     *   // config/db.php  → config::get('db.default.host')
     *   // config/app.php → config::get('app.name')
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
     * Walks nested arrays segment by segment. Returns $default when any
     * segment is missing or a non-array intermediate is encountered.
     *
     * @param string $key     Dot-separated path such as 'db.default.host'.
     * @param mixed  $default Fallback returned when the path does not resolve.
     */
    public static function get(string $key, mixed $default = null): mixed {
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
     * Creates intermediate arrays as needed. Use in tests to set config
     * values without writing files to disk. Call reset() in tearDown().
     *
     * Example:
     *   config::set('db.default.host', 'sqlite::memory:');
     *   // ... run tests ...
     *   config::reset();
     *
     * @param string $key   Dot-separated path to write.
     * @param mixed  $value Value to store at the resolved path.
     */
    public static function set(string $key, mixed $value): void {
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
     * Returns the entire config data array. #AI:all
     */
    public static function all(): array {
        return self::$data;
    }
}

#AI:class
#AI symbol: skim\core\config
#AI source_path: src/core/config.php
#AI title: config
#AI description: Static config registry that loads plain PHP arrays from a directory and provides dot-notation read access.
#AI role: static config facade
#AI layer: core
#AI badges: [facade; config; dot-notation; frozen-after-boot]
#AI intro: `skim\core\config` is the single source of configuration for the entire application. It scans a directory of plain PHP array files once during boot, namespaces each file's return value by its filename, and exposes dot-notation reads. After the first load() call the registry is frozen — further load() calls are silently ignored.
#AI lifecycle: static facade, loaded once during app boot, frozen afterwards
#AI test_seam: set(), reset()
#AI invariants: [load() is idempotent — only the first call has effect; get() returns $default when any path segment is missing; set() creates intermediate arrays automatically; all() returns the raw nested array]
#AI core_behaviors: [Each config file (db.php, app.php, etc.) becomes a top-level key in the registry; Dot-notation traversal walks nested arrays segment by segment; Config is frozen after boot to prevent runtime mutations from load()]
#AI warnings: [Config is frozen after boot — set() bypasses the boot flag and should only be used in tests]
#AI notes: Config files must return arrays. Non-array return values from a config file are silently skipped during load().
#AI owns: config data map, boot flag
#AI entry_points: [load; get]
#AI config_reads: []
#AI non_goals: [Does not write config back to disk; Does not validate config structure; Does not support runtime config reloading]
#AI side_effects: [load() sets the boot flag preventing further loads; set() mutates the in-memory config map]
#AI flow: config::load(dir) -> glob *.php -> require each -> store by filename; config::get(key) -> explode('.') -> traverse $data
#AI section_order: [Read API; Testing Hooks]
#AI architectural_notes: Config is intentionally simple — plain PHP arrays with no parser overhead. IDE autocomplete works natively on the returned arrays.

#AI:load
#AI group: Read API
#AI frequency: low
#AI signature: public static function load(string $dir): void
#AI contract: Scans $dir for *.php files, requires each one, and stores the returned array under a key derived from the filename (without extension). Only the first call takes effect; subsequent calls are no-ops.
#AI param_details: [{name: $dir | type: string | required: true | desc: Absolute path to the directory containing config PHP files.}]
#AI side_effects: [Sets the boot flag to true; Populates the internal data map from disk files]
#AI examples: [{label: Boot config | code: config::load(__DIR__ . '/../config');}]

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public static function get(string $key, mixed $default = null): mixed
#AI contract: Traverses the config data using dot-separated segments. Returns $default when any segment is missing or a non-array intermediate is encountered.
#AI param_details: [{name: $key | type: string | required: true | desc: Dot-separated path such as 'db.default.host'.}; {name: $default | type: mixed | required: false | desc: Fallback returned when the path does not resolve.}]
#AI return_detail: {type: mixed | desc: The resolved config value, or $default if the path is not found.}

#AI:set
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function set(string $key, mixed $value): void
#AI contract: Writes a value at the given dot-notation path, creating intermediate arrays as needed. Intended for test setup only — bypasses the boot-freeze guard.
#AI param_details: [{name: $key | type: string | required: true | desc: Dot-separated path to write.}; {name: $value | type: mixed | required: true | desc: Value to store at the resolved path.}]
#AI side_effects: [Mutates the in-memory config map]
#AI notes: Always pair with reset() in tearDown() to avoid leaking state between tests.

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears all loaded config data and resets the boot flag so the next load() call will re-scan the config directory.
#AI side_effects: [Clears the internal data map; Resets boot flag to false]

#AI:all
#AI group: Read API
#AI frequency: low
#AI signature: public static function all(): array
#AI contract: Returns the entire internal config data array as-is.
#AI return_detail: {type: array | desc: The full nested config map keyed by filename then array keys.}
