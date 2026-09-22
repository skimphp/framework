<?php declare(strict_types=1);

namespace Skim\Cache;

/**
 * In-memory cache driver for tests — no persistence, no external dependencies.
 *
 * Use as the cache driver in PHPUnit/Pest tests where speed and isolation matter.
 * Values live only for the current PHP process and are lost when the process ends.
 * TTL is enforced via microtime expiry checks on read. Not suitable for production
 * because state diverges across PHP-FPM workers (same reason APCu is banned).
 *
 * Example:
 *   Cache::setDriver(new ArrayDriver());
 *   Cache::set('key', 'value', 60);
 *   // ... run tests ...
 *   Cache::reset();
 *
 * Testing: this IS the test driver. Inject via Cache::setDriver().
 *
 * #AI:class
 */
final class ArrayDriver implements \Skim\Cache\Driver {
    private array $store = [];
    /**
     * Throws if constructed inside a worker process.
     *
     * array_driver stores state in a PHP array, so it leaks across requests
     * in FrankenPHP worker mode. Use file_driver or redis_driver instead.
     */
    public function __construct() {
        if (defined('WORKER_MODE') && WORKER_MODE) {
            throw new \RuntimeException('array_driver is not safe for worker mode — use file or redis driver.');
        }
    }

    /**
     * Retrieves a value by key. #AI:get
     *
     * Returns $default when the key is missing or expired. Expired keys
     * are lazily removed on read.
     *
     * @param string $key     Cache key to read.
     * @param mixed  $default Fallback returned on miss.
     */
    public function get(string $key, mixed $default = null): mixed {
        if (!$this->has($key)) {
            return $default;
        }
        return $this->store[$key]['value'];
    }

    /**
     * Stores a value by key. #AI:set
     *
     * Overwrites existing keys. Null TTL means the entry never expires.
     *
     * @param string   $key   Cache key to write.
     * @param mixed    $value Payload to persist.
     * @param int|null $ttl   TTL in seconds, or null for no expiry.
     * @return bool Always true.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        $this->store[$key] = [
            'value'   => $value,
            'expires' => $ttl !== null ? microtime(true) + $ttl : null,
        ];
        return true;
    }

    /**
     * Returns true when the key exists and is not expired. #AI:has
     *
     * Lazily removes expired keys on check.
     *
     * @param string $key Cache key to test.
     */
    public function has(string $key): bool {
        if (!isset($this->store[$key])) {
            return false;
        }
        $expires = $this->store[$key]['expires'];
        if ($expires !== null && microtime(true) > $expires) {
            unset($this->store[$key]);
            return false;
        }
        return true;
    }

    /**
     * Removes a specific key. #AI:delete
     *
     * @param string $key Exact cache key to remove.
     * @return bool Always true.
     */
    public function delete(string $key): bool {
        unset($this->store[$key]);
        return true;
    }

    /**
     * Removes all keys matching the given prefix. #AI:flush
     *
     * @param string $prefix Key prefix to match.
     * @return bool Always true.
     */
    public function flush(string $prefix): bool {
        foreach (array_keys($this->store) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->store[$key]);
            }
        }
        return true;
    }

    /**
     * Clears all entries unconditionally. #AI:flushAll
     *
     * WARNING: Destroys all cached data in this driver instance.
     *
     * @return bool Always true.
     */
    public function flushAll(): bool {
        $this->store = [];
        return true;
    }
}

#AI:class
#AI symbol: Skim\Cache\ArrayDriver
#AI source_path: src/Cache/ArrayDriver.php
#AI title: ArrayDriver
#AI description: In-memory cache driver for tests with process-scoped storage and lazy TTL expiry.
#AI role: test cache driver
#AI layer: cache
#AI badges: [driver; cache; in-memory; test-only]
#AI intro: `Skim\Cache\ArrayDriver` stores cache entries in a PHP array. Values exist only for the current process and are never persisted. TTL is enforced via microtime expiry checked lazily on read. This is the default driver for Pest/PHPUnit tests.
#AI lifecycle: process-scoped, resets naturally between requests
#AI test_seam: inject via Cache::setDriver(new ArrayDriver())
#AI invariants: [all operations return true; expired keys are lazily removed on has()/get(); flushAll() clears the entire store]
#AI warnings: [Not suitable for production — state diverges across PHP-FPM workers]
#AI notes: Why not APCu: APCu state is per-process, inconsistent under PHP-FPM multi-worker. ArrayDriver is the safe alternative for tests.
#AI section_order: [Read API; Write API; Invalidation]
#AI architectural_notes: Each entry is stored as ['value' => mixed, 'expires' => ?float]. The driver is intentionally minimal — it exists to make tests fast and isolated.

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Returns the cached value or $default if missing/expired. Delegates to has() for expiry check.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to read.}; {name: $default | type: mixed | required: false | desc: Fallback returned on miss.}]
#AI return_detail: {type: mixed | desc: The cached value or $default.}

#AI:set
#AI group: Write API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value, ?int $ttl = null): bool
#AI contract: Stores value with optional TTL. Null TTL means no expiry. Always returns true.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}; {name: $ttl | type: ?int | required: false | desc: TTL in seconds, or null for no expiry.}]
#AI return_detail: {type: bool | desc: Always true.}

#AI:has
#AI group: Read API
#AI frequency: medium
#AI signature: public function has(string $key): bool
#AI contract: Returns true when the key exists and has not expired. Lazily removes expired keys.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to test.}]
#AI return_detail: {type: bool | desc: True if key exists and is valid.}

#AI:delete
#AI group: Write API
#AI frequency: medium
#AI signature: public function delete(string $key): bool
#AI contract: Removes a single key. No-ops if absent. Always returns true.
#AI param_details: [{name: $key | type: string | required: true | desc: Exact cache key to remove.}]
#AI return_detail: {type: bool | desc: Always true.}

#AI:flush
#AI group: Invalidation
#AI frequency: low
#AI signature: public function flush(string $prefix): bool
#AI contract: Removes all keys whose name starts with the given prefix.
#AI param_details: [{name: $prefix | type: string | required: true | desc: Key prefix to match.}]
#AI return_detail: {type: bool | desc: Always true.}

#AI:__construct
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public function __construct()
#AI contract: Throws if constructed inside a worker process. ArrayDriver stores state in a PHP array, so it leaks across requests in FrankenPHP worker mode.
#AI throws_details: [{type: \RuntimeException | desc: When WORKER_MODE is defined and true.}]
#AI warnings: [Use FileDriver or RedisDriver in worker mode instead]

#AI:flushAll
#AI group: Invalidation
#AI frequency: low
#AI signature: public function flushAll(): bool
#AI contract: Clears the entire in-memory store.
#AI return_detail: {type: bool | desc: Always true.}
#AI warnings: [Destroys all cached data in this driver instance]
