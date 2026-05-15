<?php declare(strict_types=1);

namespace skim\dev;

// Static event collector — records DB queries, cache ops, view renders, log entries.
// All methods are no-ops when APP_DEBUG=false — zero overhead in production.
// db.php, cache.php, view.php, and log.php call these after every operation.
//
// Lifecycle: created empty at boot, populated during request, read by toolbar renderer,
// then reset between requests (or in tests via profiler::reset()).
final class profiler {
    private static array $events   = [];
    private static bool  $enabled  = false;

    public static function enable(): void  { self::$enabled = true; }
    public static function disable(): void { self::$enabled = false; }

    /**
     * @ai-contract called by db.php after every query (interpolated SQL, not raw template)
     * @ai-contract no-op when APP_DEBUG=false
     */
    public static function db(string $sql, float $ms, string $connection = 'default', int $rows = 0): void {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = ['type' => 'db', 'sql' => $sql, 'ms' => $ms, 'connection' => $connection, 'rows' => $rows];
    }

    /**
     * @ai-contract called by cache.php on every get/set/delete/remember
     * @ai-contract no-op when APP_DEBUG=false
     */
    public static function cache(string $op, string $key, bool $hit = false, ?int $ttl = null, string $driver = 'redis'): void {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = ['type' => 'cache', 'op' => $op, 'key' => $key, 'hit' => $hit, 'ttl' => $ttl, 'driver' => $driver];
    }

    /**
     * @ai-contract called by view.php after every template render
     * @ai-contract no-op when APP_DEBUG=false
     */
    public static function view(string $template, ?string $fragment = null, float $ms = 0.0): void {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = ['type' => 'view', 'template' => $template, 'fragment' => $fragment, 'ms' => $ms];
    }

    /**
     * @ai-contract called by log::* methods with file+line from debug_backtrace()
     * @ai-contract no-op when APP_DEBUG=false
     */
    public static function log(string $level, string $message, array $context = [], string $file = '', int $line = 0): void {
        if (!self::$enabled) {
            return;
        }
        self::$events[] = ['type' => 'log', 'level' => $level, 'message' => $message, 'context' => $context, 'file' => $file, 'line' => $line];
    }

    /**
     * @ai-contract returns aggregate summary for toolbar badge display
     */
    public static function summary(): array {
        $db_events    = array_filter(self::$events, fn($e) => $e['type'] === 'db');
        $cache_events = array_filter(self::$events, fn($e) => $e['type'] === 'cache');

        return [
            'db'    => ['count' => count($db_events),    'ms' => round(array_sum(array_column($db_events, 'ms')), 2)],
            'cache' => [
                'hits'   => count(array_filter($cache_events, fn($e) => $e['hit'])),
                'misses' => count(array_filter($cache_events, fn($e) => !$e['hit'])),
            ],
            'views' => count(array_filter(self::$events, fn($e) => $e['type'] === 'view')),
            'logs'  => count(array_filter(self::$events, fn($e) => $e['type'] === 'log')),
        ];
    }

    /**
     * @ai-contract returns all raw events — used by toolbar renderer
     */
    public static function events(): array {
        return self::$events;
    }

    /**
     * @ai-contract clears event buffer — call in tests between requests
     * @ai-contract does NOT disable profiler — only clears collected data
     */
    public static function reset(): void {
        self::$events = [];
    }
}
