<?php declare(strict_types=1);

namespace skim\dev\docs\mcp;

/**
 * MCP tool implementations — reads from llm.json, no live AST at query time. #AI:class
 *
 * Use as the tool dispatch layer for the MCP server. Provides generic tools
 * (skim_overview, skim_class, skim_classes, skim_method, skim_search, skim_lifecycle, skim_examples, skim_drivers, skim_warnings, skim_config_map) plus
 * one dynamic tool per documented non-Architecture method when configured in
 * 'full' mode. Default 'minimal' mode exposes only the 10 generic query tools.
 *
 * Example:
 *   $tools = new mcp_tools('llm.json');
 *   $defs  = $tools->definitions();
 *   $result = $tools->call('skim_class', ['name' => 'cache']);
 *
 * Testing: Instantiate with a test llm.json path.
 *
 * #AI:class
 */
class mcp_tools {
    private array $data;
    private array $index = [];
    private array $overview = [];

    /**
     * Loads llm.json and builds an in-memory class index. #AI:__construct
     *
     * Indexes classes by lowercase title, class_name, and symbol for fast lookup.
     *
     * @param string $json_path Path to llm.json.
     *
     * @throws \RuntimeException If file is missing or JSON is invalid.
     */
    public function __construct(string $json_path) {
        if (!file_exists($json_path)) {
            throw new \RuntimeException("llm.json not found: {$json_path}");
        }
        $raw = file_get_contents($json_path);
        if ($raw === false) {
            throw new \RuntimeException("Cannot read: {$json_path}");
        }
        $this->data = json_decode($raw, associative: true) ?? [];
        $overview_path = dirname($json_path) . '/overview.json';
        if (file_exists($overview_path)) {
            $overview_raw = file_get_contents($overview_path);
            if ($overview_raw !== false) {
                $this->overview = json_decode($overview_raw, associative: true) ?? [];
            }
        }
        foreach ($this->data['classes'] ?? [] as $class) {
            foreach ([$class['title'] ?? '', $class['class_name'] ?? '', $class['symbol'] ?? ''] as $key) {
                $key = strtolower((string) $key);
                if ($key !== '') {
                    $this->index[$key] = $class;
                }
            }
        }
    }

    /**
     * Returns MCP tool definitions. #AI:definitions
     *
     * In 'minimal' mode (default) returns only the 5 generic query tools.
     * In 'full' mode returns generic tools plus one dynamic tool per
     * documented non-Architecture method.
     */
    public function definitions(): array {
        $generic = $this->generic_definitions();
        if (\config('docs.mcp_tools', 'minimal') === 'full') {
            return array_merge($generic, $this->method_definitions());
        }
        return $generic;
    }

    /**
     * Dispatches a tool call by name and returns the result or error array. #AI:call
     *
     * @param string $tool Tool name (generic or dynamic method tool).
     * @param array  $args Tool arguments from the MCP client.
     */
    public function call(string $tool, array $args): array {
        return match ($tool) {
            'skim_overview'   => $this->tool_overview(),
            'skim_class'      => $this->tool_class($args),
            'skim_classes'    => $this->tool_classes($args),
            'skim_method'     => $this->tool_method($args),
            'skim_search'     => $this->tool_search($args),
            'skim_lifecycle'  => $this->tool_lifecycle(),
            'skim_examples'   => $this->tool_examples($args),
            'skim_drivers'    => $this->tool_drivers($args),
            'skim_warnings'   => $this->tool_warnings($args),
            'skim_config_map' => $this->tool_config_map($args),
            default           => $this->tool_dynamic_method($tool, $args),
        };
    }

