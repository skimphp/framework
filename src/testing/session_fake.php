<?php declare(strict_types=1);

namespace skim\testing;

class session_fake {
    private array $data = [];

    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
    public function has(string $key): bool { return isset($this->data[$key]); }
    public function flush(): void { $this->data = []; }
    public function all(): array  { return $this->data; }
}
