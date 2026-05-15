<?php declare(strict_types=1);

namespace skim\cache;

// Redis cache driver. Uses php-redis extension (not Predis).
// Tags use Redis sets: tag→[key1, key2, ...] — flush by tag deletes all member keys.
// Why php-redis over Predis: extension is 5–10x faster, no Composer dependency.
final class redis_driver implements driver {
    private ?\Redis $redis = null;

    public function __construct(
        private readonly string  $host     = '127.0.0.1',
        private readonly int     $port     = 6379,
        private readonly ?string $password = null,
        private readonly int     $database = 0,
        private readonly string  $prefix   = 'skim_',
    ) {}

    public function get(string $key, mixed $default = null): mixed {
        $raw = $this->redis()->get($this->prefix . $key);
        if ($raw === false) {
            return $default;
        }
        $data = @unserialize($raw);
        return $data !== false ? $data : $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        $serialized = serialize($value);
        $r          = $this->redis();
        $full_key   = $this->prefix . $key;
        if ($ttl !== null && $ttl > 0) {
            return (bool) $r->setex($full_key, $ttl, $serialized);
        }
        return (bool) $r->set($full_key, $serialized);
    }

    public function has(string $key): bool {
        return (bool) $this->redis()->exists($this->prefix . $key);
    }

    public function delete(string $key): bool {
        return (bool) $this->redis()->del($this->prefix . $key);
    }

    public function flush(string $prefix = ''): bool {
        if ($prefix === '') {
            return (bool) $this->redis()->flushDB();
        }
        // Scan for matching keys — avoids KEYS command which blocks Redis
        $full_prefix = $this->prefix . $prefix;
        $cursor      = null;
        do {
            $result = $this->redis()->scan($cursor, $full_prefix . '*', 100);
            if ($result === false) {
                break;
            }
            [$cursor, $keys] = $result;
            if ($keys !== []) {
                $this->redis()->del(...$keys);
            }
        } while ($cursor !== 0);
        return true;
    }

    /**
     * @ai-contract returns a tag-scoped proxy that adds tag membership on set/flush
     */
    public function tags(array $tags): tagged_redis_driver {
        return new tagged_redis_driver($this, $this->redis(), $this->prefix, $tags);
    }

    private function redis(): \Redis {
        if ($this->redis !== null) {
            return $this->redis;
        }
        $r = new \Redis();
        $r->connect($this->host, $this->port, 1.0);
        if ($this->password !== null) {
            $r->auth($this->password);
        }
        if ($this->database !== 0) {
            $r->select($this->database);
        }
        return $this->redis = $r;
    }
}
