<?php declare(strict_types=1);

namespace Skim\Cache;

/**
 * Cache driver contract implemented by all storage backends.
 *
 * Use when swapping cache backends without changing application code.
 * All three drivers (array, file, redis) implement this interface. The
 * cache facade resolves the configured driver and delegates every call
 * through it. TTL is in seconds; null means no expiry.
 *
 * Example:
 *   class custom_driver implements Driver { ... }
 *   Cache::setDriver(new custom_driver());
 *
 * Testing: implement this interface in a test double and inject via Cache::setDriver().
 *
 * #AI:class
 */
interface Driver {
    /**
     * Retrieves a value by key. #AI:get
     *
     * Returns $default when the key is missing or expired.
     *
     * @param string $key     Cache key to read.
     * @param mixed  $default Fallback returned on miss.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stores a value by key. #AI:set
     *
     * Overwrites existing keys. Null TTL means no expiry.
     *
     * @param string   $key   Cache key to write.
     * @param mixed    $value Payload to persist.
     * @param int|null $ttl   TTL in seconds, or null for no expiry.
     * @return bool True if backend confirmed successful write.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Returns true when the key exists and is not expired. #AI:has
     *
     * @param string $key Cache key to test.
     */
    public function has(string $key): bool;

    /**
     * Removes a specific key. #AI:delete
     *
     * No-ops if the key does not exist.
     *
     * @param string $key Exact cache key to remove.
     * @return bool True if backend confirmed deletion.
     */
    public function delete(string $key): bool;

    /**
     * Removes all keys matching the given prefix. #AI:flush
     *
     * The prefix is required — callers who want a full cache wipe must use
     * flushAll() explicitly.
     *
     * @param string $prefix Key prefix to match (e.g. 'user:' clears all user:* keys).
     * @return bool True if backend confirmed invalidation.
     */
    public function flush(string $prefix): bool;

    /**
     * Clears the entire cache backend unconditionally. #AI:flushAll
     *
     * WARNING: Destroys ALL cached data visible to this driver.
     * Prefer prefix-scoped flush('prefix:') in production.
     *
     * @return bool True if backend confirmed flush.
     */
    public function flushAll(): bool;
}

#AI:class
#AI symbol: Skim\Cache\Driver
#AI source_path: src/Cache/Driver.php
#AI title: driver
#AI description: Cache driver contract implemented by array, file, and redis backends.
#AI role: cache driver interface
#AI layer: cache
#AI badges: [interface; cache; driver; contract]
#AI intro: `Skim\Cache\Driver` is the interface every cache backend must implement. The cache facade resolves the configured driver and delegates all operations through it. Swapping backends requires only a config change or a `setDriver()` call in tests.
#AI lifecycle: implemented by concrete drivers, resolved by cache facade
#AI test_seam: implement interface in test double, inject via Cache::setDriver()
#AI invariants: [get() returns $default on miss; set() overwrites existing keys; null TTL means no expiry; flush() requires non-empty prefix; flushAll() clears everything]
#AI section_order: [Read API; Write API; Invalidation]
#AI architectural_notes: PSR-16 SimpleCache subset — only the methods SKIM actually uses. All drivers implement this; swap driver in config without changing app code.

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Returns the cached value for the key, or $default if the key is absent or expired.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to read.}; {name: $default | type: mixed | required: false | desc: Fallback returned on miss.}]
#AI return_detail: {type: mixed | desc: The cached value or $default.}

#AI:set
#AI group: Write API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value, ?int $ttl = null): bool
#AI contract: Stores a value under the given key. Overwrites existing entries. Null TTL means the entry never expires.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}; {name: $ttl | type: ?int | required: false | desc: TTL in seconds, or null for no expiry.}]
#AI return_detail: {type: bool | desc: True if backend confirmed successful write.}

#AI:has
#AI group: Read API
#AI frequency: medium
#AI signature: public function has(string $key): bool
#AI contract: Returns true when the key exists and has not expired.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to test.}]
#AI return_detail: {type: bool | desc: True if key exists and is valid.}

#AI:delete
#AI group: Write API
#AI frequency: medium
#AI signature: public function delete(string $key): bool
#AI contract: Removes a single key. No-ops if the key does not exist.
#AI param_details: [{name: $key | type: string | required: true | desc: Exact cache key to remove.}]
#AI return_detail: {type: bool | desc: True if backend confirmed deletion.}

#AI:flush
#AI group: Invalidation
#AI frequency: low
#AI signature: public function flush(string $prefix): bool
#AI contract: Removes all keys matching the given prefix. The prefix is required — callers who want a full cache wipe must use flushAll().
#AI param_details: [{name: $prefix | type: string | required: true | desc: Key prefix to match.}]
#AI return_detail: {type: bool | desc: True if backend confirmed invalidation.}

#AI:flushAll
#AI group: Invalidation
#AI frequency: low
#AI signature: public function flushAll(): bool
#AI contract: Clears the entire cache backend unconditionally.
#AI return_detail: {type: bool | desc: True if backend confirmed flush.}
#AI warnings: [Destroys ALL cached data visible to this driver; Prefer prefix-scoped flush in production]
