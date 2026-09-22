<?php declare(strict_types=1);

namespace Skim\Cache;

/**
 * Filesystem cache driver — each key stored as one serialized file on disk.
 *
 * Use as the fallback driver when Redis is unavailable, or for local development
 * without external services. TTL is tracked via a serialized expiry timestamp
 * inside each file (not file mtime, which is unreliable on some filesystems).
 * Falls back gracefully when the directory is not writable: set() returns false,
 * get() returns the default value.
 *
 * Example:
 *   $driver = new FileDriver(path: sys_get_temp_dir() . '/skim_cache');
 *   $driver->set('user:1', $userData, 3600);
 *
 * Testing: prefer array_driver for tests — file_driver is slower and touches disk.
 *
 * #AI:class
 */
final class FileDriver implements \Skim\Cache\Driver {
    public function __construct(private readonly string $path) {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * Retrieves a value by key. #AI:get
     *
     * Returns $default when the file is missing, unreadable, or expired.
     * Expired files are deleted on read.
     *
     * @param string $key     Cache key to read.
     * @param mixed  $default Fallback returned on miss.
     */
    public function get(string $key, mixed $default = null): mixed {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return $default;
        }

        $data = @unserialize((string) file_get_contents($file));
        if ($data === false) {
            return $default;
        }

        [$expires, $value] = $data;
        if ($expires !== null && microtime(true) > $expires) {
            @unlink($file);
            return $default;
        }

        return $value;
    }

    /**
     * Stores a value by key. #AI:set
     *
     * Overwrites existing keys. Uses LOCK_EX for atomic writes.
     * Returns false when the directory is not writable.
     *
     * @param string   $key   Cache key to write.
     * @param mixed    $value Payload to persist.
     * @param int|null $ttl   TTL in seconds, or null for no expiry.
     * @return bool True if file was written successfully.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        $file    = $this->filePath($key);
        $expires = $ttl !== null ? microtime(true) + $ttl : null;
        $content = serialize([$expires, $value]);
        return (bool) @file_put_contents($file, $content, \LOCK_EX);
    }

    /**
     * Returns true when the key exists and is not expired. #AI:has
     *
     * Delegates to get() with a sentinel default to detect misses.
     *
     * @param string $key Cache key to test.
     */
    public function has(string $key): bool {
        return $this->get($key, "\x00not_found\x00") !== "\x00not_found\x00";
    }

    /**
     * Removes a specific key. #AI:delete
     *
     * Returns true if the file was absent or successfully deleted.
     *
     * @param string $key Exact cache key to remove.
     * @return bool True if file is gone.
     */
    public function delete(string $key): bool {
        $file = $this->filePath($key);
        return !is_file($file) || (bool) @unlink($file);
    }

    /**
     * Removes all keys matching the given prefix. #AI:flush
     *
     * Scans the cache directory for .cache files, decodes filenames back
     * to keys, and deletes matches.
     *
     * @param string $prefix Key prefix to match.
     * @return bool Always true.
     */
    public function flush(string $prefix): bool {
        foreach (glob($this->path . '/*.cache') ?: [] as $file) {
            $name = basename($file, '.cache');
            $key  = base64_decode($name) ?: $name;
            if (str_starts_with($key, $prefix)) {
                @unlink($file);
            }
        }
        return true;
    }

    /**
     * Clears all cache files in the directory. #AI:flushAll
     *
     * WARNING: Deletes every .cache file in the configured path.
     *
     * @return bool Always true.
     */
    public function flushAll(): bool {
        foreach (glob($this->path . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
        return true;
    }

    private function filePath(string $key): string {
        return $this->path . '/' . base64_encode($key) . '.cache';
    }
}

#AI:class
#AI symbol: Skim\Cache\FileDriver
#AI source_path: src/Cache/FileDriver.php
#AI title: FileDriver
#AI description: Filesystem cache driver storing each key as a serialized file with TTL-based expiry.
#AI role: filesystem cache driver
#AI layer: cache
#AI badges: [driver; cache; filesystem; fail-soft]
#AI intro: `Skim\Cache\FileDriver` persists cache entries as individual files on disk. Each file contains a serialized tuple of [expiry_timestamp, value]. Keys are base64-encoded for safe filenames. TTL is tracked via the serialized expiry, not file mtime. The driver falls back gracefully when the directory is not writable.
#AI lifecycle: persistent across requests, TTL enforced on read
#AI fallback: default cache driver when Redis is unreachable
#AI invariants: [set() uses LOCK_EX for atomic writes; get() deletes expired files on read; has() delegates to get() with sentinel; keys are base64-encoded for safe filenames; flush() scans glob and decodes filenames]
#AI warnings: [flushAll() deletes every .cache file in the configured path; Not suitable for high-throughput production workloads — prefer Redis]
#AI notes: TTL tracked via serialized expiry timestamp, not file mtime (mtime unreliable on some filesystems). Falls back gracefully when directory is not writable.
#AI section_order: [Read API; Write API; Invalidation]
#AI architectural_notes: Each key maps to one file: base64(key).cache containing serialize([$expires, $value]). The driver is the default fallback when Redis is unavailable.

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Reads the cache file, unserializes the payload, checks expiry, and returns the value or $default. Deletes expired files on read.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to read.}; {name: $default | type: mixed | required: false | desc: Fallback returned on miss or expiry.}]
#AI return_detail: {type: mixed | desc: The cached value or $default.}

#AI:set
#AI group: Write API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value, ?int $ttl = null): bool
#AI contract: Serializes [expiry, value] and writes atomically with LOCK_EX. Returns false when the directory is not writable.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}; {name: $ttl | type: ?int | required: false | desc: TTL in seconds, or null for no expiry.}]
#AI return_detail: {type: bool | desc: True if file was written successfully.}

#AI:has
#AI group: Read API
#AI frequency: medium
#AI signature: public function has(string $key): bool
#AI contract: Delegates to get() with a sentinel default to detect misses and expiry.
#AI param_details: [{name: $key | type: string | required: true | desc: Cache key to test.}]
#AI return_detail: {type: bool | desc: True if key exists and is valid.}

#AI:delete
#AI group: Write API
#AI frequency: medium
#AI signature: public function delete(string $key): bool
#AI contract: Deletes the cache file for the key. Returns true if the file was absent or successfully unlinked.
#AI param_details: [{name: $key | type: string | required: true | desc: Exact cache key to remove.}]
#AI return_detail: {type: bool | desc: True if file is gone.}

#AI:flush
#AI group: Invalidation
#AI frequency: low
#AI signature: public function flush(string $prefix): bool
#AI contract: Scans the cache directory for .cache files, decodes filenames to keys, and deletes those matching the prefix.
#AI param_details: [{name: $prefix | type: string | required: true | desc: Key prefix to match.}]
#AI return_detail: {type: bool | desc: Always true.}

#AI:flushAll
#AI group: Invalidation
#AI frequency: low
#AI signature: public function flushAll(): bool
#AI contract: Deletes every .cache file in the configured cache directory.
#AI return_detail: {type: bool | desc: Always true.}
#AI warnings: [Deletes every .cache file in the configured path unconditionally]
