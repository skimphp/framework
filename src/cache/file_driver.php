<?php declare(strict_types=1);

namespace skim\cache;

// Filesystem cache driver. Each key is one file: serialize([$expires, $value]).
// TTL tracked via serialized expiry timestamp — not file mtime (mtime unreliable on some FS).
// Falls back gracefully when directory is not writable (returns false on set, null on get).
final class file_driver implements driver {
    public function __construct(private readonly string $path) {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed {
        $file = $this->file_path($key);
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

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        $file    = $this->file_path($key);
        $expires = $ttl !== null ? microtime(true) + $ttl : null;
        $content = serialize([$expires, $value]);
        return (bool) @file_put_contents($file, $content, \LOCK_EX);
    }

    public function has(string $key): bool {
        return $this->get($key, "\x00not_found\x00") !== "\x00not_found\x00";
    }

    public function delete(string $key): bool {
        $file = $this->file_path($key);
        return !is_file($file) || (bool) @unlink($file);
    }

    public function flush(string $prefix = ''): bool {
        $files = glob($this->path . '/*.cache') ?: [];
        foreach ($files as $file) {
            $name = basename($file, '.cache');
            $key  = base64_decode($name) ?: $name;
            if ($prefix === '' || str_starts_with($key, $prefix)) {
                @unlink($file);
            }
        }
        return true;
    }

    private function file_path(string $key): string {
        // Base64 encode the key to get a safe filename — keys may contain colons, slashes
        return $this->path . '/' . base64_encode($key) . '.cache';
    }
}
