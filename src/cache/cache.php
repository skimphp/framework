<?php declare(strict_types=1);

namespace skim\cache;

use skim\dev\profiler;

// Static facade over the configured cache driver.
// Swap the driver in config/cache.php without changing a single line of app code.
//
// Fallback: if Redis is unreachable on first use, silently switches to file driver.
// Reason: prevents cache failure from cascading into full app failure.
// Lifecycle: driver resolved once per request on first cache call, then reused.
final class cache {
    private static ?driver $instance = null;

    /**
     * @ai-contract returns cached value, runs $default closure on miss, caches the result
     * @ai-contract key convention: 'model:id' or 'model:scope' — makes flush() predictable
     * @ai-contract side-effect records hit/miss in profiler when APP_DEBUG=true
     */
    public static function remember(string $key, int $ttl, callable $default): mixed {
        if (self::has($key)) {
            profiler::cache('remember', $key, hit: true, ttl: $ttl, driver: self::driver_name());
            return self::get($key);
        }

        $value = $default();
        self::set($key, $value, $ttl);
        profiler::cache('remember', $key, hit: false, ttl: $ttl, driver: self::driver_name());
        return $value;
    }

    /**
     * @ai-contract returns cached value, or $default when key absent or expired
     */
    public static function get(string $key, mixed $default = null): mixed {
        $value = self::driver()->get($key, $default);
        $hit   = $value !== $default;
        profiler::cache('get', $key, hit: $hit, driver: self::driver_name());
        return $value;
    }

    /**
     * @ai-contract stores value for $ttl seconds; null ttl = forever (driver-dependent)
     */
    public static function set(string $key, mixed $value, ?int $ttl = null): bool {
        $ttl ??= (int) \skim\core\config::get('cache.ttl', 3600);
        profiler::cache('set', $key, hit: false, ttl: $ttl, driver: self::driver_name());
        return self::driver()->set($key, $value, $ttl);
    }

    /**
     * @ai-contract returns true if key exists and has not expired
     */
    public static function has(string $key): bool {
        return self::driver()->has($key);
    }

    /**
     * @ai-contract removes a single key
     */
    public static function delete(string $key): bool {
        profiler::cache('delete', $key, driver: self::driver_name());
        return self::driver()->delete($key);
    }

    /**
     * @ai-contract removes all keys matching prefix (e.g. 'user:' clears all user keys)
     * @ai-contract flush('') clears everything — prefer prefix-scoped flush in production
     */
    public static function flush(string $prefix = ''): bool {
        profiler::cache('flush', $prefix, driver: self::driver_name());
        return self::driver()->flush($prefix);
    }

    /**
     * @ai-contract alias for flush('')
     */
    public static function flush_all(): bool {
        return self::flush('');
    }

    /**
     * @ai-contract returns tag-scoped proxy — Redis only; throws on non-Redis drivers
     */
    public static function tags(array $tags): tagged_redis_driver {
        $d = self::driver();
        if (!$d instanceof redis_driver) {
            throw new \RuntimeException('Cache tags require the Redis driver.');
        }
        return $d->tags($tags);
    }

    /**
     * @ai-contract for tests — inject a driver instance directly, bypassing config
     */
    public static function set_driver(driver $driver): void {
        self::$instance = $driver;
    }

    /**
     * @ai-contract resets the driver instance — next call re-resolves from config
     */
    public static function reset(): void {
        self::$instance = null;
    }

    // --- internals ---

    private static function driver(): driver {
        return self::$instance ??= self::resolve_driver();
    }

    private static function resolve_driver(): driver {
        $name = \skim\core\config::get('cache.driver', 'array');
        try {
            return self::make_driver($name);
        } catch (\Throwable $e) {
            $fallback = \skim\core\config::get('cache.fallback', 'file');
            if ($fallback !== $name) {
                return self::make_driver($fallback);
            }
            throw $e;
        }
    }

    private static function make_driver(string $name): driver {
        return match ($name) {
            'redis' => new redis_driver(
                host:     (string) \skim\core\config::get('cache.redis.host', '127.0.0.1'),
                port:     (int) \skim\core\config::get('cache.redis.port', 6379),
                password: \skim\core\config::get('cache.redis.password'),
                database: (int) \skim\core\config::get('cache.redis.database', 0),
                prefix:   (string) \skim\core\config::get('cache.prefix', 'skim_'),
            ),
            'file'  => new file_driver(
                path: (string) \skim\core\config::get('cache.file.path', sys_get_temp_dir() . '/skim_cache'),
            ),
            'array' => new array_driver(),
            default => throw new \InvalidArgumentException("Unknown cache driver: {$name}"),
        };
    }

    private static function driver_name(): string {
        return \skim\core\config::get('cache.driver', 'array');
    }
}
