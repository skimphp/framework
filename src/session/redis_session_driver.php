<?php declare(strict_types=1);

namespace skim\session;

// Redis-backed session driver — required for multi-server / load-balanced deployments.
// Stores session as a single serialized JSON blob per session ID.
// Each write re-serializes the full session — acceptable for typical session sizes (<4KB).
// For very large sessions (file uploads metadata) consider storing only IDs in session.
final class redis_session_driver implements session_driver {
    private ?\Redis $redis    = null;
    private string  $id       = '';
    private array   $data     = [];

    public function __construct(
        private readonly string  $host,
        private readonly int     $port,
        private readonly ?string $password,
        private readonly string  $prefix   = 'sess_',
        private readonly int     $lifetime = 7200,
    ) {}

    public function start(): void {
        // Read existing session ID from cookie
        $this->id = $_COOKIE['PHPSESSID'] ?? $this->generate_id();

        $raw = $this->redis()->get($this->prefix . $this->id);
        if ($raw !== false) {
            $this->data = (array) json_decode($raw, true);
        }

        // Renew TTL on access
        $this->redis()->expire($this->prefix . $this->id, $this->lifetime);

        // Send cookie header if not yet set
        if (!isset($_COOKIE['PHPSESSID'])) {
            setcookie('PHPSESSID', $this->id, [
                'expires'  => time() + $this->lifetime,
                'path'     => '/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
        $this->persist();
    }

    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    public function delete(string $key): void {
        unset($this->data[$key]);
        $this->persist();
    }

    public function regenerate(): void {
        $this->redis()->del($this->prefix . $this->id);
        $this->id = $this->generate_id();
        setcookie('PHPSESSID', $this->id, [
            'expires'  => time() + $this->lifetime,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $this->persist();
    }

    public function flush(): void {
        $this->redis()->del($this->prefix . $this->id);
        $this->data = [];
        setcookie('PHPSESSID', '', time() - 1, '/');
    }

    public function id(): string {
        return $this->id;
    }

    // --- internals ---

    private function persist(): void {
        $this->redis()->setex(
            $this->prefix . $this->id,
            $this->lifetime,
            (string) json_encode($this->data),
        );
    }

    private function generate_id(): string {
        return bin2hex(random_bytes(32));
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
        return $this->redis = $r;
    }
}
