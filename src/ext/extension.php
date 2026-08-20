<?php declare(strict_types=1);

namespace skim\ext;

use skim\core\app;

/**
 * Base class for SKIM extensions — declares metadata and lifecycle hooks.
 *
 * Use as the parent class when building a new extension package. Override
 * register() and boot() for lifecycle hooks; set public properties for
 * metadata; override config(), commands(), etc. for optional contributions.
 *
 * Example:
 *   class my_auth_extension extends extension {
 *       public string $name = 'acme/auth';
 *       public string $version = '1.0.0';
 *       public array $capabilities = ['auth', 'session-auth'];
 *
 *       public function register(app $app): void { ... }
 *       public function boot(app $app): void { ... }
 *   }
 *
 * #AI:class
 */
abstract class extension {
    public string $name        = '';
    public string $version     = '';
    public string $description = '';

    public array $requires     = [];
    public array $provides     = [];
    public array $conflicts    = [];
    public array $capabilities = [];

    abstract public function register(app $app): void;
    abstract public function boot(app $app): void;

    /**
     * Returns the path to the extension's migrations directory. #AI:migrations
     *
     * Empty string means no migrations. Override to return __DIR__ . '/migrations'.
     */
    public function migrations(): string  { return ''; }

    /**
     * Returns config key-value pairs to merge into the app container. #AI:config
     */
    public function config(): array       { return []; }

    /**
     * Returns CLI command class map: name => class. #AI:commands
     */
    public function commands(): array     { return []; }

    /**
     * Returns .env keys this extension expects. #AI:env_keys
     */
    public function env_keys(): array     { return []; }

    /**
     * Returns post-install CLI step descriptions. #AI:post_install
     */
    public function post_install(): array { return []; }

    /**
     * Returns static metadata without instantiation. #AI:manifest
     *
     * Override to provide metadata without requiring the class to be loaded.
     * An empty array signals ext_registry to fall back to instance properties.
     * The capabilities key may be a flat list or an associative map of
     * capability => details.
     */
    public static function manifest(): array { return []; }
}

#AI:class
#AI symbol: skim\ext\extension
#AI source_path: src/ext/extension.php
#AI title: extension
#AI description: Abstract base class for SKIM extensions with metadata properties and lifecycle hooks.
#AI role: extension base class
#AI layer: ext
#AI badges: [abstract; extension; lifecycle]
#AI intro: `extension` is the abstract base class that all SKIM extension packages extend. It defines metadata properties (name, version, capabilities) and two lifecycle hooks (register, boot) that extension_manager calls during app boot.
#AI lifecycle: instantiated by extension_manager::instance() after discovery; register() called before boot()
#AI test_seam: extend and override methods in test fixtures
#AI invariants: [register() runs before boot(); manifest() returns empty array by default; all metadata properties default to empty]
#AI core_behaviors: [Declares metadata via public properties; Contributes config, commands, migrations, env keys, and post-install steps; Lifecycle hooks receive the app container]
#AI owns: extension metadata
#AI entry_points: [register; boot; manifest]
#AI config_reads: []
#AI non_goals: [Does not auto-discover itself; Does not validate its own metadata; Does not resolve dependencies]
#AI side_effects: [register() and boot() may mutate the app container]
#AI flow: extension_manager::discover() -> instance() -> register(app) -> boot(app)
#AI section_order: [Lifecycle Hooks; Metadata Accessors; Static Metadata]

#AI:register
#AI group: Lifecycle Hooks
#AI frequency: high
#AI signature: abstract public function register(app $app): void
#AI contract: Called during the registration phase. Bind services, register routes, declare config. Runs before boot().
#AI param_details: [{name: $app | type: app | required: true | desc: The application container instance.}]

#AI:boot
#AI group: Lifecycle Hooks
#AI frequency: high
#AI signature: abstract public function boot(app $app): void
#AI contract: Called after all extensions have registered. Start services, warm caches, attach event listeners.
#AI param_details: [{name: $app | type: app | required: true | desc: The application container instance.}]

#AI:migrations
#AI group: Metadata Accessors
#AI frequency: low
#AI signature: public function migrations(): string
#AI contract: Returns the path to the extension's migrations directory. Empty string means no migrations.
#AI return_detail: {type: string | desc: Absolute path to migrations directory, or empty string.}

#AI:config
#AI group: Metadata Accessors
#AI frequency: low
#AI signature: public function config(): array
#AI contract: Returns config key-value pairs that the extension contributes to the app container.
#AI return_detail: {type: array | desc: Config key-value pairs.}

#AI:commands
#AI group: Metadata Accessors
#AI frequency: low
#AI signature: public function commands(): array
#AI contract: Returns a map of CLI command names to their class strings.
#AI return_detail: {type: array | desc: Command name => class map.}

#AI:env_keys
#AI group: Metadata Accessors
#AI frequency: low
#AI signature: public function env_keys(): array
#AI contract: Returns the list of .env variable names this extension requires.
#AI return_detail: {type: array | desc: List of env key strings.}

#AI:post_install
#AI group: Metadata Accessors
#AI frequency: low
#AI signature: public function post_install(): array
#AI contract: Returns descriptions of post-install CLI steps this extension needs.
#AI return_detail: {type: array | desc: List of step description strings.}

#AI:manifest
#AI group: Static Metadata
#AI frequency: low
#AI signature: public static function manifest(): array
#AI contract: Returns static metadata without instantiation. An empty array signals ext_registry to fall back to instance properties. The capabilities key may be a flat list or an associative map.
#AI return_detail: {type: array | desc: Metadata array or empty array for fallback.}
#AI notes: Override in subclasses to avoid class instantiation during discovery.