    private function generic_definitions(): array {
        return [
            [
                'name'        => 'skim_overview',
                'description' => 'Get framework overview: quickstart, architecture, capabilities, not_yet_available features, key_classes, and recommended zero-knowledge workflow. Call this first.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name'        => 'skim_class',
                'description' => 'Get class overview with summary, lifecycle, owner, file path, relationship metadata (entry_points, owns, see_also), and non_goals.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['name' => ['type' => 'string', 'description' => 'Class name (snake_case)']],
                    'required'   => ['name'],
                ],
            ],
            [
                'name'        => 'skim_classes',
                'description' => 'Flat index of all classes with name, layer, role, and badges. Use to discover what exists before diving into specifics.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['layer' => ['type' => 'string', 'description' => 'Optional layer filter (e.g. cache, session, db)']],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'skim_method',
                'description' => 'Get full method record: signature, contract, param_details, return_detail, throws_details, side_effects, examples, see_also, and aliases. Supports fuzzy/alias resolution.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'class'  => ['type' => 'string', 'description' => 'Class name (snake_case)'],
                        'method' => ['type' => 'string', 'description' => 'Method name (exact or alias)'],
                    ],
                    'required' => ['class', 'method'],
                ],
            ],
            [
                'name'        => 'skim_search',
                'description' => 'Token-aware relevance search across class names, method names, and contract text. Ranks exact token matches higher than substring hits. Use when you do not know the exact class name. Query can be a concept: caching, validation, file upload, send email. If nothing is found, the feature likely does not exist yet — check not_yet_available in skim_overview before assuming it exists.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['query' => ['type' => 'string', 'description' => 'Search query']],
                    'required'   => ['query'],
                ],
            ],
            [
                'name'        => 'skim_lifecycle',
                'description' => 'Get boot order and request lifecycle as a structured list.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
            [
                'name'        => 'skim_examples',
                'description' => 'Get code examples for a class or a specific class::method. Returns structured Example blocks extracted from PHPDoc.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'class'  => ['type' => 'string', 'description' => 'Class name (snake_case)'],
                        'method' => ['type' => 'string', 'description' => 'Optional method name. If omitted, returns class-level examples and all method examples.'],
                    ],
                    'required' => ['class'],
                ],
            ],
            [
                'name'        => 'skim_drivers',
                'description' => 'Map of class names to their available drivers. Use before writing config to know which drivers are supported.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['class' => ['type' => 'string', 'description' => 'Optional class name (snake_case). If omitted, returns all driver maps.']],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'skim_warnings',
                'description' => 'Aggregated warnings per class. Use to avoid silent production failures (e.g. missing manifest.json, wrong driver config).',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['class' => ['type' => 'string', 'description' => 'Optional class name (snake_case). If omitted, returns all warnings.']],
                    'required'   => [],
                ],
            ],
            [
                'name'        => 'skim_config_map',
                'description' => 'Map of class names to config keys they read. Use to know what needs to be configured before using a class.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['class' => ['type' => 'string', 'description' => 'Optional class name (snake_case). If omitted, returns all config maps.']],
                    'required'   => [],
                ],
            ],
        ];
    }

    private function method_definitions(): array {
        $definitions = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            foreach ($class['methods'] ?? [] as $method) {
                if (($method['group'] ?? '') === 'Architecture') {
                    continue;
                }
                if (($method['contract'] ?? '') === '' && ($method['contracts'] ?? []) === []) {
                    continue;
                }
                $definitions[] = [
                    'name'        => $this->tool_name($class, $method),
                    'description' => $method['contract'] ?? ($method['contracts'][0] ?? ''),
                    'inputSchema' => $this->input_schema($method['param_details'] ?? []),
                    'annotations' => $this->annotations_for($class, $method),
                    '_meta'       => [
                        'group'       => $method['group'] ?? '',
                        'frequency'   => $method['frequency'] ?? '',
                        'tags'        => $class['badges'] ?? [],
                        'throws'      => $method['throws_details'] ?? [],
                        'sideEffects' => $method['side_effects'] ?? [],
                    ],
                ];
            }
        }
        return $definitions;
    }

    private function tool_class(array $args): array {
        $key = strtolower($args['name'] ?? '');
        $class = $this->index[$key] ?? null;
        if ($class === null) {
            return ['error' => "Class not found: {$args['name']}"];
        }
        return [
            'class_name'   => $class['title'] ?? $class['class_name'] ?? '',
            'namespace'    => $class['namespace'] ?? '',
            'file'         => $class['source_path'] ?? $class['file'] ?? '',
            'summary'      => $class['description'] ?? $class['summary'] ?? '',
            'lifecycle'    => $class['lifecycle'] ?? '',
            'owner'        => $class['symbol'] ?? $class['owner'] ?? '',
            'entry_points' => $class['entry_points'] ?? [],
            'owns'         => $class['owns'] ?? [],
            'see_also'     => $class['see_also'] ?? [],
            'non_goals'    => [
                'class'   => $class['non_goals'] ?? [],
                'methods' => array_values(array_filter(array_map(
                    fn(array $m) => ($m['non_goals'] ?? []) === [] ? null : ['name' => $m['name'], 'non_goals' => $m['non_goals']],
                    $class['methods'] ?? []
                ))),
            ],
        ];
    }

    private function tool_method(array $args): array {
        $key = strtolower($args['class'] ?? '');
        $class = $this->index[$key] ?? null;
        if ($class === null) {
            return ['error' => "Class not found: {$args['class']}"];
        }
        return $this->resolve_method($class, $args['method'] ?? '');
    }

    private function tool_search(array $args): array {
        $query = strtolower($args['query'] ?? '');
        $q_tokens = $this->tokenize($query);
        $results = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            $class_name = (string) ($class['title'] ?? $class['class_name'] ?? '');
            $description = (string) ($class['description'] ?? $class['summary'] ?? '');
            $class_score = $this->score_match($class_name . ' ' . $description, $query, $q_tokens);
            if ($class_score > 0) {
                $results[] = ['type' => 'class', 'class' => $class_name, 'summary' => $description, 'score' => $class_score];
            }
            foreach ($class['methods'] ?? [] as $method) {
                $haystack = ($method['name'] ?? '') . ' ' . ($method['contract'] ?? '') . ' ' . implode(' ', $method['contracts'] ?? []);
                $method_score = $this->score_match($haystack, $query, $q_tokens);
                if ($method_score > 0) {
                    $results[] = ['type' => 'method', 'class' => $class_name, 'method' => $method['name'], 'score' => $method_score];
                }
            }
        }
        usort($results, fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($results, 0, 20);
    }

    private function tool_lifecycle(): array {
        $lifecycle = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            if (($class['lifecycle'] ?? '') !== '') {
                $lifecycle[] = ['class' => $class['title'] ?? $class['class_name'] ?? '', 'lifecycle' => $class['lifecycle']];
            }
        }
        return $lifecycle;
    }

    private function tool_examples(array $args): array {
        $class = $this->index[strtolower($args['class'] ?? '')] ?? null;
        if ($class === null) {
            return ['error' => "Class not found: {$args['class']}"];
        }
        $method = $args['method'] ?? '';
        if ($method !== '') {
            $resolved = $this->resolve_method($class, $method);
            if (isset($resolved['error'])) {
                return $resolved;
            }
            return ['examples' => $resolved['examples'] ?? []];
        }
        return [
            'class'    => $class['title'] ?? '',
            'examples' => $class['examples'] ?? [],
            'methods'  => array_values(array_filter(array_map(
                fn(array $m) => ($m['examples'] ?? []) === [] ? null : ['name' => $m['name'], 'examples' => $m['examples']],
                $class['methods'] ?? []
            ))),
        ];
    }

    private function tool_overview(): array {
        if ($this->overview === []) {
            return ['error' => 'overview.json not found or empty'];
        }
        return $this->overview;
    }

    private function tool_classes(array $args): array {
        $layer = strtolower($args['layer'] ?? '');
        $result = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            $class_layer = strtolower((string) ($class['layer'] ?? ''));
            if ($layer !== '' && $class_layer !== $layer) {
                continue;
            }
            $result[] = [
                'name'   => $class['title'] ?? $class['class_name'] ?? '',
                'layer'  => $class['layer'] ?? '',
                'role'   => $class['role'] ?? '',
                'badges' => $class['badges'] ?? [],
            ];
        }
        return $result;
    }

    private function tool_drivers(array $args): array {
        $filter = strtolower($args['class'] ?? '');
        $result = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            $drivers = $class['drivers'] ?? [];
            if ($drivers === []) {
                continue;
            }
            $name = $class['title'] ?? $class['class_name'] ?? '';
            if ($filter !== '' && strtolower($name) !== $filter) {
                continue;
            }
            $result[$name] = $drivers;
        }
        return $result;
    }

    private function tool_warnings(array $args): array {
        $filter = strtolower($args['class'] ?? '');
        $result = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            $warnings = $class['warnings'] ?? [];
            if ($warnings === []) {
                continue;
            }
            $name = $class['title'] ?? $class['class_name'] ?? '';
            if ($filter !== '' && strtolower($name) !== $filter) {
                continue;
            }
            $result[] = ['class' => $name, 'warnings' => $warnings];
        }
        return $result;
    }

    private function tool_config_map(array $args): array {
        $filter = strtolower($args['class'] ?? '');
        $result = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            $config_reads = $class['config_reads'] ?? [];
            if ($config_reads === []) {
                continue;
            }
            $name = $class['title'] ?? $class['class_name'] ?? '';
            if ($filter !== '' && strtolower($name) !== $filter) {
                continue;
            }
            $result[$name] = $config_reads;
        }
        return $result;
    }

    private function tool_dynamic_method(string $tool, array $args): array {
        foreach ($this->data['classes'] ?? [] as $class) {
            foreach ($class['methods'] ?? [] as $method) {
                if ($this->tool_name($class, $method) === $tool) {
                    return [
                        ...$method,
                        'class'     => $class['symbol'] ?? $class['title'] ?? '',
                        'method'    => $method['name'] ?? '',
                        'arguments' => $args,
                    ];
                }
            }
        }
        return ['error' => "Unknown tool: {$tool}"];
    }

    private function resolve_method(array $class, string $method): array {
        if ($found = $this->find_exact($class, $method)) {
            return $found;
        }

        $alias_matches = $this->find_by_alias($class, $method);
        if (count($alias_matches) === 1) {
            return $alias_matches[0];
        }

        $substring_matches = $this->find_by_substring($class, $method);
        if (count($substring_matches) === 1) {
            return $substring_matches[0];
        }

        $candidates = array_unique(array_merge($alias_matches, $substring_matches), SORT_REGULAR);
        if ($candidates !== []) {
            return [
                'status'     => 'ambiguous',
                'message'    => "Multiple methods match '{$method}'. Refine your query.",
                'candidates' => array_map(fn(array $m) => [
                    'name'      => $m['name'],
                    'signature' => $m['signature'] ?? '',
                    'contract'  => $m['contract'] ?? '',
                ], $candidates),
            ];
        }

        $class_name = $class['title'] ?? $class['class_name'] ?? 'class';
        return ['error' => "Method not found: {$class_name}::{$method}"];
    }

    private function find_exact(array $class, string $method): ?array {
        $needle = strtolower($method);
        foreach ($class['methods'] ?? [] as $m) {
            if (strtolower($m['name'] ?? '') === $needle) {
                return $m;
            }
        }
        return null;
    }

    private function find_by_alias(array $class, string $method): array {
        $needle = strtolower($method);
        $matches = [];
        foreach ($class['methods'] ?? [] as $m) {
            foreach ($m['aliases'] ?? [] as $alias) {
                if (strtolower($alias) === $needle) {
                    $matches[] = $m;
                    break;
                }
            }
        }
        return $matches;
    }

    private function find_by_substring(array $class, string $method): array {
        $needle = strtolower($method);
        $matches = [];
        foreach ($class['methods'] ?? [] as $m) {
            $name = strtolower($m['name'] ?? '');
            if (str_contains($name, $needle) || str_contains($needle, $name)) {
                $matches[] = $m;
            }
        }
        return $matches;
    }

    private function tokenize(string $input): array {
        $input = strtolower(preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', $input));
        return array_values(array_filter(preg_split('/[_\s\-]+/', $input), fn(string $t) => strlen($t) > 1));
    }

    private function score_match(string $haystack, string $query, array $q_tokens): int {
        $h_tokens = $this->tokenize($haystack);
        $score = 0;

        foreach ($q_tokens as $qt) {
            foreach ($h_tokens as $ht) {
                if ($ht === $qt) {
                    $score += 15;
                } elseif (str_starts_with($ht, $qt)) {
                    $score += 10;
                } elseif (str_contains($ht, $qt)) {
                    $score += 5;
                }
            }
        }

        if (str_contains(strtolower($haystack), $query)) {
            $score += 3;
        }

        return $score;
    }

    private function tool_name(array $class, array $method): string {
        return strtolower((string) ($class['title'] ?? $class['class_name'] ?? 'class')) . '_' . (string) ($method['name'] ?? 'method');
    }

    private function input_schema(array $params): array {
        $properties = [];
        $required = [];
        foreach ($params as $param) {
            if (!is_array($param)) {
                continue;
            }
            $name = ltrim((string) ($param['name'] ?? ''), '$');
            if ($name === '') {
                continue;
            }
            $properties[$name] = $this->schema_for_type((string) ($param['type'] ?? 'mixed'), (string) ($param['desc'] ?? ''));
            if (($param['required'] ?? false) === true) {
                $required[] = $name;
            }
        }
        return ['type' => 'object', 'properties' => $properties, 'required' => $required];
    }

    private function schema_for_type(string $type, string $description): array {
        $normalized = ltrim(strtolower($type), '?');
        if ($normalized === 'array|string' || $normalized === 'string|array') {
            return [
                'oneOf' => [['type' => 'array', 'items' => ['type' => 'string']], ['type' => 'string']],
                'description' => $description,
            ];
        }
        return match ($normalized) {
            'string' => ['type' => 'string', 'description' => $description],
            'int', 'integer' => ['type' => 'integer', 'description' => $description],
            'float', 'double' => ['type' => 'number', 'description' => $description],
            'bool', 'boolean' => ['type' => 'boolean', 'description' => $description],
            'array' => ['type' => 'array', 'description' => $description],
            'callable' => ['type' => 'string', 'description' => trim($description . ' (callable — pass as code reference)')],
            default => ['type' => 'string', 'description' => trim($description . ' (class-string or instance reference)')],
        };
    }

    private function annotations_for(array $class, array $method): array {
        $group = (string) ($method['group'] ?? '');
        $name = (string) ($method['name'] ?? '');
        return [
            'title' => ($class['title'] ?? $class['class_name'] ?? 'class') . '.' . $name,
            'readOnlyHint' => $group === 'Read API',
            'destructiveHint' => ($method['warnings'] ?? []) !== [] || $group === 'Invalidation',
            'idempotentHint' => $group === 'Read API' || in_array($name, ['has', 'flush', 'flush_all'], true),
            'openWorldHint' => in_array($name, ['tags', 'flush', 'flush_all'], true) || $group === 'Tag Operations',
        ];
    }
}

