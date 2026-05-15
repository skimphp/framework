<?php declare(strict_types=1);

namespace skim\session;

// Session facade. Wraps PHP native sessions with Redis or file driver.
// Reason for wrapping: native session_* functions are non-testable and
// tightly coupled to headers — this facade allows test injection via set_driver().
//
// Flash messages: stored with '__flash__' prefix, auto-deleted on next get().
// Regenerate: called after login/logout to prevent session fixation.
final class session {
    private static ?session_driver $driver  = null;
    private static bool            $started = false;

    /**
     * @ai-contract must be called once per request before any get/set — boots the session
     * @ai-contract idempotent — safe to call multiple times
     */
    public static function start(): void {
        if (self::$started) {
            return;
        }
        self::driver()->start();
        self::$started = true;
    }

    /**
     * @ai-contract returns session value by key, or $default if not set
     * @ai-contract auto-deletes flash keys on read
     */
    public static function get(string $key, mixed $default = null): mixed {
        self::start();
        $flash_key = '__flash__' . $key;
        if (self::driver()->has($flash_key)) {
            $val = self::driver()->get($flash_key);
            self::driver()->delete($flash_key);
            return $val ?? $default;
        }
        return self::driver()->get($key, $default);
    }

    /**
     * @ai-contract stores a value in the session for the current and future requests
     */
    public static function set(string $key, mixed $value): void {
        self::start();
        self::driver()->set($key, $value);
    }

    /**
     * @ai-contract returns true if the key exists and is not a consumed flash
     */
    public static function has(string $key): bool {
        self::start();
        return self::driver()->has($key) || self::driver()->has('__flash__' . $key);
    }

    /**
     * @ai-contract removes a key from the session
     */
    public static function delete(string $key): void {
        self::start();
        self::driver()->delete($key);
    }

    /**
     * @ai-contract stores a flash value — available on THIS read and the NEXT request only
     * @ai-contract after first get() call, the flash key is deleted
     */
    public static function flash(string $key, mixed $value): void {
        self::start();
        self::driver()->set('__flash__' . $key, $value);
    }

    /**
     * @ai-contract regenerates session ID — call after login/logout to prevent session fixation
     */
    public static function regenerate(): void {
        self::start();
        self::driver()->regenerate();
    }

    /**
     * @ai-contract destroys session data and cookie
     */
    public static function flush(): void {
        self::start();
        self::driver()->flush();
        self::$started = false;
    }

    /**
     * @ai-contract returns current session ID
     */
    public static function id(): string {
        self::start();
        return self::driver()->id();
    }

    /**
     * @ai-contract for tests — inject driver directly, bypass config resolution
     */
    public static function set_driver(session_driver $driver): void {
        self::$driver  = $driver;
        self::$started = false;
    }

    /**
     * @ai-contract for tests — reset to unstarted state
     */
    public static function reset(): void {
        self::$driver  = null;
        self::$started = false;
    }

    // --- internals ---

    private static function driver(): session_driver {
        return self::$driver ??= self::resolve_driver();
    }

    private static function resolve_driver(): session_driver {
        $name = \skim\core\config::get('app.session.driver', 'file');
        return match ($name) {
            'redis' => new redis_session_driver(
                host:     (string) \skim\core\config::get('cache.redis.host', '127.0.0.1'),
                port:     (int) \skim\core\config::get('cache.redis.port', 6379),
                password: \skim\core\config::get('cache.redis.password'),
                prefix:   (string) \skim\core\config::get('app.session.prefix', 'sess_'),
                lifetime: (int) \skim\core\config::get('app.session.lifetime', 7200),
            ),
            default => new file_session_driver(
                path: storage_path('sessions'),
                lifetime: (int) \skim\core\config::get('app.session.lifetime', 7200),
            ),
        };
    }
}
