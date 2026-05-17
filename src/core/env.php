<?php declare(strict_types=1);

namespace skim\core;

// Parses .env once per process, caches in memory.
// Own implementation — avoids vlucas/phpdotenv dependency.
// Reason: .env parsing is trivial; adding a dependency for 30 lines is wasteful.
final class env {
    private static array $cache = [];
    private static bool  $loaded = false;

    /**
     * @ai-contract loads .env file from root path, populates $_ENV and internal cache
     * @ai-contract idempotent — safe to call multiple times, only parses once
     * @ai-contract silently skips missing .env file — .env is optional in production
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
     * @ai-contract returns env variable value, or $default if not set
     * @ai-contract casts 'true'/'false'/'null' strings to their native PHP types
     * @ai-contract $default can be any type — returned as-is when key absent
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
     * @ai-contract for tests only — override a single key without touching the .env file
     * @ai-contract side-effect permanent for the lifetime of the process
     */
    public static function set(string $key, mixed $value): void {
        self::$cache[$key] = $value;
        $_ENV[$key]        = $value;
    }

    /**
     * @ai-contract for tests only — reset env state so a new load() can run
     */
    public static function reset(): void {
        self::$cache  = [];
        self::$loaded = false;
    }
}
