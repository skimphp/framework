<?php declare(strict_types=1);

namespace Skim\Session;

/**
 * Redis-backed session driver for multi-server deployments. #AI:class
 *
 * Use when sessions must be shared across load-balanced PHP workers.
 * Stores each session as a single JSON blob keyed by prefixed session ID.
 * The Redis connection is opened lazily on first access and reused.
 *
 * Example:
 *   $driver = new RedisSessionDriver('redis', 6379, null, 'sess_', 7200);
 *   $driver->start();
 *
 * Testing: Use SessionFake instead; this driver opens a real Redis connection.
 *
 * #AI:class
 */
final class RedisSessionDriver implements \Skim\Session\SessionDriver {
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

    /**
     * Starts or resumes a Redis-backed session. #AI:start
     *
     * Reads the session ID from the PHPSESSID cookie or generates a new one.
     * Loads existing session data from Redis and renews the TTL on access.
     * Sends a Set-Cookie header only for new sessions.
     */
    public function start(): void {
        // Read existing session ID from cookie
        $this->id = $_COOKIE['PHPSESSID'] ?? $this->generateId();

        $raw = $this->redis()->get($this->prefix . $this->id);

		if ($raw !== false) {
            $this->data = (array) json_decode($raw, true);
        }

        // Renew TTL on access
        $this->redis()->expire($this->prefix . $this->id, $this->lifetime);

        // Send cookie header if not yet set
        if (!isset($_COOKIE['PHPSESSID'])) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
            setcookie('PHPSESSID', $this->id, [
                'expires'  => time() + $this->lifetime,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Returns session value by key, or $default if absent. #AI:get
     *
     * @param string $key     Session key to read.
     * @param mixed  $default Fallback when key is missing.
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }

    /**
     * Stores a value and persists to Redis immediately. #AI:set
     *
     * @param string $key   Session key to write.
     * @param mixed  $value Payload to persist.
     */
    public function set(string $key, mixed $value): void {
        $this->data[$key] = $value;
        $this->persist();
    }

    /**
     * Returns true when the key exists in the session. #AI:has
     *
     * @param string $key Session key to test.
     */
    public function has(string $key): bool {
        return isset($this->data[$key]);
    }

    /**
     * Removes a key and persists the change to Redis. #AI:delete
     *
     * @param string $key Session key to remove.
     */
    public function delete(string $key): void {
        unset($this->data[$key]);
        $this->persist();
    }

    /**
     * Regenerates the session ID and migrates data to the new key. #AI:regenerate
     *
     * Deletes the old Redis key, generates a new ID, sends a fresh cookie,
     * and persists existing data under the new key.
     */
    public function regenerate(): void {
        $this->redis()->del($this->prefix . $this->id);
        $this->id = $this->generateId();
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        setcookie('PHPSESSID', $this->id, [
            'expires'  => time() + $this->lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $this->persist();
    }

    /**
     * Destroys session data in Redis and expires the cookie. #AI:flush
     *
     * WARNING: Irreversible — all session data for this ID is deleted from Redis.
     */
    public function flush(): void {
        $this->redis()->del($this->prefix . $this->id);
        $this->data = [];
        setcookie('PHPSESSID', '', time() - 1, '/');
    }

    /**
     * Returns the current session ID. #AI:id
     */
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

    private function generateId(): string {
        return bin2hex(random_bytes(32));
    }

    private function redis(): \Redis {
        if ($this->redis !== null) {
            return $this->redis;
        }
        $r = new \Redis();
        $r->connect($this->host, $this->port, 1.0);
        if ($this->password !== null && $this->password !== '') {
            $r->auth($this->password);
        }
        return $this->redis = $r;
    }
}

#AI:class
#AI symbol: Skim\Session\RedisSessionDriver
#AI source_path: src/Session/RedisSessionDriver.php
#AI title: RedisSessionDriver
#AI description: Redis-backed session driver for multi-server and load-balanced deployments.
#AI role: session driver (redis)
#AI layer: session
#AI badges: [session; driver; redis; multi-server]
#AI intro: `RedisSessionDriver` stores each session as a single serialized JSON blob in Redis, keyed by a configurable prefix plus the session ID. It is required for load-balanced deployments where file-based sessions would not be shared across workers.
#AI lifecycle: instantiated by session facade, start() called once per request, Redis connection opened lazily
#AI fallback: none — use FileSessionDriver when Redis is unavailable
#AI test_seam: use SessionFake in tests instead
#AI invariants: [start() is idempotent; Redis connection is opened lazily and reused; every write re-serializes the full session as JSON; TTL is renewed on each start()]
#AI core_behaviors: [Session data is stored as a single JSON blob per session ID; Cookie is sent only for new sessions; regenerate() migrates data to a new Redis key]
#AI warnings: [flush() deletes session data from Redis irreversibly; Each write re-serializes the full session — avoid storing large payloads]
#AI notes: Each set()/delete() triggers a full JSON re-serialization and Redis write. For typical session sizes (<4KB) this is acceptable.
#AI owns: Redis session keys under configured prefix
#AI entry_points: [start; get; set; has; delete; regenerate; flush; id]
#AI config_reads: []
#AI non_goals: [Does not support session locking; Does not encrypt session data; Not suitable for very large session payloads]
#AI side_effects: [Opens Redis connection on first use; Writes JSON blobs to Redis; Sets cookie headers for new sessions]
#AI flow: Session::method() -> RedisSessionDriver -> Redis SETEX/GET/DEL
#AI lifecycle_steps: [Session::start(); -> RedisSessionDriver::start(); -> read PHPSESSID cookie or generateId(); -> Redis GET; -> json_decode; -> Redis EXPIRE; -> setcookie if new]
#AI section_order: [Session API; Lifecycle; Testing Hooks; Architecture]
#AI architectural_notes: Stores session as a single JSON blob rather than individual keys — simpler but means every write is O(session_size).

#AI:start
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public function start(): void
#AI contract: Starts or resumes a Redis-backed session. Reads session ID from cookie or generates new one. Loads data from Redis and renews TTL. Sends cookie only for new sessions.

#AI:get
#AI group: Session API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Returns session value by key from the in-memory data array, or $default if absent.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to read.}; {name: $default | type: mixed | required: false | desc: Fallback when key is missing.}]
#AI return_detail: {type: mixed | desc: The stored value or $default.}

#AI:set
#AI group: Session API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value): void
#AI contract: Stores a value in the in-memory data array and immediately persists the full session to Redis.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}]
#AI side_effects: [Re-serializes full session and writes to Redis via SETEX]

#AI:has
#AI group: Session API
#AI frequency: medium
#AI signature: public function has(string $key): bool
#AI contract: Returns true when the key exists in the in-memory session data.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to test.}]
#AI return_detail: {type: bool | desc: True if key exists.}

#AI:delete
#AI group: Session API
#AI frequency: medium
#AI signature: public function delete(string $key): void
#AI contract: Removes a key from the in-memory data and persists the change to Redis.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to remove.}]
#AI side_effects: [Re-serializes full session and writes to Redis via SETEX]

#AI:regenerate
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function regenerate(): void
#AI contract: Deletes the old Redis session key, generates a new Session ID, sends a fresh cookie, and persists existing data under the new key.
#AI side_effects: [Deletes old Redis key; Generates new Session ID; Sends Set-Cookie header; Writes new Redis key]

#AI:flush
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function flush(): void
#AI contract: Deletes session data from Redis and expires the session cookie.
#AI warnings: [Irreversible — all session data for this ID is deleted from Redis]
#AI side_effects: [Deletes Redis key; Clears in-memory data; Expires cookie]

#AI:id
#AI group: Session API
#AI frequency: low
#AI signature: public function id(): string
#AI contract: Returns the current session ID.
#AI return_detail: {type: string | desc: The active session identifier.}
