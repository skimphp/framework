<?php declare(strict_types=1);

use Skim\Core\Config;
use Skim\Core\Env;
use Skim\Core\Router;

if (!function_exists('env')) {
    /**
     * Reads an environment variable with optional fallback. #AI:env
     *
     * @param string $key     Dotenv key, e.g. 'DB_HOST'.
     * @param mixed  $default Returned when the variable is missing.
     */
    function env(string $key, mixed $default = null): mixed {
        return \Skim\Core\Env::get($key, $default);
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
        return \Skim\Core\Config::get($key, $default);
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
        return \Skim\Core\Router::url($name, $params);
    }
}

if (!function_exists('storagePath')) {
    /**
     * Absolute path under the .skim/ directory. #AI:storagePath
     *
     * Resolves SKIM_ROOT when defined, falls back to getcwd().
     *
     * Example:
     *   storagePath('logs/app.log')  // → /var/www/myapp/.skim/logs/app.log
     *
     * @param string $path Sub-path appended to .skim root. Empty returns the root itself.
     */
    function storagePath(string $path = ''): string {
        $base = defined('SKIM_ROOT') ? SKIM_ROOT . '/.skim' : getcwd() . '/.skim';
        return $path !== '' ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('basePath')) {
    /**
     * Absolute path to the project root. #AI:basePath
     *
     * @param string $path Sub-path appended to root. Empty returns root itself.
     */
    function basePath(string $path = ''): string {
        $base = defined('SKIM_ROOT') ? SKIM_ROOT : getcwd();
        return $path !== '' ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('e')) {
    /**
     * HTML-escapes a string for safe template output. #AI:e
     *
     * Always call on user-supplied data — skipping is an XSS vulnerability.
     * Pass `$doubleEncode = false` when the value already contains entities.
     *
     * @param string $value          Raw string to escape.
     * @param bool   $doubleEncode  Re-encode existing entities when true.
     */
    function e(string $value, bool $doubleEncode = true): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }
}

if (!function_exists('component')) {
    /**
     * Renders a component by name. #AI:component
     *
     * Supports both array props (legacy) and *_props readonly objects (new).
     * Components render in clean scope with no parent data leakage.
     *
     * Example:
     *   component('alert', ['message' => 'test']);
     *   component('alert', new AlertProps(message: 'test'));
     *
     * @param string $name Component name (maps to views/components/{$name}.php).
     * @param array|object $props Props array or *_props readonly object.
     */
    function component(string $name, array|object $props = []): string {
        return \Skim\View\View::component($name, $props);
    }
}

if (!function_exists('componentWithParts')) {
    /**
     * Renders a component with named parts via closures. #AI:componentWithParts
     *
     * Use for card headers, modal footers, or any component that needs
     * multiple named content blocks passed from the call site.
     *
     * Example:
     *   componentWithParts('card', new CardProps(title: 'Profile'), function($c) {
     *       $c->part('header', fn() => '<h2>Profile</h2>');
     *       echo '<p>Main content</p>';
     *   });
     *
     * @param string       $name   Component name (maps to views/components/{$name}.php).
     * @param array|object $props  Props array or *_props readonly object.
     * @param callable     $render Closure receiving the component_collector instance.
     */
    function componentWithParts(string $name, array|object $props, callable $render): string {
        $collector = new \Skim\View\ComponentCollector();
        $collector->captureMain(function() use ($render, $collector) {
            $render($collector);
        });

        \Skim\View\ComponentCollector::push($collector);
        try {
            return \Skim\View\ComponentRenderer::render($name, $props);
        } finally {
            \Skim\View\ComponentCollector::pop();
        }
    }
}

if (!function_exists('part')) {
    /**
     * Returns a named part from the active component collector. #AI:part
     *
     * When called without $name, returns the main body content.
     * Returns $default when the part is absent or no component is rendering.
     *
     * @param string $name    Part identifier, or empty string for main body.
     * @param string $default Fallback HTML when the part is absent.
     */
    function part(string $name = '', string $default = ''): string {
        $collector = \Skim\View\ComponentCollector::current();
        if ($collector === null) {
            return $default;
        }
        return $name === '' ? $collector->getMain() : $collector->getPart($name, $default);
    }
}

if (!function_exists('hasPart')) {
    /**
     * Checks if a named part exists in the active component collector. #AI:hasPart
     *
     * Returns false when no component is currently rendering.
     *
     * @param string $name Part identifier to check.
     */
    function hasPart(string $name): bool {
        $collector = \Skim\View\ComponentCollector::current();
        return $collector !== null && $collector->hasPart($name);
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
        return \Skim\I18n\I18n::t($key, $params);
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
        return \Skim\Assets\Assets::url($path);
    }
}

#AI:file
#AI source_path: src/Core/helpers.php
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
#AI entry_points: [env; config; route; storagePath; basePath; e; component; componentWithParts; part; hasPart; t; asset]
#AI config_reads: [app.*; db.*; cache.*]
#AI non_goals: [Does not add behavior beyond the underlying facades; Does not replace facade usage in application controllers or models]
#AI side_effects: [none — all functions are pure delegation]
#AI section_order: [Config & Env; Templates & Routing]

#AI:env
#AI group: Config & Env
#AI frequency: high
#AI signature: function env(string $key, mixed $default = null): mixed
#AI contract: Reads an environment variable by key, returning $default when the variable is not set. Delegates to Env::get().
#AI param_details: [{name: $key | type: string | required: true | desc: Dotenv key such as 'DB_HOST' or 'APP_NAME'.}; {name: $default | type: mixed | required: false | desc: Fallback value returned when the variable is missing.}]
#AI return_detail: {type: mixed | desc: The environment variable value or $default.}

#AI:config
#AI group: Config & Env
#AI frequency: high
#AI signature: function config(string $key, mixed $default = null): mixed
#AI contract: Reads a dot-separated config value from the loaded config files. Returns $default when the key path does not exist.
#AI param_details: [{name: $key | type: string | required: true | desc: Dot-path such as 'app.name' or 'db.default.host'.}; {name: $default | type: mixed | required: false | desc: Fallback value returned when the key is not set.}]
#AI return_detail: {type: mixed | desc: The config value or $default.}

#AI:basePath
#AI group: Config & Env
#AI frequency: medium
#AI signature: function basePath(string $path = ''): string
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

#AI:storagePath
#AI group: Templates & Routing
#AI frequency: medium
#AI signature: function storagePath(string $path = ''): string
#AI contract: Returns the absolute path under the .skim/ directory. Uses SKIM_ROOT when defined, falls back to getcwd(). Appends the optional sub-path.
#AI param_details: [{name: $path | type: string | required: false | desc: Sub-path under .skim/. Empty string returns the .skim root itself.}]
#AI return_detail: {type: string | desc: Absolute filesystem path under .skim/.}
#AI examples: [{label: Log file path | code: storagePath('logs/app.log')  // → /var/www/myapp/.skim/logs/app.log}]

#AI:e
#AI group: Templates & Routing
#AI frequency: high
#AI signature: function e(string $value, bool $doubleEncode = true): string
#AI contract: HTML-escapes a string using htmlspecialchars with ENT_QUOTES and UTF-8. Always call on user-supplied data to prevent XSS. Pass $doubleEncode = false when the value already contains HTML entities.
#AI param_details: [{name: $value | type: string | required: true | desc: Raw string to escape.}; {name: $doubleEncode | type: bool | required: false | desc: When false, existing HTML entities are not re-encoded.}]
#AI return_detail: {type: string | desc: HTML-safe escaped string.}
#AI warnings: [Skipping e() on user-supplied data is an XSS vulnerability]

#AI:component
#AI group: Templates & Routing
#AI frequency: high
#AI signature: function component(string $name, array|object $props = []): string
#AI contract: Renders an isolated component in clean scope. Supports array props (legacy) and *_props readonly objects (new).
#AI param_details: [{name: $name | type: string | required: true | desc: Component name (maps to views/components/{$name}.php).}; {name: $props | type: array|object | required: false | desc: Props array or *_props readonly object.}]
#AI return_detail: {type: string | desc: Rendered component HTML.}
#AI throws_details: [{type: \Skim\View\Exceptions\ViewException | desc: If props object is not a *_props class or component file is not found.}]

#AI:componentWithParts
#AI group: Templates & Routing
#AI frequency: medium
#AI signature: function componentWithParts(string $name, array|object $props, callable $render): string
#AI contract: Renders a component with named parts captured via closures. The closure receives a ComponentCollector to declare parts. Parts and main body are injected into the component template.
#AI param_details: [{name: $name | type: string | required: true | desc: Component name (maps to views/components/{$name}.php).}; {name: $props | type: array|object | required: true | desc: Props array or *_props readonly object.}; {name: $render | type: callable | required: true | desc: Closure receiving the ComponentCollector instance.}]
#AI return_detail: {type: string | desc: Rendered component HTML with parts injected.}
#AI throws_details: [{type: \Skim\View\Exceptions\ViewException | desc: If component file is not found.}]

#AI:part
#AI group: Templates & Routing
#AI frequency: high
#AI signature: function part(string $name = '', string $default = ''): string
#AI contract: Returns a named part from the active component collector. Empty $name returns the main body. Returns $default when no component is rendering or the part is absent.
#AI param_details: [{name: $name | type: string | required: false | desc: Part identifier, or empty string for main body.}; {name: $default | type: string | required: false | desc: Fallback HTML when the part is absent.}]
#AI return_detail: {type: string | desc: Part HTML or default value.}

#AI:hasPart
#AI group: Templates & Routing
#AI frequency: medium
#AI signature: function hasPart(string $name): bool
#AI contract: Returns true when the named part exists in the active component collector. Returns false when no component is rendering.
#AI param_details: [{name: $name | type: string | required: true | desc: Part identifier to check.}]
#AI return_detail: {type: bool | desc: True if the part exists and a component is rendering.}

#AI:t
#AI group: Templates & Routing
#AI frequency: medium
#AI signature: function t(string $key, array $params = []): string
#AI contract: Translates a key using the current locale via Skim\I18n\I18n::t(). Placeholders in $params are substituted into the translated string.
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
