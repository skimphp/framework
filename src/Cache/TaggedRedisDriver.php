<?php declare(strict_types=1);

namespace Skim\Cache;

/**
 * Tag-scoped proxy over redis_driver for grouped cache invalidation.
 *
 * Use when cache entries should be invalidated as a group (e.g., all entries
 * related to "users"). Each tag is a Redis set containing the prefixed keys
 * associated with that tag. On set(), the key is added to every tag's set via
 * SADD. On flush(), all keys in the union of tag sets are deleted, then the
 * tag sets themselves are removed.
 *
 * Example:
 *   Cache::tags(['users'])->set('user:42:profile', $data, 3600);
 *   Cache::tags(['users'])->flush(); // Deletes user:42:profile and all tagged keys
 *
 * Testing: requires a live Redis connection. Use array_driver for unit tests.
 *
 * #AI:class
 */
final class TaggedRedisDriver {
    public function __construct(
        private readonly \Skim\Cache\RedisDriver $driver,
        private readonly \Redis       $redis,
        private readonly string       $prefix,
        private readonly array        $tags,
    ) {}

    /**
     * Stores a value and registers it in all tag sets. #AI:set
     *
     * Adds the prefixed key to each tag's Redis set via SADD, then delegates
     * the actual write to the underlying redis_driver.
     *
     * @param string   $key   Cache key to write.
     * @param mixed    $value Payload to persist.
     * @param int|null $ttl   TTL in seconds, or null for no expiry.
     * @return bool True if backend confirmed successful write.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        foreach ($this->tags as $tag) {
            $this->redis->sadd($this->prefix . 'tag:' . $tag, $this->prefix . $key);
        }
        return $this->driver->set($key, $value, $ttl);
    }

    /**
     * Retrieves a value by key. #AI:get
     *
     * Delegates directly to the underlying redis_driver — tags do not affect reads.
     *
     * @param string $key     Cache key to read.
     * @param mixed  $default Fallback returned on miss.
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->driver->get($key, $default);
    }

    /**
     * Deletes all keys in the union of tag sets, then clears the sets. #AI:flush
     *
     * WARNING: Removes every key associated with any of the constructor tags.
     * This is a destructive bulk operation — use with specific tag names only.
     *
     * Example:
     *   Cache::tags(['users', 'posts'])->flush(); // All keys tagged 'users' OR 'posts'
     *
     * @return bool Always true.
     */
    public function flush(): bool {
        foreach ($this->tags as $tag) {
            $tagKey = $this->prefix . 'tag:' . $tag;
            $members = $this->redis->smembers($tagKey);
            if ($members) {
                $this->redis->del(...$members);
            }
            $this->redis->del($tagKey);
        }
        return true;
    }
}

#AI:class
#AI symbol: Skim\Cache\TaggedRedisDriver
#AI source_path: src/Cache/TaggedRedisDriver.php
#AI title: TaggedRedisDriver
#AI description: Tag-scoped proxy over RedisDriver for grouped cache invalidation via Redis sets.
#AI role: tag-scoped cache proxy
#AI layer: cache
#AI badges: [proxy; cache; redis; tags; bulk-invalidation]
#AI intro: `Skim\Cache\TaggedRedisDriver` is a proxy returned by `Cache::tags()`. It wraps the RedisDriver and adds tag membership tracking via Redis sets. On `set()`, the key is SADD'd to each tag's set. On `flush()`, all member keys are deleted along with the tag sets themselves.
#AI lifecycle: created per Cache::tags() call, no persistent state beyond Redis sets
#AI invariants: [set() adds key to all tag sets before writing; get() delegates to driver without tag awareness; flush() deletes member keys then tag sets]
#AI warnings: [flush() is destructive — removes all keys associated with any constructor tag; Requires live Redis connection]
#AI notes: Why Redis sets: O(1) membership check, atomic SADD, natural TTL via separate key expiry. This class does NOT implement the driver interface — it's a proxy with a reduced API (set, get, flush only).
#AI section_order: [Read API; Write API; Invalidation]
#AI architectural_notes: Each tag maps to a Redis set at key `{prefix}tag:{tag_name}`. The set members are the full prefixed cache keys. flush() uses SMEMBERS + DEL, which is acceptable for tag-scoped invalidation at application scale.

#AI:set
#AI group: Write API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value, ?int $ttl = null): bool
#AI contract: Adds the prefixed key to each tag's Redis set via SADD, then delegates the write to the underlying RedisDriver.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}; {name: $ttl | type: ?int | required: false | desc: TTL in seconds, or null for no expiry.}]
#AI return_detail: {type: bool | desc: True if backend confirmed successful write.}
#AI side_effects: [Adds key to Redis tag sets via SADD]

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Delegates directly to the underlying RedisDriver. Tags do not affect reads.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to read.}; {name: $default | type: mixed | required: false | desc: Fallback returned on miss.}]
#AI return_detail: {type: mixed | desc: The cached value or $default.}

#AI:flush
#AI group: Invalidation
#AI frequency: medium
#AI signature: public function flush(): bool
#AI contract: For each constructor tag, reads all member keys from the Redis set via SMEMBERS, deletes them, then deletes the tag set itself.
#AI return_detail: {type: bool | desc: Always true.}
#AI warnings: [Destructive — removes all keys associated with any constructor tag; Deletes the tag sets themselves]
#AI side_effects: [Mass deletion of tagged keys in Redis; Removes tag sets]
