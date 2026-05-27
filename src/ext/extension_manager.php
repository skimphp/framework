<?php declare(strict_types=1);

namespace skim\ext;

use skim\core\app;

/**
 * Coordinates discovered extension lifecycle hooks.
 */
final class extension_manager {
    private array $instances = [];

    /**
     * @ai-contract extensions are normalized metadata arrays from ext_registry::installed()
     */
    public function __construct(
        private readonly array $extensions,
    ) {}

    /**
     * @ai-contract discovers extensions, validates dependencies, topologically sorts them,
     *              stores metadata in sys.extensions, and returns a lifecycle manager
     */
    public static function discover(string $root, app $app): self {
        $registry   = new ext_registry($root);
        $extensions = $registry->installed();

        self::validate_dependencies($extensions);
        $extensions = self::topological_sort($extensions);

        $app->set('sys.extensions', $extensions);
        $app->set('sys.extension_conflicts', $registry->conflicts());

        return new self($extensions);
    }

    /**
     * @ai-contract calls register(app) for every discovered extension in priority order
     */
    public function register(app $app): void {
        $conflicts = $app->get('sys.extension_conflicts', []);
        if ($conflicts !== []) {
            throw new \RuntimeException('Extension conflict: ' . $conflicts[0]['message']);
        }

        foreach ($this->extensions as $extension) {
            $instance = $this->instance($extension);
            if (!method_exists($instance, 'register')) {
                continue;
            }

            $app->with_extension_context(
                $extension['name'],
                (int) $extension['priority'],
                fn(): mixed => $instance->register($app),
            );
        }
    }

    /**
     * @ai-contract calls boot(app) for every discovered extension in priority order
     */
    public function boot(app $app): void {
        foreach ($this->extensions as $extension) {
            $instance = $this->instance($extension);
            if (!method_exists($instance, 'boot')) {
                continue;
            }

            $app->with_extension_context(
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
     * @ai-contract throws RuntimeException when a required capability or name is not provided by any installed extension
     */
    private static function validate_dependencies(array $extensions): void {
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
     * @ai-contract returns extensions sorted so dependencies always come before dependents
     * @ai-contract throws RuntimeException on circular dependency
     */
    private static function topological_sort(array $extensions): array {
        $by_name    = [];
        $providers  = [];
        foreach ($extensions as $ext) {
            $by_name[$ext['name']] = $ext;
            $providers[$ext['name']] = $ext['name'];
            foreach (array_merge($ext['provides'], $ext['capabilities']) as $cap) {
                $providers[$cap] = $ext['name'];
            }
        }

        $sorted   = [];
        $visited  = [];
        $visiting = [];

        $visit = function(array $ext) use (&$visit, &$sorted, &$visited, &$visiting, $by_name, $providers): void {
            $name = $ext['name'];
            if (isset($visited[$name])) {
                return;
            }
            if (isset($visiting[$name])) {
                throw new \RuntimeException("Circular dependency detected involving extension '{$name}'.");
            }
            $visiting[$name] = true;
            foreach ($ext['requires'] as $req) {
                $dep_name = $providers[$req] ?? null;
                if ($dep_name !== null && isset($by_name[$dep_name])) {
                    $visit($by_name[$dep_name]);
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
