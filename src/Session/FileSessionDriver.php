<?php declare(strict_types=1);

namespace Skim\Session;

/**
 * Native PHP file-backed session driver. #AI:class
 *
 * Use for single-server deployments where Redis is not available.
 * Delegates to PHP's native session_* functions after configuring save_path
 * and secure cookie params. The session directory is auto-created on start().
 *
 * Example:
 *   $driver = new FileSessionDriver(storagePath('sessions'), 7200);
 *   $driver->start();
 *
 * Testing: Use SessionFake instead; this driver touches $_SESSION and headers.
 *
 * #AI:class
 */
final class FileSessionDriver implements \Skim\Session\SessionDriver {
    private string $id = '';

    public function __construct(
        private readonly string $path,
        private readonly int    $lifetime = 7200,
    ) {}

    /**
     * Starts or resumes a file-backed session. #AI:start
     *
     * No-ops when a session is already active. Creates the save_path directory
     * if missing and configures secure cookie params based on HTTPS detection.
     */
    public function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->id = session_id() ?: '';
            return;
        }
        if (!is_dir($this->path)) {
            mkdir($this->path, 0700, true);
        }
        session_save_path($this->path);

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

		session_set_cookie_params([
            'lifetime' => $this->lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $this->id = session_id() ?: '';
    }

    /**
     * Returns session value by key, or $default if absent. #AI:get
     *
     * @param string $key     Session key to read.
     * @param mixed  $default Fallback when key is missing.
     */
    public function get(string $key, mixed $default = null): mixed {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Stores a value in the session. #AI:set
     *
     * @param string $key   Session key to write.
     * @param mixed  $value Payload to persist.
     */
    public function set(string $key, mixed $value): void {
        $_SESSION[$key] = $value;
    }

    /**
     * Returns true when the key exists in the session. #AI:has
     *
     * @param string $key Session key to test.
     */
    public function has(string $key): bool {
        return isset($_SESSION[$key]);
    }

    /**
     * Removes a key from the session. #AI:delete
     *
     * @param string $key Session key to remove.
     */
    public function delete(string $key): void {
        unset($_SESSION[$key]);
    }

    /**
     * Regenerates the session ID, deleting the old session file. #AI:regenerate
     *
     * Call after login/logout to prevent session fixation attacks.
     */
    public function regenerate(): void {
        session_regenerate_id(true);
        $this->id = session_id() ?: '';
    }

    /**
     * Destroys session data and invalidates the session. #AI:flush
     *
     * WARNING: Irreversible — all session data for this ID is lost immediately.
     */
    public function flush(): void {
        session_destroy();
        $_SESSION = [];
        $this->id = '';
    }

    /**
     * Returns the current session ID. #AI:id
     */
    public function id(): string {
        return $this->id;
    }
}

#AI:class
#AI symbol: Skim\Session\FileSessionDriver
#AI source_path: src/Session/FileSessionDriver.php
#AI title: FileSessionDriver
#AI description: Native PHP file-backed session driver for single-server deployments.
#AI role: session driver (file)
#AI layer: session
#AI badges: [session; driver; file; native-php]
#AI intro: `FileSessionDriver` wraps PHP's native `session_*` functions with a configured save path and secure cookie parameters. It is the default session driver for single-server setups.
#AI lifecycle: instantiated by session facade, start() called once per request
#AI fallback: none — this is itself the fallback driver
#AI test_seam: use SessionFake in tests instead
#AI invariants: [start() is idempotent; save_path directory is auto-created with 0700 permissions; cookie secure flag is auto-detected from HTTPS]
#AI core_behaviors: [Delegates all read/write to $_SESSION superglobal; Configures cookie params on every start() call; regenerate() deletes old session file]
#AI warnings: [flush() destroys all session data irreversibly; Direct $_SESSION access bypasses driver abstraction]
#AI notes: Not suitable for multi-server deployments — session files are local to one server. Use RedisSessionDriver for load-balanced setups.
#AI owns: session file on disk
#AI entry_points: [start; get; set; has; delete; regenerate; flush; id]
#AI config_reads: []
#AI non_goals: [Does not support multi-server session sharing; Does not encrypt session data at rest]
#AI side_effects: [Writes session files to disk; Sets cookie headers via session_start(); Modifies $_SESSION superglobal]
#AI flow: Session::method() -> FileSessionDriver -> session_* native functions -> $_SESSION
#AI lifecycle_steps: [Session::start(); -> FileSessionDriver::start(); -> mkdir if needed; -> session_set_cookie_params(); -> session_start(); -> $_SESSION available]
#AI section_order: [Session API; Lifecycle; Testing Hooks; Architecture]
#AI architectural_notes: Wraps PHP native sessions to enable test injection via the SessionDriver interface.

#AI:start
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public function start(): void
#AI contract: Starts or resumes a file-backed session. No-ops when a session is already active. Creates the save_path directory if missing and configures secure cookie params.

#AI:get
#AI group: Session API
#AI frequency: high
#AI signature: public function get(string $key, mixed $default = null): mixed
#AI contract: Returns session value by key, or $default if absent.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to read.}; {name: $default | type: mixed | required: false | desc: Fallback when key is missing.}]
#AI return_detail: {type: mixed | desc: The stored value or $default.}

#AI:set
#AI group: Session API
#AI frequency: high
#AI signature: public function set(string $key, mixed $value): void
#AI contract: Stores a value in the session via $_SESSION.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}]

#AI:has
#AI group: Session API
#AI frequency: medium
#AI signature: public function has(string $key): bool
#AI contract: Returns true when the key exists in the session.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to test.}]
#AI return_detail: {type: bool | desc: True if key exists.}

#AI:delete
#AI group: Session API
#AI frequency: medium
#AI signature: public function delete(string $key): void
#AI contract: Removes a key from the session.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to remove.}]

#AI:regenerate
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function regenerate(): void
#AI contract: Regenerates the session ID and deletes the old session file. Call after login/logout to prevent session fixation.
#AI side_effects: [Deletes old session file; Generates new Session ID]

#AI:flush
#AI group: Lifecycle
#AI frequency: low
#AI signature: public function flush(): void
#AI contract: Destroys session data and invalidates the session.
#AI warnings: [Irreversible — all session data for this ID is lost immediately]
#AI side_effects: [Destroys session file; Clears $_SESSION; Resets internal ID]

#AI:id
#AI group: Session API
#AI frequency: low
#AI signature: public function id(): string
#AI contract: Returns the current session ID.
#AI return_detail: {type: string | desc: The active session identifier.}
