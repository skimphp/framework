<?php declare(strict_types=1);

namespace skim\core;

// Loads all config/*.php files once, merges into a flat map.
// Dot-notation access across all config files: config('db.default.host').
// Config is frozen after boot — no mutations after app::run() starts.
final class config {
    private static array $data   = [];
    private static bool  $booted = false;

    /**
     * @ai-contract scans $dir for *.php files, each must return an array
     * @ai-contract top-level key is the filename without extension: db.php → 'db'
     * @ai-contract idempotent — subsequent calls after boot are no-ops
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
     * @ai-contract dot-notation traversal: 'db.default.host' → $data['db']['default']['host']
     * @ai-contract returns $default if any segment in the path is missing or non-array
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
     * @ai-contract for tests only — inject config values without a file on disk
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
     * @ai-contract for tests only — fully reset config state
     */
    public static function reset(): void {
        self::$data   = [];
        self::$booted = false;
    }

    public static function all(): array {
        return self::$data;
    }
}
