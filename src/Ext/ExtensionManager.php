<?php declare(strict_types=1);

namespace Skim\Ext;

use Skim\Core\App;

/**
 * Coordinates discovered extension lifecycle: discovery, validation, and boot.
 *
 * Use during app boot to discover extensions from Composer packages,
 * validate their dependencies, topologically sort them, and invoke
 * register()/boot() hooks in the correct order.
 *
 * Example:
 *   $manager = extensionManager::discover(basePath(), $app);
 *   $manager->register($app);
 *   $manager->boot($app);
 *
 * #AI:class
 */
final class ExtensionManager {
    private array $instances = [];

    /**
     * Accepts normalized extension metadata arrays from ext_registry. #AI:__construct
     *
     * @param array $extensions Extension metadata arrays from ExtRegistry::installed().
     */
    public function __construct(
        private readonly array $extensions,
    ) {}

    /**
     * Discovers, validates, and sorts extensions, then stores metadata in the app. #AI:discover
     *
     * Scans Composer packages via ext_registry, validates dependency
     * requirements, topologically sorts by dependency graph, and stores
     * the result in sys.extensions and sys.extension_conflicts.
     *
     * Example:
     *   $manager = extensionManager::discover(basePath(), $app);
     *
     * @param string $root Project root directory containing vendor/.
     * @param \Skim\Core\App $app Application container to store extension metadata.
     * @throws \RuntimeException On missing dependency or circular dependency.
     */
    public static function discover(string $root, \Skim\Core\App $app): self {
        $registry   = new \Skim\Ext\ExtRegistry($root);
        $extensions = $registry->installed();

        self::validateDependencies($extensions);
        $extensions = self::topologicalSort($extensions);

        $app->set('sys.extensions', $extensions);
        $app->set('sys.extension_conflicts', $registry->conflicts());

        return new self($extensions);
    }

    /**
     * Calls register(app) for every extension in priority order. #AI:register
     *
     * Throws immediately if any extension conflict was detected during
     * discovery. Each extension's register() runs inside an extension
     * context for profiler attribution.
     *
     * @param \Skim\Core\App $app Application container.
     * @throws \RuntimeException When extension conflicts exist.
     */
    public function register(\Skim\Core\App $app): void {
        $conflicts = $app->get('sys.extension_conflicts', []);
        if ($conflicts !== []) {
            throw new \RuntimeException('Extension conflict: ' . $conflicts[0]['message']);
        }

        foreach ($this->extensions as $extension) {
            $instance = $this->instance($extension);
            if (!method_exists($instance, 'register')) {
                continue;
            }

            $app->withExtensionContext(
                $extension['name'],
                (int) $extension['priority'],
                fn(): mixed => $instance->register($app),
            );
        }
    }

    /**
     * Calls boot(app) for every extension in priority order. #AI:boot
     *
     * Runs after all extensions have been registered. Each extension's
     * boot() runs inside an extension context for profiler attribution.
     *
     * @param \Skim\Core\App $app Application container.
     */
    public function boot(\Skim\Core\App $app): void {
        foreach ($this->extensions as $extension) {
            $instance = $this->instance($extension);
            if (!method_exists($instance, 'boot')) {
                continue;
            }

            $app->withExtensionContext(
                $extension['name'],
                (int) $extension['priority'],
                fn(): mixed => $instance->boot($app),
            );
        }
    }

    private function instance(array $extension): object {
        $name = (string) $extension['name'];
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $this->validate($extension);

        $class = (string) $extension['class'];
        if (!class_exists($class)) {
            throw new \RuntimeException("Extension '{$name}' entry point '{$class}' is not autoloadable.");
        }

        return $this->instances[$name] = new $class();
    }

    private function validate(array $extension): void {
        if (($extension['type'] ?? 'feature') === 'replacement' && ($extension['conflicts'] ?? []) === []) {
            throw new \RuntimeException("Replacement extension '{$extension['name']}' must declare conflict scope.");
        }
    }

    /**
     * Validates that all required capabilities are provided by installed extensions. #AI:validateDependencies
     *
     * @param array $extensions Extension metadata arrays.
     * @throws \RuntimeException When a required capability is not provided by any extension.
     */
    private static function validateDependencies(array $extensions): void {
        $available = [];
        foreach ($extensions as $ext) {
            $available[] = $ext['name'];
            foreach ($ext['provides'] as $cap) {
                $available[] = $cap;
            }
            foreach ($ext['capabilities'] as $cap) {
                $available[] = $cap;
            }
        }
        $available = array_unique($available);

        foreach ($extensions as $ext) {
            foreach ($ext['requires'] as $req) {
                if (!in_array($req, $available, true)) {
                    throw new \RuntimeException(
                        "Extension '{$ext['name']}' requires '{$req}' but no installed extension provides it."
                    );
                }
            }
        }
    }

