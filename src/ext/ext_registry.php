<?php declare(strict_types=1);

namespace skim\ext;

/**
 * Reads installed SKIM extension metadata from Composer packages.
 */
final class ext_registry {
    private ?array $installed = null;
    private ?array $capability_map = null;
    private ?array $conflicts = null;

    /**
     * @ai-contract root is the project directory containing vendor/
     */
    public function __construct(
        private readonly string $root,
    ) {}

    /**
     * @ai-contract returns installed extension metadata from vendor package composer.json files
     * @ai-contract caches scan results for the lifetime of this registry instance
     *
     * @return array<int,array{name:string,version:string,description:string,class:string,type:string,priority:int,requires:array,provides:array,conflicts:array,capabilities:array,capability_details:array,config:array,migrations:bool,commands:array,env:array,post_install:array,path:string}>
     */
    public function installed(): array {
        if ($this->installed !== null) {
            return $this->installed;
        }

        $extensions = [];
        foreach ($this->composer_files() as $file) {
            $json = json_decode((string) file_get_contents($file), true);
            if (!is_array($json)) {
                continue;
            }

            $metadata = $this->metadata_from_package(dirname($file), $json);
            if ($metadata === null) {
                continue;
            }

            $extensions[] = $metadata;
        }

        usort($extensions, static fn(array $a, array $b): int => [$a['priority'], $a['name']] <=> [$b['priority'], $b['name']]);

        return $this->installed = $extensions;
    }

    /**
     * @ai-contract returns metadata for one package name, or null when not installed
     */
    public function find(string $name): ?array {
        foreach ($this->installed() as $extension) {
            if ($extension['name'] === $name) {
                return $extension;
            }
        }

        return null;
    }

    /**
     * @ai-contract clears cached scan data so a later installed package can be discovered
     */
    public function refresh(): void {
        $this->installed = null;
        $this->capability_map = null;
        $this->conflicts = null;
    }

    /**
     * @return array<int,array>
     */
    public static function all(?string $root = null): array {
        return (new self($root ?? base_path()))->installed();
    }

    public function __call(string $method, array $args): mixed {
        return match ($method) {
            'has_capability' => $this->has_capability_value((string) ($args[0] ?? '')),
            'who_provides'   => $this->who_provides_value((string) ($args[0] ?? '')),
            'conflicts'      => $this->conflict_list(),
            default          => throw new \BadMethodCallException("Method {$method} does not exist."),
        };
    }

    public static function __callStatic(string $method, array $args): mixed {
        $root = isset($args[1]) && is_string($args[1]) ? $args[1] : base_path();
        $registry = new self($root);

        return match ($method) {
            'has_capability' => $registry->has_capability_value((string) ($args[0] ?? '')),
            'who_provides'   => $registry->who_provides_value((string) ($args[0] ?? '')),
            'conflicts'      => $registry->conflict_list(),
            default          => throw new \BadMethodCallException("Static method {$method} does not exist."),
        };
    }

    private function has_capability_value(string $capability): bool {
        return isset($this->capability_map()[$capability]);
    }

    private function who_provides_value(string $capability): ?string {
        return $this->capability_map()[$capability] ?? null;
    }

    /**
     * @return array<int,array{message:string,extensions:array,capabilities:array}>
     */
    private function conflict_list(): array {
        $this->capability_map();

        return $this->conflicts ?? [];
    }

    /**
     * @return array<string,string>
     */
    public function capability_map(): array {
        if ($this->capability_map !== null) {
            return $this->capability_map;
        }

        $map = [];
        $owners = [];
        $conflicts = [];
        foreach ($this->installed() as $extension) {
            $name = $extension['name'];
            foreach (array_unique(array_merge($extension['provides'], $extension['capabilities'])) as $capability) {
                if (isset($owners[$capability]) && $owners[$capability] !== $name) {
                    $pair = [$owners[$capability], $name];
                    sort($pair);
                    $key = implode('|', $pair) . ':' . $capability;
                    $conflicts[$key] = [
                        'message'      => 'both ' . $owners[$capability] . ' and ' . $name . ' provide [' . $capability . ']',
                        'extensions'   => [$owners[$capability], $name],
                        'capabilities' => [$capability],
                    ];
                    continue;
                }

                $owners[$capability] = $name;
                $map[$capability] = $name;
            }
        }

        foreach ($this->installed() as $extension) {
            foreach ($extension['conflicts'] as $target) {
                foreach ($this->installed() as $other) {
                    if ($other['name'] === $extension['name']) {
                        continue;
                    }

                    if ($other['name'] === $target || in_array($target, $other['provides'], true) || in_array($target, $other['capabilities'], true)) {
                        $pair = [$extension['name'], $other['name']];
                        sort($pair);
                        $key = implode('|', $pair) . ':' . $target;
                        $conflicts[$key] = [
                            'message'      => $extension['name'] . ' conflicts with ' . $other['name'] . ' [' . $target . ']',
                            'extensions'   => [$extension['name'], $other['name']],
                            'capabilities' => [$target],
                        ];
                    }
                }
            }
        }

        $this->conflicts = array_values($conflicts);

        return $this->capability_map = $map;
    }

