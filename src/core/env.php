<?php declare(strict_types=1);

namespace skim\core;

/**
 * Parses .env files and provides typed access to environment variables.
 *
 * Use when reading configuration that varies between environments without
 * adding the vlucas/phpdotenv dependency — this is 30 lines of trivial parsing.
 * Loads once per process; OS environment variables always take priority over
 * .env values. Missing .env files are silently skipped.
 *
 * Example:
 *   env::load(dirname(__DIR__) . '/.env');
 *   $debug = env::get('APP_DEBUG', false);
 *
 * Testing: Use set() to override keys, reset() to clear state between tests.
 *
 * #AI:class
 */
final class env {
    private static array $cache = [];
    private static bool  $loaded = false;

    /**
     * Parses a .env file and populates the internal cache and $_ENV. #AI:load
     *
     * Idempotent — subsequent calls are no-ops. Silently skips missing files
     * (.env is optional in production). OS environment variables set by Docker,
     * CI, or the shell always take priority over .env values.
     *
     * WARNING: OS env vars override .env values intentionally. If a key exists
     * in getenv() or $_ENV, the .env value for that key is ignored. This prevents
     * docker-compose `environment:` values from being silently overwritten.
     *
     * Example:
     *   env::load(dirname(__DIR__) . '/.env');
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
            // Skip comments and lines without '='
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);

            // Strip surrounding quotes: "value" or 'value'
            if (
                (str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            // OS environment variables (set by Docker, CI, or the shell) take priority
            // over .env file values. .env is a local-dev convenience fallback — it should
            // never override what the deployment environment explicitly injected.
            // Without this check, docker-compose `environment:` values would be silently
            // overwritten every time the .env file exists on disk.
            if (getenv($key) !== false || isset($_ENV[$key])) {
                self::$cache[$key] = getenv($key) !== false ? getenv($key) : $_ENV[$key];
                continue;
            }

            self::$cache[$key] = $val;
            $_ENV[$key]        = $val;
            putenv("{$key}={$val}");
        }
    }

    /**
     * Returns an environment variable value with automatic type casting. #AI:get
     *
     * Casts string values 'true', 'false', and 'null' to their native PHP types.
     * Falls back through internal cache, $_ENV, and getenv() in that order.
     *
     * @param string $key     Environment variable name.
     * @param mixed  $default Returned as-is when the key is absent.
     */
    public static function get(string $key, mixed $default = null): mixed {
        $raw = self::$cache[$key] ?? $_ENV[$key] ?? getenv($key);

        if ($raw === false || $raw === null) {
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
     * Writes to both the internal cache and $_ENV. Does not touch the .env file.
     * Call reset() in tearDown() to restore clean state.
     *
     * @param string $key   Environment variable name to override.
     * @param mixed  $value Value to store.
     */
    public static function set(string $key, mixed $value): void {
        self::$cache[$key] = $value;
        $_ENV[$key]        = $value;
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
#AI symbol: skim\core\env
#AI source_path: src/core/env.php
#AI title: env
#AI description: Static facade for parsing .env files and accessing typed environment variables with OS-var priority.
#AI role: static environment facade
#AI layer: core
#AI badges: [facade; env; static; zero-dependency]
#AI intro: `skim\core\env` parses a `.env` file once per process and provides typed access to environment variables. OS environment variables always take priority over `.env` values, ensuring deployment-injected values are never silently overwritten.
#AI lifecycle: static facade, .env parsed once on first load() call
#AI test_seam: set(), reset()
#AI invariants: [load() is idempotent — subsequent calls are no-ops; OS env vars take priority over .env file values; get() casts 'true', 'false', 'null' strings to native PHP types; missing .env file is silently skipped]
#AI core_behaviors: [Parses KEY=VALUE lines from .env, stripping quotes and comments; Populates internal cache, $_ENV, and putenv() for new keys; OS env vars are read into cache but never overwritten by .env values]
#AI warnings: [OS environment variables set by Docker, CI, or the shell always override .env file values — this is intentional but may surprise developers expecting .env to win]
#AI notes: Own implementation avoiding vlucas/phpdotenv dependency. The parsing logic is approximately 30 lines.
#AI owns: in-memory env cache, loaded flag
#AI entry_points: [load; get]
#AI non_goals: [Does not cast numeric strings to int or float; Does not validate .env syntax; Does not support nested or multiline values; Does not expand variable references like ${OTHER_VAR}]
#AI side_effects: [load() populates $_ENV and calls putenv() for new keys; set() mutates $_ENV and internal cache; reset() clears internal cache and loaded flag]
#AI flow: env::load(path) -> parse .env -> OS var check -> cache + $_ENV + putenv(); env::get(key) -> cache ?? $_ENV ?? getenv() -> type cast
#AI lifecycle_steps: [env::load(path); -> idempotent check (self::$loaded); -> is_file check; -> parse lines; -> OS var priority check; -> cache + $_ENV + putenv]
#AI section_order: [Read API; Write API; Testing Hooks]
#AI architectural_notes: Own implementation avoids the vlucas/phpdotenv dependency. Environment parsing is trivial and does not warrant an external package.

#AI:load
#AI group: Write API
#AI frequency: low
#AI signature: public static function load(string $path): void
#AI contract: Parses the .env file at the given path and populates the internal cache, $_ENV, and putenv(). Idempotent — only the first call has effect. Silently skips missing files. OS environment variables take priority over .env values.
#AI param_details: [{name: $path | type: string | required: true | desc: Absolute path to the .env file. Typically dirname(__DIR__) . '/.env' from public/index.php.}]
#AI side_effects: [Populates self::$cache with parsed key-value pairs; Writes to $_ENV superglobal; Calls putenv() for keys not already set in the OS environment]
#AI warnings: [OS environment variables override .env values — docker-compose environment: block wins over .env file; Idempotent — calling load() a second time with a different path has no effect]
#AI notes: Lines starting with # are treated as comments. Surrounding single or double quotes are stripped from values.

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public static function get(string $key, mixed $default = null): mixed
#AI contract: Returns the environment variable value for the given key. Casts string values 'true', 'false', and 'null' (case-insensitive) to their native PHP types. Returns $default when the key is absent from all sources.
#AI param_details: [{name: $key | type: string | required: true | desc: Environment variable name.}; {name: $default | type: mixed | required: false | desc: Fallback value returned as-is when the key is not found in cache, $_ENV, or getenv().}]
#AI return_detail: {type: mixed | desc: The typed value (bool for 'true'/'false', null for 'null', string otherwise) or $default if absent.}
#AI notes: Lookup order is internal cache, then $_ENV, then getenv(). Numeric strings are NOT cast to int or float.

#AI:set
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function set(string $key, mixed $value): void
#AI contract: Overrides a single environment variable in both the internal cache and $_ENV. Does not modify the .env file on disk. Intended for test isolation.
#AI param_details: [{name: $key | type: string | required: true | desc: Environment variable name to override.}; {name: $value | type: mixed | required: true | desc: Value to store. Persists for the process lifetime until reset().}]
#AI side_effects: [Mutates self::$cache and $_ENV superglobal]
#AI notes: Does not call putenv() — the override is visible to env::get() but not to getenv().

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears the internal cache and resets the loaded flag, allowing a subsequent load() call to re-parse the .env file. Use in test tearDown().
#AI side_effects: [Clears self::$cache; Sets self::$loaded to false]
#AI notes: Does not remove values from $_ENV or putenv(). Previously loaded or set values remain in the OS environment.
