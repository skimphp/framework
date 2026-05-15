<?php declare(strict_types=1);

use skim\core\config;
use skim\core\env;
use skim\core\router;

// Global helper functions — thin wrappers around framework static facades.
// These exist for ergonomic use in templates and config files.
// All app code should import the classes directly; helpers are for convenience.

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed {
        return env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        return config::get($key, $default);
    }
}

if (!function_exists('route')) {
    /**
     * Generate a named route URL.
     * route('user.show', ['id' => 5]) → /users/5
     */
    function route(string $name, array $params = []): string {
        return router::url($name, $params);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Absolute path to the storage directory.
     * storage_path('logs/app.log') → /var/www/myapp/storage/logs/app.log
     */
    function storage_path(string $path = ''): string {
        $base = defined('SKIM_ROOT') ? SKIM_ROOT . '/storage' : getcwd() . '/storage';
        return $path !== '' ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        $base = defined('SKIM_ROOT') ? SKIM_ROOT : getcwd();
        return $path !== '' ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('e')) {
    /**
     * HTML-escape a string for safe output in templates.
     * Always call on user-supplied data — skipping is an XSS vulnerability.
     */
    function e(string $value, bool $double_encode = true): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $double_encode);
    }
}

if (!function_exists('t')) {
    /**
     * Translate a key using the current locale.
     * t('auth.login') | t('items.count', ['count' => 3])
     */
    function t(string $key, array $params = []): string {
        return \skim\i18n\i18n::t($key, $params);
    }
}

if (!function_exists('asset')) {
    /**
     * Returns the URL for a Vite-managed asset.
     * In dev: proxies through Vite HMR server.
     * In production: returns hashed filename from manifest.json.
     */
    function asset(string $path): string {
        return \skim\assets\assets::url($path);
    }
}
