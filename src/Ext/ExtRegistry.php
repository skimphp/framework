<?php declare(strict_types=1);

namespace Skim\Ext;

/**
 * Reads installed SKIM extension metadata from Composer packages.
 *
 * Use during app boot to discover extensions from vendor/ packages.
 * Scans composer.json files for extra.skim configuration and optional
 * skim.json manifests. Caches results for the instance lifetime.
 *
 * Example:
 *   $registry = new ExtRegistry(basePath());
 *   $extensions = $registry->installed();
 *   $auth = $registry->find('acme/auth');
 *
 * Testing: Construct with a temp directory containing mock vendor/ structure.
 *
 * #AI:class
 */
final class ExtRegistry {
    private ?array $installed = null;
    private ?array $capabilityMap = null;
    private ?array $conflicts = null;

    /**
     * Sets the project root directory containing vendor/. #AI:__construct
     *
     * @param string $root Project root path.
     */
    public function __construct(
        private readonly string $root,
    ) {}

    /**
     * Returns installed extension metadata from vendor package composer.json files. #AI:installed
     *
     * Scans vendor package composer.json files for extra.skim
     * configuration. Results are cached for the instance lifetime. Extensions
     * are sorted by priority then name.
     *
     * @return array<int,array{name:string,version:string,description:string,class:string,type:string,priority:int,requires:array,provides:array,conflicts:array,capabilities:array,capability_details:array,config:array,migrations:bool,commands:array,env:array,post_install:array,path:string}>
     */
    public function installed(): array {
        if ($this->installed !== null) {
            return $this->installed;
        }

        if ($this->loadCompiledCache()) {
            return $this->installed;
        }

        $extensions = [];
        foreach ($this->composerFiles() as $file) {
            $json = json_decode((string) file_get_contents($file), true);
            if (!is_array($json)) {
                continue;
            }

            $metadata = $this->metadataFromPackage(dirname($file), $json);
            if ($metadata === null) {
                continue;
            }

            $extensions[] = $metadata;
        }

        usort($extensions, static fn(array $a, array $b): int => [$a['priority'], $a['name']] <=> [$b['priority'], $b['name']]);

        $this->installed = $extensions;
        $this->writeCompiledCache();

        return $this->installed;
    }

    /**
     * Returns metadata for one package by name, or null when not installed. #AI:find
     *
     * @param string $name Package name to search for (e.g. 'acme/auth').
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
     * Clears cached scan data so a later installed package can be discovered. #AI:refresh
     */
    public function refresh(): void {
        $this->installed = null;
        $this->capabilityMap = null;
        $this->conflicts = null;

        $cachePath = $this->root . '/.skim/config_cache/extensions.php';
        if (is_file($cachePath)) {
            unlink($cachePath);
        }
    }

    /**
     * Convenience: returns all installed extensions using the given or default root. #AI:all
     *
     * @param string|null $root Project root, or null for basePath().
     * @return array<int,array>
     */
    public static function all(?string $root = null): array {
        return (new self($root ?? basePath()))->installed();
    }

    /**
     * Dynamic dispatch for has_capability, who_provides, and conflicts. #AI:__call
     *
     * @param string $method Method name.
     * @param array  $args   Arguments.
     * @throws \BadMethodCallException When method is not recognized.
     */
    public function __call(string $method, array $args): mixed {
        return match ($method) {
            'has_capability' => $this->hasCapabilityValue((string) ($args[0] ?? '')),
            'who_provides'   => $this->whoProvidesValue((string) ($args[0] ?? '')),
            'conflicts'      => $this->conflictList(),
            default          => throw new \BadMethodCallException("Method {$method} does not exist."),
        };
    }

    /**
     * Static dynamic dispatch for has_capability, who_provides, and conflicts. #AI:__callStatic
     *
     * @param string $method Method name.
     * @param array  $args   Arguments.
     * @throws \BadMethodCallException When method is not recognized.
     */
    public static function __callStatic(string $method, array $args): mixed {
        $root = isset($args[1]) && is_string($args[1]) ? $args[1] : basePath();
        $registry = new self($root);

        return match ($method) {
            'has_capability' => $registry->hasCapabilityValue((string) ($args[0] ?? '')),
            'who_provides'   => $registry->whoProvidesValue((string) ($args[0] ?? '')),
            'conflicts'      => $registry->conflictList(),
            default          => throw new \BadMethodCallException("Static method {$method} does not exist."),
        };
    }

    private function hasCapabilityValue(string $capability): bool {
        return isset($this->capabilityMap()[$capability]);
    }

    private function whoProvidesValue(string $capability): ?string {
        return $this->capabilityMap()[$capability] ?? null;
    }

