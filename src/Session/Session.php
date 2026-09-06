<?php declare(strict_types=1);

namespace Skim\Session;

use Skim\Worker\Resettable;

/**
 * Static facade over the configured session driver with flash message support. #AI:class
 *
 * Use when application code needs session access without depending on a specific backend.
 * The driver is resolved lazily from config('app.session.driver') on first use.
 * Flash values (stored via flash()) are auto-deleted on the first get() call.
 *
 * Example:
 *   Session::set('user_id', $user->id);
 *   Session::flash('notice', 'Profile updated');
 *   Session::regenerate(); // after login
 *
 * Testing: Use setDriver() to inject SessionFake, reset() to clear state.
 *
 * #AI:class
 */
final class Session implements \Skim\Worker\Resettable {
    private static ?\Skim\Session\SessionDriver $driver  = null;
    private static bool            $started = false;

    /**
     * Closes the active session and resets driver state between requests. #AI:resetRequest
     */
    public static function resetRequest(): void {
        if (self::$started) {
            session_write_close();
        }
        self::$driver  = null;
        self::$started = false;
    }

    /**
     * Boots the session driver. #AI:start
     *
     * Idempotent — safe to call multiple times. Called automatically by
     * get/set/has/delete, so explicit calls are rarely needed.
     */
    public static function start(): void {
        if (self::$started) {
            return;
        }
        self::driver()->start();
        self::$started = true;
    }

    /**
     * Returns session value by key, consuming flash values on read. #AI:get
     *
     * Checks for a flash-prefixed key first; if found, returns and deletes it.
     * Otherwise reads the regular key. Auto-starts the session if needed.
     *
     * @param string $key     Session key to read.
     * @param mixed  $default Fallback when key is missing.
     */
    public static function get(string $key, mixed $default = null): mixed {
        self::start();
        $flashKey = '__flash__' . $key;
        if (self::driver()->has($flashKey)) {
            $val = self::driver()->get($flashKey);
            self::driver()->delete($flashKey);
            return $val ?? $default;
        }
        return self::driver()->get($key, $default);
    }

    /**
     * Stores a value in the session. #AI:set
     *
     * @param string $key   Session key to write.
     * @param mixed  $value Payload to persist.
     */
    public static function set(string $key, mixed $value): void {
        self::start();
        self::driver()->set($key, $value);
    }

    /**
     * Returns true when the key or its flash variant exists. #AI:has
     *
     * @param string $key Session key to test.
     */
    public static function has(string $key): bool {
        self::start();
        return self::driver()->has($key) || self::driver()->has('__flash__' . $key);
    }

    /**
     * Removes a key from the session. #AI:delete
     *
     * @param string $key Session key to remove.
     */
    public static function delete(string $key): void {
        self::start();
        self::driver()->delete($key);
    }

    /**
     * Stores a flash value — available until the first get() call. #AI:flash
     *
     * Flash values are stored with a `__flash__` prefix and auto-deleted
     * when read via get(). Use for one-time notifications after redirects.
     *
     * Example:
     *   Session::flash('notice', 'Item saved successfully');
     *   // Next request: Session::get('notice') returns the message and deletes it
     *
     * @param string $key   Flash key (read back via get() without prefix).
     * @param mixed  $value One-time payload.
     */
    public static function flash(string $key, mixed $value): void {
        self::start();
        self::driver()->set('__flash__' . $key, $value);
    }

    /**
     * Regenerates the session ID to prevent fixation. #AI:regenerate
     *
     * Call after login/logout. Delegates to the active driver's regenerate().
     */
    public static function regenerate(): void {
        self::start();
        self::driver()->regenerate();
    }

    /**
     * Destroys session data and resets to unstarted state. #AI:flush
     *
     * WARNING: Irreversible — all session data including flash values is lost.
     */
    public static function flush(): void {
        self::start();
        self::driver()->flush();
        self::$started = false;
    }

