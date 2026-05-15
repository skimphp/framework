<?php declare(strict_types=1);

namespace skim\cache;

// In-memory array driver — for tests only. No persistence, no Redis required.
// Resets between requests naturally (process-scoped array).
// Why not APCu: APCu state is per-process, inconsistent under PHP-FPM.
final class array_driver implements driver {
    // Each entry: ['value' => mixed, 'expires' => ?float (microtime)]
    private array $store = [];

    public function get(string $key, mixed $default = null): mixed {
        if (!$this->has($key)) {
            return $default;
        }
        return $this->store[$key]['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        $this->store[$key] = [
            'value'   => $value,
            'expires' => $ttl !== null ? microtime(true) + $ttl : null,
        ];
        return true;
    }

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

    public function delete(string $key): bool {
        unset($this->store[$key]);
        return true;
    }

    public function flush(string $prefix = ''): bool {
        if ($prefix === '') {
            $this->store = [];
            return true;
        }
        foreach (array_keys($this->store) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->store[$key]);
            }
        }
        return true;
    }
}
