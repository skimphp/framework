<?php declare(strict_types=1);

namespace Skim\Cache;

/**
 * Redis cache driver using the php-redis extension for high-performance caching.
 *
 * Use as the primary cache driver in production when Redis is available.
 * Connection is lazy — the Redis socket opens on the first operation, not at
 * construction. Uses SCAN (not KEYS) for prefix-based flush to avoid blocking
 * the Redis event loop. Tags are supported via Redis sets through tags().
 *
 * Example:
 *   $driver = new RedisDriver(host: '127.0.0.1', port: 6379, prefix: 'skim_');
 *   $driver->set('user:1', $data, 3600);
 *
 * Testing: prefer array_driver for unit tests — redis_driver requires a live Redis.
 *
 * #AI:class
 */
final class RedisDriver implements \Skim\Cache\Driver {
    private ?\Redis $redis = null;

    public function __construct(
        private readonly string  $host     = '127.0.0.1',
        private readonly int     $port     = 6379,
        private readonly ?string $password = null,
        private readonly int     $database = 0,
        private readonly string  $prefix   = 'skim_',
    ) {}

    /**
     * Retrieves a value by key. #AI:get
     *
     * Returns $default when the key is missing or deserialization fails.
     *
     * @param string $key     Cache key to read.
     * @param mixed  $default Fallback returned on miss.
     */
    public function get(string $key, mixed $default = null): mixed {
        $raw = $this->redis()->get($this->prefix . $key);
        if ($raw === false) {
            return $default;
        }
        $data = @unserialize($raw);
        return $data !== false ? $data : $default;
    }

    /**
     * Stores a value by key. #AI:set
     *
     * Uses SETEX when TTL is provided, SET when null. Values are serialized
     * before storage.
     *
     * @param string   $key   Cache key to write.
     * @param mixed    $value Payload to persist.
     * @param int|null $ttl   TTL in seconds, or null for no expiry.
     * @return bool True if Redis confirmed the write.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        $serialized = serialize($value);
        $r          = $this->redis();
        $fullKey   = $this->prefix . $key;
        if ($ttl !== null && $ttl > 0) {
            return (bool) $r->setex($fullKey, $ttl, $serialized);
        }
        return (bool) $r->set($fullKey, $serialized);
    }

    /**
     * Returns true when the key exists. #AI:has
     *
     * @param string $key Cache key to test.
     */
    public function has(string $key): bool {
        return (bool) $this->redis()->exists($this->prefix . $key);
    }

    /**
     * Removes a specific key. #AI:delete
     *
     * @param string $key Exact cache key to remove.
     * @return bool True if Redis confirmed deletion.
     */
    public function delete(string $key): bool {
        return (bool) $this->redis()->del($this->prefix . $key);
    }

    /**
     * Removes all keys matching the given prefix. #AI:flush
     *
     * Uses SCAN instead of KEYS to avoid blocking the Redis event loop
     * on large datasets. Deletes keys in batches of 100.
     *
     * @param string $prefix Key prefix to match.
     * @return bool Always true.
     */
    public function flush(string $prefix): bool {
        $fullPrefix = $this->prefix . $prefix;
        $cursor     = null;
        do {
            $keys = $this->redis()->scan($cursor, $fullPrefix . '*', 100);
            if ($keys === false) {
                break;
            }
            if ($keys !== []) {
                $this->redis()->del(...$keys);
            }
        } while ($cursor !== 0);
        return true;
    }

    /**
     * Clears the selected Redis database unconditionally. #AI:flushAll
     *
     * WARNING: Issues FLUSHDB on the selected database. Destroys ALL data
     * in that database, not just cache keys. Prefer flush('prefix:') in production.
     *
     * @return bool True if Redis confirmed the flush.
     */
    public function flushAll(): bool {
        return (bool) $this->redis()->flushDB();
    }

    /**
     * Returns a tag-scoped proxy for grouped invalidation. #AI:tags
     *
     * Tags use Redis sets to track which keys belong to each tag.
     *
     * Example:
     *   $driver->tags(['users'])->set('user:42', $data, 3600);
     *   $driver->tags(['users'])->flush(); // Removes all keys tagged 'users'
     *
     * @param array $tags Tag identifiers for grouped operations.
     */
    public function tags(array $tags): \Skim\Cache\TaggedRedisDriver {
        return new \Skim\Cache\TaggedRedisDriver($this, $this->redis(), $this->prefix, $tags);
    }