#AI:class
#AI symbol: skim\dev\docs\mcp\mcp_tools
#AI source_path: src/dev/docs/mcp/mcp_tools.php
#AI title: mcp_tools
#AI description: MCP tool implementations that query llm.json data — provides generic tools and one dynamic tool per documented method.
#AI role: MCP tool dispatch
#AI layer: dev
#AI badges: [mcp; tools; query; llm]
#AI intro: `mcp_tools` is the tool dispatch layer for the MCP server. It loads llm.json into an in-memory index and exposes generic tools (skim_class, skim_method, skim_search, skim_lifecycle, skim_examples) plus one dynamic tool per documented non-Architecture method when in 'full' mode.
#AI lifecycle: instantiated once by mcp_server.php, reused for all requests
#AI fallback: returns error arrays for missing classes/methods/tools
#AI test_seam: instantiate with a test llm.json path
#AI invariants: [class index keyed by lowercase title/class_name/symbol; Architecture methods excluded from dynamic tools; search limited to 20 results; methods without contracts excluded from dynamic tools]
#AI core_behaviors: [Builds in-memory class index for fast lookup; Generates generic MCP tool definitions; Generates one dynamic tool per documented method in full mode; Dispatches tool calls by name; Token-aware relevance search across class names, method names, and contract text; Fuzzy/alias method resolution with ambiguous candidate lists]
#AI owns: data (decoded llm.json), index (class lookup map)
#AI entry_points: [definitions; call]
#AI config_reads: [docs.mcp_tools]
#AI non_goals: [Does not read from AST at query time; Does not modify llm.json; Does not handle MCP transport]
#AI side_effects: []
#AI flow: definitions() -> generic (+ method definitions in full mode); call(name, args) -> match tool -> tool_* method -> result
#AI lifecycle_steps: [__construct(); -> load llm.json; -> build index; definitions(); -> generic_definitions() (+ method_definitions() in full mode); call(); -> match tool name; -> dispatch to tool_* method]
#AI section_order: [Construction; Tool Definitions; Tool Dispatch; Resolution; Architecture]
#AI architectural_notes: All queries run against the in-memory index — no live AST parsing at query time.