    /**
     * Returns the current session ID. #AI:id
     */
    public static function id(): string {
        self::start();
        return self::driver()->id();
    }

    /**
     * Injects a custom driver instance (testing only). #AI:setDriver
     *
     * Use in Pest/PHPUnit to bypass config-based resolution. Call reset()
     * in tearDown() to restore normal behavior.
     *
     * Example:
     *   Session::setDriver(new SessionFake());
     *   // ... run tests ...
     *   Session::reset();
     *
     * @param \Skim\Session\SessionDriver $driver Mock or fake driver for testing.
     */
    public static function setDriver(\Skim\Session\SessionDriver $driver): void {
        self::$driver  = $driver;
        self::$started = false;
    }

    /**
     * Clears the cached driver and resets to unstarted state. #AI:reset
     *
     * Forces re-resolution from config on the next session call.
     */
    public static function reset(): void {
        self::$driver  = null;
        self::$started = false;
    }

    // --- internals ---

    private static function driver(): \Skim\Session\SessionDriver {
        return self::$driver ??= self::resolveDriver();
    }

    private static function resolveDriver(): \Skim\Session\SessionDriver {
        $name = \Skim\Core\Config::get('app.session.driver', 'file');
        return match ($name) {
            'redis' => new \Skim\Session\RedisSessionDriver(
                host:     (string) \Skim\Core\Config::get('cache.redis.host', '127.0.0.1'),
                port:     (int) \Skim\Core\Config::get('cache.redis.port', 6379),
                password: \Skim\Core\Config::get('cache.redis.password'),
                prefix:   (string) \Skim\Core\Config::get('app.session.prefix', 'sess_'),
                lifetime: (int) \Skim\Core\Config::get('app.session.lifetime', 7200),
            ),
            default => new \Skim\Session\FileSessionDriver(
                path: storagePath('sessions'),
                lifetime: (int) \Skim\Core\Config::get('app.session.lifetime', 7200),
            ),
        };
    }
}

#AI:class
#AI symbol: Skim\Session\Session
#AI source_path: src/Session/Session.php
#AI title: session
#AI description: Static session facade with flash message support, lazy driver resolution, and test injection.
#AI role: static session facade
#AI layer: session
#AI badges: [facade; session; flash; driver-backed]
#AI intro: `session` is the static entry point for session operations. It resolves the configured session driver on first use and adds flash message support on top of the raw driver API.
#AI lifecycle: static facade, driver resolved on first session call
#AI fallback: FileSessionDriver when app.session.driver is not 'redis'
#AI test_seam: setDriver(), reset()
#AI drivers: [file; redis]
#AI invariants: [start() is idempotent; flash values are auto-deleted on first get(); has() checks both regular and flash keys; driver is resolved once and reused until reset()]
#AI core_behaviors: [Flash values use __flash__ prefix internally; get() consumes flash values on read; Auto-starts session on any read/write operation]
#AI warnings: [flush() destroys all session data irreversibly]
#AI notes: Flash messages are consumed on first read via get(). Use flash() before redirect, get() on the next request to display.
#AI owns: driver instance, started flag
#AI entry_points: [start; get; set; has; delete; flash; regenerate; flush; id]
#AI config_reads: [app.session.driver; app.session.prefix; app.session.lifetime; cache.redis.host; cache.redis.port; cache.redis.password]
#AI non_goals: [Does not encrypt session data; Does not handle session locking; Flash is single-read only, not queued]
#AI side_effects: [Auto-starts session on first get/set/has/delete; setDriver() replaces active driver; reset() forces re-resolution]
#AI flow: Session::method() -> start() -> driver() -> resolveDriver() -> concrete driver
#AI lifecycle_steps: [Session::get/set/has/delete(); -> start(); -> started?; -> driver(); -> resolveDriver(); -> match config app.session.driver; -> FileSessionDriver or RedisSessionDriver]
#AI section_order: [Read API; Write API; Flash Messages; Lifecycle; Testing Hooks; Architecture]
#AI architectural_notes: Wraps native session drivers to enable test injection and add flash message semantics.

