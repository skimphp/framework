<?php declare(strict_types=1);

namespace skim\cache;

// Tag-scoped proxy over redis_driver.
// Each tag is a Redis set containing all keys associated with that tag.
// flush() removes all keys in the union of tag sets, then clears the sets.
// Why sets: O(1) membership check, atomic sadd, natural TTL via separate key expiry.
final class tagged_redis_driver {
    public function __construct(
        private readonly redis_driver $driver,
        private readonly \Redis       $redis,
        private readonly string       $prefix,
        private readonly array        $tags,
    ) {}

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        foreach ($this->tags as $tag) {
            $this->redis->sadd($this->prefix . 'tag:' . $tag, $this->prefix . $key);
        }
        return $this->driver->set($key, $value, $ttl);
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->driver->get($key, $default);
    }

    /**
     * @ai-contract deletes all keys tagged with any tag in the constructor tag list
     * @ai-contract also deletes the tag sets themselves
     */
    public function flush(): bool {
        foreach ($this->tags as $tag) {
            $tag_key = $this->prefix . 'tag:' . $tag;
            $members = $this->redis->smembers($tag_key);
            if ($members) {
                $this->redis->del(...$members);
            }
            $this->redis->del($tag_key);
        }
        return true;
    }
}
