<?php declare(strict_types=1);

namespace skim\cache;

use skim\dev\profiler;

/**
 * Static facade over the configured cache driver. #AI:class
 *
 * Use when application code should not depend on a specific cache backend.
 * The driver is resolved lazily on first use from config/cache.php and reused
 * for the process lifetime. If the primary driver fails, falls back to
 * config('cache.fallback'). All operations are recorded in profiler::cache.
 * Prefix-based invalidation (flush()) requires a non-empty prefix; full backend
 * clears go through flush_all().
 *
 * Example:
 *   $user = cache::remember("user:{$id}", 3600, fn() => User::find($id));
 *   cache::flush('user:'); // Invalidate all user:* keys
 *   cache::flush_all();    // Wipe everything
 *
 * Testing: Use set_driver() to inject mocks, reset() to clear state.
 */
final class cache {
    private static ?driver $instance = null;

    /**
     * Retrieves a value by key, computing it on miss. #AI:remember
     *
     * Executes $callback only when the key is missing or expired. Stores the
     * result with the given TTL and records a cache miss in the profiler.
     *
     * Example:
     *   $posts = cache::remember('posts:published', 3600, fn() => Post::published()->get());
     *
     * @param string   $key      Cache key. Use stable prefixes like 'user:42'.
     * @param int      $ttl      Time-to-live in seconds.
     * @param callable $callback Producer function, called only on miss.
     * @return mixed The cached or newly computed value.
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
     * Retrieves a value by key. #AI:get
     *
     * Returns $default when the key is missing or expired. Records a profiler
     * cache event with hit/miss status.
     *
     * @param string $key     Cache key to read.
     * @param mixed  $default Fallback value returned on miss.
     */
    public static function get(string $key, mixed $default = null): mixed {
        $value = self::driver()->get($key, $default);
        $hit   = $value !== $default;
        profiler::cache('get', $key, hit: $hit, driver: self::driver_name());
        return $value;
    }

    /**
     * Returns true when the key exists and is not expired. #AI:has
     *
     * @param string $key Cache key to test.
     */
    public static function has(string $key): bool {
        return self::driver()->has($key);
    }

    /**
     * Stores a value by key. #AI:set
     *
     * Overwrites existing keys. When $ttl is null, uses config('cache.ttl')
     * or falls back to 3600 seconds.
     *
     * @param string   $key   Cache key to write.
     * @param mixed    $value Payload to persist.
     * @param int|null $ttl   TTL in seconds, or null for config default.
     * @return bool True if backend confirmed successful write.
     */
    public static function set(string $key, mixed $value, ?int $ttl = null): bool {
        $ttl ??= (int) \skim\core\config::get('cache.ttl', 3600);
        profiler::cache('set', $key, hit: false, ttl: $ttl, driver: self::driver_name());
        return self::driver()->set($key, $value, $ttl);
    }

    /**
     * Removes a specific key from the cache. #AI:delete
     *
     * No-ops if the key does not exist. Records the delete in profiler.
     *
     * @param string $key Exact cache key to remove.
     * @return bool True if backend confirmed deletion.
     */
    public static function delete(string $key): bool {
        profiler::cache('delete', $key, driver: self::driver_name());
        return self::driver()->delete($key);
    }

    /**
     * Removes keys matching the given prefix. #AI:flush
     *
     * WARNING: Prefix is required. Use flush_all() to clear the entire backend.
     * Calling with an empty string will trigger a PHP ArgumentCountError.
     *
     * Example:
     *   cache::flush('user:'); // Removes all user:* keys
     *
     * @param string $prefix Key prefix to match (e.g. 'user:').
     * @return bool True if backend confirmed invalidation.
     */
    public static function flush(string $prefix): bool {
        profiler::cache('flush', $prefix, driver: self::driver_name());
        return self::driver()->flush($prefix);
    }

    /**
     * Clears the entire active backend unconditionally. #AI:flush_all
     *
     * WARNING: Destroys all cached data across all contexts sharing this driver.
     * Prefer flush('prefix:') in production for targeted invalidation.
     * With Redis, this issues FLUSHDB on the selected database.
     *
     * @return bool True if backend confirmed flush.
     */
    public static function flush_all(): bool {
        profiler::cache('flush_all', '', driver: self::driver_name());
        return self::driver()->flush_all();
    }

    /**
     * Returns a Redis tag-scoped cache proxy. #AI:tags
     *
     * Tags group cache entries for bulk invalidation. Only available when the
     * active driver is redis_driver.
     *
     * Example:
     *   cache::tags(['users'])->set('user:42:profile', $data, 3600);
     *   cache::tags(['users'])->flush(); // All entries tagged 'users'
     *
     * @param array $tags Tag identifiers for grouped operations.
     * @throws \RuntimeException If the active driver is not redis_driver.
     */
    public static function tags(array $tags): tagged_redis_driver {
        $d = self::driver();
        if (!$d instanceof redis_driver) {
            throw new \RuntimeException('Cache tags require the Redis driver.');
        }
        return $d->tags($tags);
    }