#AI:start
#AI group: Lifecycle
#AI frequency: internal
#AI signature: public static function start(): void
#AI contract: Boots the session driver. Idempotent — safe to call multiple times. Called automatically by get/set/has/delete.

#AI:get
#AI group: Read API
#AI frequency: high
#AI signature: public static function get(string $key, mixed $default = null): mixed
#AI contract: Returns session value by key. Checks for a flash-prefixed key first; if found, returns and deletes it. Otherwise reads the regular key.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to read.}; {name: $default | type: mixed | required: false | desc: Fallback when key is missing.}]
#AI return_detail: {type: mixed | desc: The stored value, flash value, or $default.}
#AI side_effects: [Consumes (deletes) flash values on read]

#AI:set
#AI group: Write API
#AI frequency: high
#AI signature: public static function set(string $key, mixed $value): void
#AI contract: Stores a value in the session for the current and future requests.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to write.}; {name: $value | type: mixed | required: true | desc: Payload to persist.}]

#AI:has
#AI group: Read API
#AI frequency: medium
#AI signature: public static function has(string $key): bool
#AI contract: Returns true when the key or its flash variant (__flash__ prefix) exists.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to test.}]
#AI return_detail: {type: bool | desc: True if regular or flash key exists.}

#AI:delete
#AI group: Write API
#AI frequency: medium
#AI signature: public static function delete(string $key): void
#AI contract: Removes a key from the session. Does not remove the flash variant.
#AI param_details: [{name: $key | type: string | required: true | desc: Session key to remove.}]

#AI:flash
#AI group: Flash Messages
#AI frequency: medium
#AI signature: public static function flash(string $key, mixed $value): void
#AI contract: Stores a flash value that is available on the current read and auto-deleted on the first get() call.
#AI param_details: [{name: $key | type: string | required: true | desc: Flash key, read back via get() without prefix.}; {name: $value | type: mixed | required: true | desc: One-time payload.}]
#AI side_effects: [Writes to session with __flash__ prefix]

#AI:regenerate
#AI group: Lifecycle
#AI frequency: low
#AI signature: public static function regenerate(): void
#AI contract: Regenerates the session ID to prevent session fixation. Call after login/logout.

#AI:flush
#AI group: Lifecycle
#AI frequency: low
#AI signature: public static function flush(): void
#AI contract: Destroys session data and resets the facade to unstarted state.
#AI warnings: [Irreversible — all session data including flash values is lost]
#AI side_effects: [Destroys session via driver; Resets started flag]

#AI:id
#AI group: Read API
#AI frequency: low
#AI signature: public static function id(): string
#AI contract: Returns the current session ID.
#AI return_detail: {type: string | desc: The active session identifier.}

#AI:setDriver
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function setDriver(SessionDriver $driver): void
#AI contract: Replaces the active driver instance. Use in tests to bypass config-based resolution.
#AI param_details: [{name: $driver | type: SessionDriver | required: true | desc: Mock or fake driver for testing.}]
#AI side_effects: [Replaces static driver; Resets started flag]

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears the cached driver and resets to unstarted state. Forces re-resolution from config on next call.
#AI side_effects: [Clears static driver and started flag]

#AI:resetRequest
#AI group: Testing Hooks
#AI frequency: internal
#AI signature: public static function resetRequest(): void
#AI contract: Closes the active session and resets driver state between requests in worker mode.
#AI side_effects: [Calls session_write_close() if started; clears driver and started flag]

#AI:driver
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function driver(): SessionDriver
#AI contract: Returns the cached driver instance, resolving lazily if null.

#AI:resolveDriver
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function resolveDriver(): SessionDriver
#AI contract: Maps config('app.session.driver') to a concrete driver instance. Defaults to FileSessionDriver.