    private function redis(): \Redis {
        if ($this->redis !== null) {
            return $this->redis;
        }

		$r = new \Redis();
        $r->connect($this->host, $this->port, 1.0);

		if ($this->password !== null && $this->password !== '') {
            $r->auth($this->password);
        }

		if ($this->database !== 0) {
            $r->select($this->database);
        }

		return $this->redis = $r;
    }
}

#AI:class
#AI symbol: Skim\Cache\RedisDriver
#AI source_path: src/Cache/RedisDriver.php
#AI title: RedisDriver
#AI description: Redis cache driver using php-redis extension with lazy connection, SCAN-based flush, and tag support.
#AI role: primary cache driver
#AI layer: cache
#AI badges: [driver; cache; redis; lazy-connection; tag-support]
#AI intro: `Skim\Cache\RedisDriver` is the production cache backend. It uses the php-redis extension (not Predis) for 5-10x better performance. Connection is lazy — the socket opens on first operation. Prefix-based flush uses SCAN to avoid blocking Redis. Tags are supported via Redis sets through `tags()`.
#AI lifecycle: lazy connection on first operation, reused for process lifetime
#AI fallback: FileDriver when Redis is unreachable
#AI invariants: [connection is lazy — no socket opened until first operation; flush() uses SCAN not KEYS; values are serialized via PHP serialize(); tags use Redis sets; flushAll() issues FLUSHDB on selected database]
#AI warnings: [flushAll() issues FLUSHDB which destroys ALL data in the selected Redis database, not just cache keys; Prefer flush('prefix:') in production]
#AI notes: Why php-redis over Predis: extension is 5-10x faster, no Composer dependency. Tags use Redis sets: tag→[key1, key2, ...] — flush by tag deletes all member keys.
#AI section_order: [Read API; Write API; Invalidation; Tag Operations; Architecture]
#AI architectural_notes: The driver prefixes all keys with the configured prefix (default 'skim_') to namespace cache entries within a shared Redis instance. SCAN-based flush avoids the KEYS command which blocks the Redis event loop on large datasets.

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Reads the prefixed key from Redis, unserializes the value, and returns it or $default on miss/deserialization failure.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to read.}; {name: $default | type: mixed | required: false | desc: Fallback returned on miss.}]
#AI return_detail: {type: mixed | desc: The cached value or $default.}

#AI:set
#AI group: Write API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value, ?int $ttl = null): bool
#AI contract: Serializes the value and writes to Redis using SETEX (with TTL) or SET (without TTL).
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}; {name: $ttl | type: ?int | required: false | desc: TTL in seconds, or null for no expiry.}]
#AI return_detail: {type: bool | desc: True if Redis confirmed the write.}

#AI:has
#AI group: Read API
#AI frequency: medium
#AI signature: public function has(string $key): bool
#AI contract: Checks key existence via Redis EXISTS command.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to test.}]
#AI return_detail: {type: bool | desc: True if key exists.}

#AI:delete
#AI group: Write API
#AI frequency: medium
#AI signature: public function delete(string $key): bool
#AI contract: Deletes the prefixed key via Redis DEL command.
#AI param_details: [{name: $key | type: string | required: true | desc: Exact cache key to remove.}]
#AI return_detail: {type: bool | desc: True if Redis confirmed deletion.}

#AI:flush
#AI group: Invalidation
#AI frequency: low
#AI signature: public function flush(string $prefix): bool
#AI contract: Uses SCAN to find matching keys in batches of 100, then DEL to remove them. Avoids KEYS which blocks Redis.
#AI param_details: [{name: $prefix | type: string | required: true | desc: Key prefix to match.}]
#AI return_detail: {type: bool | desc: Always true.}

#AI:flushAll
#AI group: Invalidation
#AI frequency: low
#AI signature: public function flushAll(): bool
#AI contract: Issues FLUSHDB on the selected Redis database, destroying all data in that database.
#AI return_detail: {type: bool | desc: True if Redis confirmed the flush.}
#AI warnings: [FLUSHDB destroys ALL data in the selected database, not just cache keys; Prefer flush('prefix:') in production]

#AI:tags
#AI group: Tag Operations
#AI frequency: medium
#AI signature: public function tags(array $tags): TaggedRedisDriver
#AI contract: Returns a tag-scoped proxy that tracks key membership in Redis sets and supports grouped invalidation.
#AI param_details: [{name: $tags | type: array | required: true | desc: Tag identifiers for grouped operations.}]
#AI return_detail: {type: TaggedRedisDriver | desc: Tag-scoped cache proxy.}
