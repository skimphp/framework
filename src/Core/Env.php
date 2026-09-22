<?php declare(strict_types=1);

namespace Skim\Core;

/**
 * Parses .env files and provides typed access to environment variables.
 *
 * Use when reading configuration that varies between environments without
 * adding the vlucas/phpdotenv dependency — this is 30 lines of trivial parsing.
 * Lazy-loads on first get() call; OS environment variables always take priority
 * over .env values. Missing .env files are silently skipped.
 *
 * Example:
 *   $debug = Env::get('APP_DEBUG', false);   // auto-loads .env on first call
 *
 * Testing: Use set() to override keys, reset() to clear state between tests.
 *
 * #AI:class
 */
final class Env {
    // Parsed .env values and test overrides; never touches $_ENV or putenv().
    private static array $cache = [];
    // Idempotent guard: true after first load() or set() call.
    private static bool  $loaded = false;

    /**
     * Parses a .env file into the internal cache only. #AI:load
     *
     * Idempotent — subsequent calls are no-ops. Silently skips missing files
     * (.env is optional in production). OS variable priority is enforced at
     * read time in get(), not at load time.
     *
     * Example:
     *   Env::load(basePath('.env'));
     *
     * @param string $path Absolute path to the .env file.
     */
    public static function load(string $path): void {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);

            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            // WHY: putenv() removed — internal cache only, no process-global mutations.
            self::$cache[$key] = $val;
        }
    }

    /**
     * Returns an environment variable value with automatic type casting. #AI:get
     *
     * Read priority: $_SERVER → $_ENV → internal cache (.env + set()) → $default.
     * Auto-loads .env on first call when not yet loaded. Casts 'true', 'false',
     * and 'null' strings to native PHP types.
     *
     * @param string $key     Environment variable name.
     * @param mixed  $default Returned as-is when the key is absent.
     */
    public static function get(string $key, mixed $default = null): mixed {
        if (!self::$loaded) {
            if (!self::loadCompiledCache()) {
                self::load(basePath('.env'));
            }
        }

        $raw = $_SERVER[$key] ?? $_ENV[$key] ?? self::$cache[$key] ?? null;

        if ($raw === null) {
            return $default;
        }

        return match (strtolower((string) $raw)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $raw,
        };
    }

    /**
     * Overrides a single environment variable (testing only). #AI:set
     *
     * Writes to internal cache only — does not touch $_ENV or putenv().
     * Sets the loaded flag to prevent auto-load from overwriting test values.
     * Call reset() in tearDown() to restore clean state.
     *
     * @param string $key   Environment variable name to override.
     * @param mixed  $value Value to store.
     */
    public static function set(string $key, mixed $value): void {
        self::$cache[$key] = $value;
        self::$loaded      = true;
    }

    /**
     * Returns all cached environment variables as a flat array. #AI:all
     *
     * Auto-loads on first call. Includes values from .env and set() overrides.
     * Does not include $_SERVER or $_ENV values — only the internal cache.
     */
    public static function all(): array {
        if (!self::$loaded) {
            if (!self::loadCompiledCache()) {
                self::load(basePath('.env'));
            }
        }
        return self::$cache;
    }

    /**
     * Attempts to load env values from a pre-compiled PHP array cache. #AI:loadCompiledCache
     *
     * WHY: Pure arrays allow OPcache shared-memory hit with zero parse overhead.
     * Returns false when the cache file is missing or stale (dev mode with
     * APP_DEBUG=true and .env newer than cache), so the caller falls back
     * to parsing .env directly.
     */
    private static function loadCompiledCache(): bool {
        $cachePath = storagePath('config_cache/env.php');
        if (!is_file($cachePath)) {
            return false;
        }

        $debug = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? false;
        if ($debug && is_file(basePath('.env')) && filemtime(basePath('.env')) > filemtime($cachePath)) {
            return false;
        }

        self::$cache  = require $cachePath;
        self::$loaded = true;
        return true;
    }

    /**
     * Clears all cached environment state (testing only). #AI:reset
     *
     * Allows a subsequent load() to re-parse the .env file. Use in tearDown().
     */
    public static function reset(): void {
        self::$cache  = [];
        self::$loaded = false;
    }
}

