<?php declare(strict_types=1);

namespace skim\session;

interface session_driver {
    public function start(): void;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function delete(string $key): void;
    public function regenerate(): void;
    public function flush(): void;
    public function id(): string;
}
