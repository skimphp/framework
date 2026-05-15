<?php declare(strict_types=1);

namespace skim\cache;

// PSR-16 SimpleCacheInterface subset — only the methods SKIM actually uses.
// All drivers implement this; swap driver in config without changing app code.
interface driver {
    /**
     * @ai-contract returns cached value, or $default if key absent or expired
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * @ai-contract stores $value under $key for $ttl seconds (null = forever)
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * @ai-contract returns true if key exists and has not expired
     */
    public function has(string $key): bool;

    /**
     * @ai-contract removes a single key
     */
    public function delete(string $key): bool;

    /**
     * @ai-contract removes all keys with the given prefix (e.g. flush('user:') clears all user caches)
     * @ai-contract flush('') or flush_all() clears the entire cache — use with caution in production
     */
    public function flush(string $prefix = ''): bool;
}