#AI:__construct
#AI group: Construction
#AI frequency: high
#AI signature: public function __construct(string $json_path)
#AI contract: Loads llm.json from the given path and builds an in-memory class index keyed by lowercase title, class_name, and symbol for fast lookup.
#AI param_details: [{name: $json_path | type: string | required: true | desc: Path to llm.json file.}]
#AI throws_details: [{type: \RuntimeException | desc: When file is missing or JSON is invalid.}]

#AI:definitions
#AI group: Tool Definitions
#AI frequency: high
#AI signature: public function definitions(): array
#AI contract: Returns the list of MCP tool definitions. In 'minimal' mode returns only the 5 generic query tools. In 'full' mode returns generic tools plus one dynamic tool per documented non-Architecture method.
#AI return_detail: {type: array | desc: Array of MCP tool definition objects with name, description, inputSchema, and annotations.}

#AI:call
#AI group: Tool Dispatch
#AI frequency: high
#AI signature: public function call(string $tool, array $args): array
#AI contract: Dispatches a tool call by name. Routes generic tools to their dedicated handlers and dynamic method tools to tool_dynamic_method.
#AI param_details: [{name: $tool | type: string | required: true | desc: Tool name (generic or dynamic method tool).}; {name: $args | type: array | required: true | desc: Tool arguments from the MCP client.}]
#AI return_detail: {type: array | desc: Result array or error array with 'error' key.}