    /**
     * Returns extensions sorted so dependencies always come before dependents. #AI:topologicalSort
     *
     * @param array $extensions Extension metadata arrays.
     * @return array Topologically sorted extensions.
     * @throws \RuntimeException On circular dependency.
     */
    private static function topologicalSort(array $extensions): array {
        $byName    = [];
        $providers  = [];
        foreach ($extensions as $ext) {
            $byName[$ext['name']] = $ext;
            $providers[$ext['name']] = $ext['name'];
            foreach (array_merge($ext['provides'], $ext['capabilities']) as $cap) {
                $providers[$cap] = $ext['name'];
            }
        }

        $sorted   = [];
        $visited  = [];
        $visiting = [];

        $visit = function(array $ext) use (&$visit, &$sorted, &$visited, &$visiting, $byName, $providers): void {
            $name = $ext['name'];
            if (isset($visited[$name])) {
                return;
            }
            if (isset($visiting[$name])) {
                throw new \RuntimeException("Circular dependency detected involving extension '{$name}'.");
            }
            $visiting[$name] = true;
            foreach ($ext['requires'] as $req) {
                $depName = $providers[$req] ?? null;
                if ($depName !== null && isset($byName[$depName])) {
                    $visit($byName[$depName]);
                }
            }
            unset($visiting[$name]);
            $visited[$name] = true;
            $sorted[]       = $ext;
        };

        foreach ($extensions as $ext) {
            $visit($ext);
        }

        return $sorted;
    }
}

#AI:class
#AI symbol: Skim\Ext\ExtensionManager
#AI source_path: src/Ext/ExtensionManager.php
#AI title: extensionManager
#AI description: Coordinates extension discovery, dependency validation, topological sorting, and lifecycle hooks.
#AI role: extension lifecycle coordinator
#AI layer: ext
#AI badges: [extension; lifecycle; discovery; dependency-graph]
#AI intro: `extensionManager` discovers extensions from Composer packages, validates their dependency requirements, topologically sorts them, and invokes register()/boot() hooks in the correct order during app boot.
#AI lifecycle: created by discover() during app boot; register() then boot() called sequentially
#AI test_seam: construct with mock extension metadata arrays; test with empty extensions list
#AI invariants: [register() runs before boot(); dependencies are validated before sorting; circular dependencies throw; replacement extensions must declare conflicts]
#AI core_behaviors: [Discovers via ExtRegistry; Validates requires against provides+capabilities; Topological sort ensures dependency order; Calls register/boot within extension context for profiler]
#AI owns: extension instances cache
#AI entry_points: [discover; register; boot]
#AI config_reads: []
#AI non_goals: [Does not install or download extensions; Does not resolve version conflicts; Does not hot-reload extensions at runtime]
#AI side_effects: [Stores sys.extensions and sys.extension_conflicts in app container; Instantiates extension classes; Calls register() and boot() hooks]
#AI flow: discover() -> ExtRegistry::installed() -> validateDependencies() -> topologicalSort() -> store in app; register() -> foreach: instance()->register(app); boot() -> foreach: instance()->boot(app)
#AI section_order: [Discovery; Lifecycle Hooks; Architecture]

#AI:__construct
#AI group: Architecture
#AI frequency: internal
#AI signature: public function __construct(array $extensions)
#AI contract: Accepts normalized extension metadata arrays from ExtRegistry::installed().
#AI param_details: [{name: $extensions | type: array | required: true | desc: Extension metadata arrays from ExtRegistry.}]

#AI:discover
#AI group: Discovery
#AI frequency: high
#AI signature: public static function discover(string $root, App $app): self
#AI contract: Scans Composer packages for extensions, validates dependencies, topologically sorts them, and stores metadata in the app container.
#AI param_details: [{name: $root | type: string | required: true | desc: Project root directory containing vendor/.}; {name: $app | type: app | required: true | desc: Application container to store extension metadata.}]
#AI return_detail: {type: self | desc: Configured extensionManager ready for register()/boot().}
#AI throws_details: [{type: \RuntimeException | desc: On missing dependency or circular dependency.}]
#AI side_effects: [Stores sys.extensions and sys.extension_conflicts in app container]

#AI:register
#AI group: Lifecycle Hooks
#AI frequency: high
#AI signature: public function register(App $app): void
#AI contract: Calls register(app) for every extension in dependency-sorted order. Throws immediately if conflicts exist.
#AI param_details: [{name: $app | type: app | required: true | desc: Application container.}]
#AI throws_details: [{type: \RuntimeException | desc: When extension conflicts are detected.}]
#AI side_effects: [Calls extension register() hooks; Mutates app container via extension registrations]

#AI:boot
#AI group: Lifecycle Hooks
#AI frequency: high
#AI signature: public function boot(App $app): void
#AI contract: Calls boot(app) for every extension in dependency-sorted order. Runs after all extensions have been registered.
#AI param_details: [{name: $app | type: app | required: true | desc: Application container.}]
#AI side_effects: [Calls extension boot() hooks]

#AI:validateDependencies
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function validateDependencies(array $extensions): void
#AI contract: Throws RuntimeException when a required capability or name is not provided by any installed extension.
#AI param_details: [{name: $extensions | type: array | required: true | desc: Extension metadata arrays.}]
#AI throws_details: [{type: \RuntimeException | desc: When a required capability is not provided.}]

#AI:topologicalSort
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function topologicalSort(array $extensions): array
#AI contract: Returns extensions sorted so dependencies always come before dependents. Throws on circular dependency.
#AI param_details: [{name: $extensions | type: array | required: true | desc: Extension metadata arrays.}]
#AI return_detail: {type: array | desc: Topologically sorted extension metadata arrays.}
#AI throws_details: [{type: \RuntimeException | desc: On circular dependency.}]