    /**
     * @return array<int,array{message:string,extensions:array,capabilities:array}>
     */
    private function conflictList(): array {
        $this->capabilityMap();

        return $this->conflicts ?? [];
    }

    /**
     * Builds a map of capability => providing extension name. #AI:capabilityMap
     *
     * Also detects conflicts: duplicate capability providers and declared
     * conflict targets. Populates $this->conflicts as a side effect.
     *
     * @return array<string,string> Capability name => extension name map.
     */
    public function capabilityMap(): array {
        if ($this->capabilityMap !== null) {
            return $this->capabilityMap;
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

        return $this->capabilityMap = $map;
    }

    /**
     * Attempts to load extension metadata from a pre-compiled PHP array cache. #AI:loadCompiledCache
     *
     * WHY: Pure arrays allow OPcache shared-memory hit with zero parse overhead.
     * Returns false when the cache file is missing or stale (dev mode with
     * APP_DEBUG=true and vendor/composer/installed.json newer than cache).
     */
    private function loadCompiledCache(): bool {
        $cachePath = $this->root . '/.skim/config_cache/extensions.php';
        if (!is_file($cachePath)) {
            return false;
        }

        $debug = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? false;
        $installedJson = $this->root . '/vendor/composer/installed.json';
        $installedPhp  = $this->root . '/vendor/composer/installed.php';

        if ($debug) {
            if (is_file($installedJson) && filemtime($installedJson) > filemtime($cachePath)) {
                return false;
            }
            if (is_file($installedPhp) && filemtime($installedPhp) > filemtime($cachePath)) {
                return false;
            }
        }

        $cache = require $cachePath;
        if (!is_array($cache)) {
            return false;
        }
        $this->installed = $cache;
        return true;
    }

    /**
     * Writes the scanned extension metadata to a compiled PHP array cache file. #AI:write_compiled_cache
     *
     * WHY: Pure arrays allow OPcache shared-memory hit with zero parse overhead.
     */
    private function writeCompiledCache(): void {
        $cacheDir = $this->root . '/.skim/config_cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        @file_put_contents(
            $cacheDir . '/extensions.php',
            '<?php return ' . var_export($this->installed, true) . ';'
        );
    }

