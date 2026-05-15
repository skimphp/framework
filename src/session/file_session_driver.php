<?php declare(strict_types=1);

namespace skim\session;

// Native PHP file-backed session driver.
// Delegates to session_* functions after setting save_path and cookie params.
// Use for single-server setups; switch to redis_session_driver for multi-server.
final class file_session_driver implements session_driver {
    private string $id = '';

    public function __construct(
        private readonly string $path,
        private readonly int    $lifetime = 7200,
    ) {}

    public function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->id = session_id() ?: '';
            return;
        }
        if (!is_dir($this->path)) {
            mkdir($this->path, 0700, true);
        }
        session_save_path($this->path);
        session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $this->id = session_id() ?: '';
    }

    public function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    public function delete(string $key): void {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void {
        session_regenerate_id(true);
        $this->id = session_id() ?: '';
    }

    public function flush(): void {
        session_destroy();
        $_SESSION = [];
        $this->id = '';
    }

    public function id(): string {
        return $this->id;
    }
}
