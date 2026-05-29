<?php declare(strict_types=1);

use skim\core\config;
use skim\core\env;
use skim\core\router;

if (!function_exists('env')) {
    /**
     * Reads an environment variable with optional fallback. #AI:env
     *
     * @param string $key     Dotenv key, e.g. 'DB_HOST'.
     * @param mixed  $default Returned when the variable is missing.
     */
    function env(string $key, mixed $default = null): mixed {
        return env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Reads a dot-separated config value. #AI:config
     *
     * @param string $key     Dot-path like 'app.name' or 'db.default.host'.
     * @param mixed  $default Returned when the key is not set.
     */
    function config(string $key, mixed $default = null): mixed {
        return config::get($key, $default);
    }
}

if (!function_exists('route')) {
    /**
     * Generates a URL for a named route. #AI:route
     *
     * Substitutes route param placeholders in the pattern. Throws when
     * the route name is not registered.
     *
     * Example:
     *   route('user.show', ['id' => 5])  // → /users/5
     *
     * @param string $name   Registered route name.
     * @param array  $params Key-value pairs mapped to route placeholders.
     */
    function route(string $name, array $params = []): string {
        return router::url($name, $params);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Absolute path under the storage/ directory. #AI:storage_path
     *
     * Resolves SKIM_ROOT when defined, falls back to getcwd().
     *
     * Example:
     *   storage_path('logs/app.log')  // → /var/www/myapp/storage/logs/app.log
     *
     * @param string $path Sub-path appended to storage root. Empty returns the root itself.
     */
    function storage_path(string $path = ''): string {
        $base = defined('SKIM_ROOT') ? SKIM_ROOT . '/storage' : getcwd() . '/storage';
        return $path !== '' ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('base_path')) {
    /**
     * Absolute path to the project root. #AI:base_path
     *
     * @param string $path Sub-path appended to root. Empty returns root itself.
     */
    function base_path(string $path = ''): string {
        $base = defined('SKIM_ROOT') ? SKIM_ROOT : getcwd();
        return $path !== '' ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('e')) {
    /**
     * HTML-escapes a string for safe template output. #AI:e
     *
     * Always call on user-supplied data — skipping is an XSS vulnerability.
     * Pass `$double_encode = false` when the value already contains entities.
     *
     * @param string $value          Raw string to escape.
     * @param bool   $double_encode  Re-encode existing entities when true.
     */
    function e(string $value, bool $double_encode = true): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $double_encode);
    }
}

if (!function_exists('t')) {
    /**
     * Translates a key using the current locale. #AI:t
     *
     * Example:
     *   t('auth.login')
     *   t('items.count', ['count' => 3])  // "3 items"
     *
     * @param string $key    Translation key, e.g. 'auth.login'.
     * @param array  $params Placeholders substituted into the translated string.
     */
    function t(string $key, array $params = []): string {
        return \skim\i18n\i18n::t($key, $params);
    }
}

if (!function_exists('asset')) {
    /**
     * URL for a Vite-managed asset. #AI:asset
     *
     * Dev mode proxies through the Vite HMR server; production returns the
     * hashed filename resolved from manifest.json.
     *
     * @param string $path Asset path relative to the project root, e.g. 'css/app.css'.
     */
    function asset(string $path): string {
        return \skim\assets\assets::url($path);
    }
}

#AI:file
#AI source_path: src/core/helpers.php
#AI title: helpers
#AI description: Global convenience functions wrapping framework facades for ergonomic use in templates, config files, and application code.
#AI role: global helper functions
#AI layer: core
#AI badges: [helpers; global-functions; facade-wrappers; template-safe]
#AI intro: `helpers.php` defines global functions that delegate to framework static facades. They exist for ergonomic use in templates and config files where importing classes is awkward. Each function is guarded by `function_exists()` to allow application-level overrides.
#AI lifecycle: loaded via composer autoload files, available after bootstrap
#AI invariants: [each function is guarded by function_exists() to prevent redeclaration; all functions delegate to static facades; no global mutable state is introduced]
#AI core_behaviors: [Functions are thin pass-through wrappers; Application code may override any helper by defining it before autoload]
#AI notes: App code should import facade classes directly for IDE support. Helpers are for templates and config files where `use` statements are unavailable or awkward.
#AI owns: nothing
#AI entry_points: [env; config; route; storage_path; base_path; e; t; asset]
#AI config_reads: [app.*; db.*; cache.*]
#AI non_goals: [Does not add behavior beyond the underlying facades; Does not replace facade usage in application controllers or models]
#AI side_effects: [none — all functions are pure delegation]
#AI section_order: [Config & Env; Templates & Routing]

#AI:env
#AI group: Config & Env
#AI frequency: high
#AI signature: function env(string $key, mixed $default = null): mixed
#AI contract: Reads an environment variable by key, returning $default when the variable is not set. Delegates to env::get().
#AI param_details: [{name: $key | type: string | required: true | desc: Dotenv key such as 'DB_HOST' or 'APP_NAME'.}; {name: $default | type: mixed | required: false | desc: Fallback value returned when the variable is missing.}]
#AI return_detail: {type: mixed | desc: The environment variable value or $default.}

#AI:config
#AI group: Config & Env
#AI frequency: high
#AI signature: function config(string $key, mixed $default = null): mixed
#AI contract: Reads a dot-separated config value from the loaded config files. Returns $default when the key path does not exist.
#AI param_details: [{name: $key | type: string | required: true | desc: Dot-path such as 'app.name' or 'db.default.host'.}; {name: $default | type: mixed | required: false | desc: Fallback value returned when the key is not set.}]
#AI return_detail: {type: mixed | desc: The config value or $default.}

#AI:base_path
#AI group: Config & Env
#AI frequency: medium
#AI signature: function base_path(string $path = ''): string
#AI contract: Returns the absolute path to the project root. Uses SKIM_ROOT when defined, falls back to getcwd(). Appends the optional sub-path.
#AI param_details: [{name: $path | type: string | required: false | desc: Sub-path appended to root. Empty string returns the root directory itself.}]
#AI return_detail: {type: string | desc: Absolute filesystem path.}

#AI:route
#AI group: Templates & Routing
#AI frequency: high
#AI signature: function route(string $name, array $params = []): string
#AI contract: Generates a URL string for a named route by substituting $params into the route's pattern placeholders. Throws when the route name is not registered.
#AI param_details: [{name: $name | type: string | required: true | desc: Registered route name, e.g. 'user.show'.}; {name: $params | type: array | required: false | desc: Key-value pairs mapped to @param placeholders in the route pattern.}]
#AI return_detail: {type: string | desc: Generated URL path.}
#AI examples: [{label: Basic usage | code: route('user.show', ['id' => 5])  // → /users/5}]

#AI:storage_path
#AI group: Templates & Routing
#AI frequency: medium
#AI signature: function storage_path(string $path = ''): string
#AI contract: Returns the absolute path under the storage/ directory. Uses SKIM_ROOT when defined, falls back to getcwd(). Appends the optional sub-path.
#AI param_details: [{name: $path | type: string | required: false | desc: Sub-path under storage/. Empty string returns the storage root itself.}]
#AI return_detail: {type: string | desc: Absolute filesystem path under storage/.}
#AI examples: [{label: Log file path | code: storage_path('logs/app.log')  // → /var/www/myapp/storage/logs/app.log}]

#AI:e
#AI group: Templates & Routing
#AI frequency: high
#AI signature: function e(string $value, bool $double_encode = true): string
#AI contract: HTML-escapes a string using htmlspecialchars with ENT_QUOTES and UTF-8. Always call on user-supplied data to prevent XSS. Pass $double_encode = false when the value already contains HTML entities.
#AI param_details: [{name: $value | type: string | required: true | desc: Raw string to escape.}; {name: $double_encode | type: bool | required: false | desc: When false, existing HTML entities are not re-encoded.}]
#AI return_detail: {type: string | desc: HTML-safe escaped string.}
#AI warnings: [Skipping e() on user-supplied data is an XSS vulnerability]

#AI:t
#AI group: Templates & Routing
#AI frequency: medium
#AI signature: function t(string $key, array $params = []): string
#AI contract: Translates a key using the current locale via skim\i18n\i18n::t(). Placeholders in $params are substituted into the translated string.
#AI param_details: [{name: $key | type: string | required: true | desc: Translation key such as 'auth.login' or 'items.count'.}; {name: $params | type: array | required: false | desc: Placeholders substituted into the translated string.}]
#AI return_detail: {type: string | desc: Translated and interpolated string.}
#AI examples: [{label: Pluralization | code: t('items.count', ['count' => 3])  // → "3 items"}]

#AI:asset
#AI group: Templates & Routing
#AI frequency: medium
#AI signature: function asset(string $path): string
#AI contract: Returns the URL for a Vite-managed asset. In dev mode, proxies through the Vite HMR server. In production, resolves the hashed filename from manifest.json.
#AI param_details: [{name: $path | type: string | required: true | desc: Asset path relative to project root, e.g. 'css/app.css'.}]
#AI return_detail: {type: string | desc: Public URL for the asset.}