#AI:class
#AI symbol: Skim\Core\Env
#AI source_path: src/Core/Env.php
#AI title: env
#AI description: Static facade for lazy-loading .env files and accessing typed environment variables with OS-var priority.
#AI role: static environment facade
#AI layer: core
#AI badges: [facade; env; static; zero-dependency; lazy-load]
#AI intro: `Skim\Core\Env` lazy-loads a `.env` file on first `get()` call and provides typed access to environment variables. OS environment variables (`$_SERVER`, `$_ENV`) always take priority over `.env` values, ensuring deployment-injected values are never silently overwritten. No `putenv()` or `$_ENV` mutations — internal cache only.
#AI lifecycle: static facade, .env lazy-loaded on first get() call
#AI test_seam: set(), reset()
#AI invariants: [load() is idempotent — subsequent calls are no-ops; get() auto-loads .env when not yet loaded; Read priority is $_SERVER → $_ENV → internal cache → $default; get() casts 'true', 'false', 'null' strings to native PHP types; missing .env file is silently skipped; No putenv() or $_ENV mutations]
#AI core_behaviors: [Parses KEY=VALUE lines from .env into internal cache only; OS var priority enforced at read time in get(), not at load time; set() writes to cache and sets loaded flag to prevent auto-load clobbering]
#AI warnings: [OS environment variables in $_SERVER or $_ENV always override .env file values — this is intentional]
#AI notes: Own implementation avoiding vlucas/phpdotenv dependency. The parsing logic is approximately 30 lines.
#AI owns: in-memory env cache, loaded flag
#AI entry_points: [get; load; all]
#AI non_goals: [Does not cast numeric strings to int or float; Does not validate .env syntax; Does not support nested or multiline values; Does not expand variable references like ${OTHER_VAR}]
#AI side_effects: [load() populates internal cache only; set() mutates internal cache and sets loaded flag; reset() clears internal cache and loaded flag]
#AI flow: Env::get(key) -> auto-load if !$loaded -> $_SERVER ?? $_ENV ?? cache ?? default -> type cast; Env::load(path) -> parse .env -> cache only
#AI lifecycle_steps: [Env::get(key); -> !$loaded check; -> auto-load basePath('.env'); -> idempotent check; -> is_file check; -> parse lines into cache; -> $_SERVER[$key] ?? $_ENV[$key] ?? Cache[$key] ?? default -> type cast]
#AI section_order: [Read API; Write API; Testing Hooks]
#AI architectural_notes: Own implementation avoids the vlucas/phpdotenv dependency. putenv() removed to avoid process-global mutations — internal cache only.

#AI:load
#AI group: Write API
#AI frequency: low
#AI signature: public static function load(string $path): void
#AI contract: Parses the .env file at the given path into the internal cache only. Idempotent — only the first call has effect. Silently skips missing files. OS variable priority is enforced at read time in get(), not at load time.
#AI param_details: [{name: $path | type: string | required: true | desc: Absolute path to the .env file. Typically basePath('.env').}]
#AI side_effects: [Populates self::$cache with parsed key-value pairs; No $_ENV or putenv() mutations]
#AI warnings: [Idempotent — calling load() a second time with a different path has no effect]
#AI notes: Lines starting with # are treated as comments. Surrounding single or double quotes are stripped from values.

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public static function get(string $key, mixed $default = null): mixed
#AI contract: Returns the environment variable value for the given key. Auto-loads .env on first call when not yet loaded. Read priority: $_SERVER → $_ENV → internal cache → $default. Casts string values 'true', 'false', and 'null' (case-insensitive) to native PHP types.
#AI param_details: [{name: $key | type: string | required: true | desc: Environment variable name.}; {name: $default | type: mixed | required: false | desc: Fallback value returned as-is when the key is not found in any source.}]
#AI return_detail: {type: mixed | desc: The typed value (bool for 'true'/'false', null for 'null', string otherwise) or $default if absent.}
#AI notes: Lookup order is $_SERVER, then $_ENV, then internal cache. Numeric strings are NOT cast to int or float.

#AI:set
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function set(string $key, mixed $value): void
#AI contract: Overrides a single environment variable in the internal cache only. Sets the loaded flag to prevent auto-load from overwriting test values. Does not touch $_ENV or putenv(). Intended for test isolation.
#AI param_details: [{name: $key | type: string | required: true | desc: Environment variable name to override.}; {name: $value | type: mixed | required: true | desc: Value to store. Persists for the process lifetime until reset().}]
#AI side_effects: [Mutates self::$cache; Sets self::$loaded to true]
#AI notes: Override is visible to Env::get() but not to getenv() or $_ENV readers.

#AI:all
#AI group: Read API
#AI frequency: low
#AI signature: public static function all(): array
#AI contract: Returns all cached environment variables as a flat array. Auto-loads on first call. Includes values from .env and set() overrides. Does not include $_SERVER or $_ENV values.
#AI return_detail: {type: array | desc: Flat key-value map of all cached environment variables.}

#AI:loadCompiledCache
#AI group: Read API
#AI frequency: internal
#AI signature: private static function loadCompiledCache(): bool
#AI contract: Attempts to load env values from a pre-compiled PHP array cache at storage/config_cache/env.php. Returns false when the cache file is missing or stale (APP_DEBUG=true and .env newer than cache). Sets loaded flag on success.
#AI return_detail: {type: bool | desc: True if cache was loaded, false if caller should fall back to load().}
#AI side_effects: [Populates self::$cache from compiled file; Sets self::$loaded to true on success]

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears the internal cache and resets the loaded flag, allowing a subsequent load() call to re-parse the .env file. Use in test tearDown().
#AI side_effects: [Clears self::$cache; Sets self::$loaded to false]