    /**
     * Injects a custom driver instance (testing only). #AI:set_driver
     *
     * Use in PHPUnit to bypass config-based resolution. Call reset() in
     * tearDown() to restore normal behavior.
     *
     * Example:
     *   cache::set_driver(new array_driver());
     *   // ... run tests ...
     *   cache::reset();
     *
     * @param driver $driver Mock or fake driver for testing.
     */
    public static function set_driver(driver $driver): void {
        self::$instance = $driver;
    }

    /**
     * Drops the cached driver instance. #AI:reset
     *
     * Forces re-resolution from config on the next cache call. Use in test
     * tearDown() after set_driver() or when cache config changes at runtime.
     */
    public static function reset(): void {
        self::$instance = null;
    }

    /**
     * Returns the active driver, resolving it once per lifecycle. #AI:driver
     *
     * Private — internal subsystems only.
     */
    private static function driver(): driver {
        return self::$instance ??= self::resolve_driver();
    }

    /**
     * Builds the primary driver, falling back on failure. #AI:resolve_driver
     *
     * Falls back to config('cache.fallback') when the primary driver's
     * constructor throws. Runtime failures inside a live driver are not caught.
     */
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

    /**
     * Maps driver name to a concrete instance. #AI:make_driver
     *
     * @param string $name Driver name from config (redis, file, array).
     * @throws \InvalidArgumentException If the driver name is unsupported.
     */
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

    /**
     * Returns the configured primary driver name for profiling. #AI:driver_name
     *
     * May differ from the actual active driver after set_driver() or fallback.
     */
    private static function driver_name(): string {
        return \skim\core\config::get('cache.driver', 'array');
    }
}

#AI:class
#AI symbol: skim\cache\cache
#AI source_path: src/cache/cache.php
#AI title: cache
#AI description: Static cache facade for configured drivers, fail-soft fallback, tag support, and test driver injection.
#AI role: static cache facade
#AI layer: cache
#AI badges: [facade; cache; driver-backed; fail-soft]
#AI intro: Static entry point for cache operations. Resolves the configured driver on first use and reuses it for the process lifetime. Falls back to a secondary driver when the primary cannot be created.
#AI lifecycle: static facade, driver resolved on first cache call
#AI fallback: cache.fallback when cache.driver cannot be created
#AI test_seam: set_driver(), reset()
#AI drivers: [array; file; redis]
#AI invariants: [driver is resolved once and reused until reset() or set_driver(); get() returns default on miss; remember() computes and stores only on miss; tags() requires redis_driver; flush() requires non-empty prefix]
#AI core_behaviors: [The facade resolves one configured driver and exposes a single cache API; Cache reads, writes, misses, and invalidation are recorded in profiler; Redis tags are available only when active driver is redis_driver]
#AI warnings: [flush_all() clears the entire active cache backend; flush() requires a non-empty prefix; Prefer prefix-based invalidation such as flush('user:') in production]
#AI notes: The resolved driver is process-local. If tests change cache config at runtime, call reset() before the next cache operation.
#AI scope_items: [{name: array | mutable: true | desc: In-memory cache driver. Values exist only for the current process and are not persisted.}; {name: file | mutable: true | desc: Filesystem-backed cache driver. Values are persisted on disk.}; {name: redis | mutable: true | desc: Redis-backed cache driver. Required for tag-scoped cache operations through cache::tags().}]
#AI owns: driver instance cache
#AI entry_points: [remember; get; set; has; delete; flush; flush_all; tags]
#AI config_reads: [cache.driver; cache.fallback; cache.ttl; cache.redis.*; cache.prefix; cache.file.path]
#AI non_goals: [Does not expose backend-specific APIs except Redis tags; Does not handle serialization; Fallback protects app availability, not cache consistency guarantees]
#AI side_effects: [profiler::cache records cache operations; set_driver() replaces active driver; reset() forces re-resolution]
#AI flow: cache::method() -> driver() -> resolve_driver() [primary -> fallback] -> concrete driver -> profiler
#AI lifecycle_steps: [cache::remember() / get() / set() / has(); -> driver(); -> cached driver instance?; -> resolve_driver(); -> make_driver(config cache.driver); -> on failure make_driver(config cache.fallback); -> concrete driver operation; -> profiler::cache(...)]
#AI section_order: [Read API; Write API; Invalidation; Tag Operations; Testing Hooks; Architecture]
#AI architectural_notes: The facade keeps cache usage stable while backend selection remains in config/cache.php. The flush()/flush_all() split forces callers to explicitly choose between targeted and destructive invalidation.