    private function composer_files(): array {
        $vendor = rtrim($this->root, '/') . '/vendor';
        if (!is_dir($vendor)) {
            return [];
        }

        $files = array_merge(
            glob($vendor . '/*/composer.json') ?: [],
            glob($vendor . '/*/*/composer.json') ?: [],
        );

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    private function extension_type(mixed $type): string {
        $type = is_string($type) ? $type : 'feature';

        return match ($type) {
            'feature', 'enhancement', 'replacement' => $type,
            default => 'feature',
        };
    }

    private function metadata_from_package(string $path, array $composer): ?array {
        $skim = $composer['extra']['skim'] ?? [];
        $class = is_array($skim) && isset($skim['extension']) && is_string($skim['extension'])
            ? $skim['extension']
            : '';

        $manifest_path = $path . '/skim.json';
        if (is_file($manifest_path)) {
            $manifest = json_decode((string) file_get_contents($manifest_path), true);
            if (!is_array($manifest)) {
                return null;
            }

            return $this->normalize(
                package: $composer,
                skim: is_array($skim) ? $skim : [],
                manifest: $manifest,
                path: $path,
                class: $class,
            );
        }

        if ($class === '') {
            return null;
        }

        $manifest = [];
        if (class_exists($class) && is_subclass_of($class, extension::class)) {
            $static = $class::manifest();
            if ($static !== []) {
                $manifest = $static;
            } else {
                $ext = new $class();
                $manifest = [
                    'name'         => $ext->name,
                    'version'      => $ext->version,
                    'description'  => $ext->description,
                    'requires'     => $ext->requires,
                    'provides'     => $ext->provides,
                    'conflicts'    => $ext->conflicts,
                    'capabilities' => $ext->capabilities,
                    'config'       => $ext->config(),
                    'migrations'   => $ext->migrations() !== '',
                    'commands'     => array_keys($ext->commands()),
                    'env_keys'     => $ext->env_keys(),
                    'post_install' => $ext->post_install(),
                ];
            }
        }

        return $this->normalize(
            package: $composer,
            skim: is_array($skim) ? $skim : [],
            manifest: $manifest,
            path: $path,
            class: $class,
        );
    }

    private function normalize(array $package, array $skim, array $manifest, string $path, string $class): array {
        $commands = $manifest['commands'] ?? $skim['commands'] ?? [];
        if (is_array($commands) && array_is_list($commands)) {
            $commands = array_fill_keys($commands, true);
        }

        $raw_caps           = $manifest['capabilities'] ?? $skim['capabilities'] ?? [];
        $capabilities_list  = is_array($raw_caps) && !array_is_list($raw_caps)
            ? array_keys($raw_caps)
            : array_values((array) $raw_caps);
        $capability_details = is_array($raw_caps) && !array_is_list($raw_caps)
            ? $raw_caps
            : [];

        return [
            'name'               => (string) (($manifest['name'] ?? '') ?: ($package['name'] ?? basename($path))),
            'version'            => (string) (($manifest['version'] ?? '') ?: ($package['version'] ?? 'dev')),
            'description'        => (string) (($manifest['description'] ?? '') ?: ($package['description'] ?? '')),
            'class'              => $class,
            'type'               => $this->extension_type($skim['type'] ?? 'feature'),
            'priority'           => (int) ($skim['priority'] ?? extension_priority::USER),
            'requires'           => array_values((array) ($manifest['requires'] ?? $skim['requires'] ?? [])),
            'provides'           => array_values((array) ($manifest['provides'] ?? $skim['provides'] ?? [])),
            'conflicts'          => array_values((array) ($manifest['conflicts'] ?? $skim['conflicts'] ?? [])),
            'capabilities'       => $capabilities_list,
            'capability_details' => $capability_details,
            'config'             => (array) ($manifest['config'] ?? []),
            'migrations'         => (bool) ($manifest['migrations'] ?? false),
            'commands'           => $commands,
            'env'                => array_values((array) ($manifest['env_keys'] ?? $manifest['env'] ?? $skim['env'] ?? [])),
            'post_install'       => array_values((array) ($manifest['post_install'] ?? $skim['post_install'] ?? [])),
            'path'               => $path,
        ];
    }
}