    private function composerFiles(): array {
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

    private function extensionType(mixed $type): string {
        $type = is_string($type) ? $type : 'feature';

        return match ($type) {
            'feature', 'enhancement', 'replacement' => $type,
            default => 'feature',
        };
    }

    private function metadataFromPackage(string $path, array $composer): ?array {
        $skim = $composer['extra']['skim'] ?? [];
        $class = is_array($skim) && isset($skim['extension']) && is_string($skim['extension'])
            ? $skim['extension']
            : '';

        $manifestPath = $path . '/skim.json';
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
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
        if (class_exists($class) && is_subclass_of($class, \Skim\Ext\Extension::class)) {
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
                    'env_keys'     => $ext->envKeys(),
                    'post_install' => $ext->postInstall(),
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

        $rawCaps           = $manifest['capabilities'] ?? $skim['capabilities'] ?? [];
        $capabilitiesList  = is_array($rawCaps) && !array_is_list($rawCaps)
            ? array_keys($rawCaps)
            : array_values((array) $rawCaps);
        $capabilityDetails = is_array($rawCaps) && !array_is_list($rawCaps)
            ? $rawCaps
            : [];

        return [
            'name'               => (string) (($manifest['name'] ?? '') ?: ($package['name'] ?? basename($path))),
            'version'            => (string) (($manifest['version'] ?? '') ?: ($package['version'] ?? 'dev')),
            'description'        => (string) (($manifest['description'] ?? '') ?: ($package['description'] ?? '')),
            'class'              => $class,
            'type'               => $this->extensionType($skim['type'] ?? 'feature'),
            'priority'           => (int) ($skim['priority'] ?? \Skim\Ext\ExtensionPriority::USER),
            'requires'           => array_values((array) ($manifest['requires'] ?? $skim['requires'] ?? [])),
            'provides'           => array_values((array) ($manifest['provides'] ?? $skim['provides'] ?? [])),
            'conflicts'          => array_values((array) ($manifest['conflicts'] ?? $skim['conflicts'] ?? [])),
            'capabilities'       => $capabilitiesList,
            'capability_details' => $capabilityDetails,
            'config'             => (array) ($manifest['config'] ?? []),
            'migrations'         => (bool) ($manifest['migrations'] ?? false),
            'commands'           => $commands,
            'env'                => array_values((array) ($manifest['env_keys'] ?? $manifest['env'] ?? $skim['env'] ?? [])),
            'post_install'       => array_values((array) ($manifest['post_install'] ?? $skim['post_install'] ?? [])),
            'path'               => $path,
        ];
    }
}

#AI:class
#AI symbol: Skim\Ext\ExtRegistry
#AI source_path: src/Ext/ExtRegistry.php
#AI title: ExtRegistry
#AI description: Discovers installed SKIM extensions from Composer packages with capability mapping and conflict detection.
#AI role: extension discovery registry
#AI layer: ext
#AI badges: [extension; discovery; registry; composer; capability-map]
#AI intro: `ExtRegistry` scans vendor/ Composer packages for SKIM extension metadata. It reads extra.skim from composer.json and optional skim.json manifests, builds a capability map, detects conflicts, and caches results for the instance lifetime.
#AI lifecycle: instantiated per-discovery; results cached until refresh()
#AI test_seam: construct with temp directory containing mock vendor/ structure
#AI invariants: [Results cached per instance; skim.json takes priority over class instantiation; Extensions sorted by priority then name; Capability conflicts detected across all installed extensions]
#AI core_behaviors: [Scans vendor/*/composer.json and vendor/*/*/composer.json; Reads extra.skim.extension for class name; Falls back to skim.json manifest; Builds capability map with conflict detection]
#AI owns: extension metadata cache, capability map, conflict list
#AI entry_points: [installed; find; refresh; all; capabilityMap]
#AI config_reads: []
#AI non_goals: [Does not install or download packages; Does not validate extension classes beyond autoload check; Does not resolve version constraints]
#AI side_effects: [Reads composer.json and skim.json files from vendor/]
#AI flow: installed() -> composerFiles() -> metadataFromPackage() -> normalize() -> sort by priority; capabilityMap() -> iterate installed -> detect duplicate providers + declared conflicts
#AI section_order: [Discovery API; Lookup API; Capability API; Dynamic Dispatch; Cache Management]

#AI:__construct
#AI group: Discovery API
#AI frequency: low
#AI signature: public function __construct(string $root)
#AI contract: Sets the project root directory containing vendor/ for extension scanning.
#AI param_details: [{name: $root | type: string | required: true | desc: Project root path containing vendor/ directory.}]

#AI:installed
#AI group: Discovery API
#AI frequency: high
#AI signature: public function installed(): array
#AI contract: Scans vendor/ for extension metadata from composer.json extra.skim and skim.json manifests. Results are cached for the instance lifetime.
#AI return_detail: {type: array | desc: Sorted array of extension metadata arrays with name, version, class, capabilities, etc.}

#AI:find
#AI group: Lookup API
#AI frequency: medium
#AI signature: public function find(string $name): ?array
#AI contract: Returns metadata for one extension by package name, or null when not installed.
#AI param_details: [{name: $name | type: string | required: true | desc: Package name to search for, e.g. 'acme/auth'.}]
#AI return_detail: {type: ?array | desc: Extension metadata array or null.}

#AI:refresh
#AI group: Cache Management
#AI frequency: low
#AI signature: public function refresh(): void
#AI contract: Clears all cached scan data so a later installed package can be discovered on the next installed() call.
#AI side_effects: [Clears installed, capabilityMap, and conflicts caches]

#AI:all
#AI group: Discovery API
#AI frequency: low
#AI signature: public static function all(?string $root = null): array
#AI contract: Convenience static method that creates a registry with the given or default root and returns installed extensions.
#AI param_details: [{name: $root | type: ?string | required: false | desc: Project root, or null for basePath().}]
#AI return_detail: {type: array | desc: Extension metadata arrays.}

#AI:__call
#AI group: Dynamic Dispatch
#AI frequency: medium
#AI signature: public function __call(string $method, array $args): mixed
#AI contract: Dispatches has_capability, who_provides, and conflicts dynamically.
#AI param_details: [{name: $method | type: string | required: true | desc: Method name: has_capability, who_provides, or conflicts.}; {name: $args | type: array | required: true | desc: Method arguments.}]
#AI throws_details: [{type: \BadMethodCallException | desc: When method is not recognized.}]

#AI:__callStatic
#AI group: Dynamic Dispatch
#AI frequency: low
#AI signature: public static function __callStatic(string $method, array $args): mixed
#AI contract: Static dispatch for has_capability, who_provides, and conflicts. Creates a new registry with basePath() or provided root.
#AI param_details: [{name: $method | type: string | required: true | desc: Method name.}; {name: $args | type: array | required: true | desc: Method arguments.}]
#AI throws_details: [{type: \BadMethodCallException | desc: When method is not recognized.}]

#AI:capabilityMap
#AI group: Capability API
#AI frequency: medium
#AI signature: public function capabilityMap(): array
#AI contract: Builds and returns a map of capability name => providing extension name. Detects duplicate providers and declared conflicts as a side effect.
#AI return_detail: {type: array<string,string> | desc: Capability name => extension name map.}
#AI side_effects: [Populates internal conflicts list]