#AI:remember
#AI group: Read API
#AI frequency: high
#AI signature: public static function remember(string $key, int $ttl, callable $default): mixed
#AI contract: Returns the cached value when the key exists. On a miss, executes the callback, stores the returned value with the provided TTL, records the miss in profiler, and returns the computed value.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key. Use stable prefixes such as user:42 or posts:published so related entries can be invalidated by prefix.}; {name: $ttl | type: int | required: true | desc: Time to live in seconds for the computed value.}; {name: $default | type: callable | required: true | desc: Callback executed only when the key is missing or expired.}]
#AI return_detail: {type: mixed | desc: The cached or newly computed value.}
#AI side_effects: Writes to cache backend only when key is missing or expired.
#AI notes: Use remember() for expensive reads when recomputing the value on cache miss is safe and deterministic.

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public static function get(string $key, mixed $default = null): mixed
#AI contract: Returns the cached value for the key. If the key is missing or expired, returns $default. Each read records a profiler cache event with hit or miss status.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to read.}; {name: $default | type: mixed | required: false | desc: Fallback value returned when the key is missing or expired.}]
#AI return_detail: {type: mixed | desc: The cached value or $default.}

#AI:has
#AI group: Read API
#AI frequency: medium
#AI signature: public static function has(string $key): bool
#AI contract: Returns true when the active driver contains a non-expired value for the key.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to test.}]
#AI return_detail: {type: bool | desc: True if key exists and is valid.}

#AI:set
#AI group: Write API
#AI frequency: high
#AI signature: public static function set(string $key, mixed $value, ?int $ttl = null): bool
#AI contract: Stores a value in the active driver. When $ttl is null, the facade uses cache.ttl from config and falls back to 3600 seconds when that value is not set.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to write.}; {name: $value | type: mixed | required: true | desc: Value to store. Must be supported by the active driver.}; {name: $ttl | type: ?int | required: false | desc: Optional TTL in seconds. null means use the configured default TTL.}]
#AI return_detail: {type: bool | desc: True if backend confirmed successful write.}
#AI side_effects: Writes payload to the active cache backend.

#AI:delete
#AI group: Write API
#AI frequency: medium
#AI signature: public static function delete(string $key): bool
#AI contract: Removes one exact cache key from the active driver and records the delete operation in profiler.
#AI param_details: [{name: $key | type: string | required: true | desc: Exact cache key to remove.}]
#AI return_detail: {type: bool | desc: True if backend confirmed deletion.}
#AI side_effects: Mutates cache backend by removing key.

#AI:flush
#AI group: Invalidation
#AI frequency: low
#AI signature: public static function flush(string $prefix): bool
#AI contract: Removes keys that match the given prefix. The prefix is required — callers who want a full cache wipe must use flush_all().
#AI param_details: [{name: $prefix | type: string | required: true | desc: Key prefix to match. Use a non-empty prefix like user: for safer invalidation.}]
#AI return_detail: {type: bool | desc: True if backend confirmed successful invalidation.}
#AI side_effects: Mass deletion in cache backend for matching keys.
#AI warnings: [Prefix is required. Trying to flush with an empty string triggers a PHP ArgumentCountError — use flush_all() instead.]

#AI:flush_all
#AI group: Invalidation
#AI frequency: low
#AI signature: public static function flush_all(): bool
#AI contract: Unconditionally wipes the entire cache backend by delegating to the driver's flush_all(). Records the operation in profiler.
#AI return_detail: {type: bool | desc: True if backend confirmed flush.}
#AI warnings: [Destroys all cached data across all application contexts sharing this driver; Triggers immediate re-computation on next read; Prefer flush('prefix:') in production]
#AI side_effects: Mass deletion of all entries in cache backend.

#AI:tags
#AI group: Tag Operations
#AI frequency: medium
#AI signature: public static function tags(array $tags): tagged_redis_driver
#AI contract: Returns a Redis tag-scoped cache proxy for the given tags. Available only when the active driver is redis_driver.
#AI param_details: [{name: $tags | type: array | required: true | desc: Tag identifiers used to scope subsequent cache operations.}]
#AI return_detail: {type: tagged_redis_driver | desc: Tag-scoped cache proxy.}
#AI throws_details: [{type: RuntimeException | desc: Thrown when the active driver is not redis_driver.}]

#AI:set_driver
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function set_driver(driver $driver): void
#AI contract: Replaces the active driver instance directly. Use in tests to bypass config-based resolution and external services.
#AI param_details: [{name: $driver | type: driver | required: true | desc: Driver implementation used for subsequent cache calls.}]
#AI side_effects: Mutates static driver state.

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears the cached driver instance. The next cache call resolves the driver again from config.
#AI side_effects: Clears static driver state.

#AI:driver
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function driver(): driver
#AI contract: Returns the cached driver instance, resolving and caching it lazily if null.
#AI notes: Private for internal subsystem access.

#AI:resolve_driver
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function resolve_driver(): driver
#AI contract: Builds the primary driver from config, falls back to the configured secondary on exception.

#AI:make_driver
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function make_driver(string $name): driver
#AI contract: Maps string config names to concrete driver instances (redis, file, array).
#AI param_details: [{name: $name | type: string | required: true | desc: Driver name from config (redis, file, array).}]
#AI throws_details: [{type: InvalidArgumentException | desc: If driver name is unsupported.}]

#AI:driver_name
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function driver_name(): string
#AI contract: Returns the configured cache.driver value for profiler metadata. May differ from the actual active driver after set_driver() or fallback.
