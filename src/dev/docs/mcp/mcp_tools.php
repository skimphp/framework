<?php declare(strict_types=1);

namespace skim\dev\docs\mcp;

/**
 * MCP tool implementations — reads from llm.json, no live AST at query time. #AI:class
 *
 * Use as the tool dispatch layer for the MCP server. Provides generic tools
 * (skim_class, skim_method, skim_search, skim_lifecycle, skim_non_goals) plus
 * one dynamic tool per documented non-Architecture method.
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
     * Returns generic MCP tools plus one method tool per documented method. #AI:definitions
     *
     * Architecture-group methods are excluded from dynamic tool generation.
     */
    public function definitions(): array {
        return array_merge($this->generic_definitions(), $this->method_definitions());
    }

    /**
     * Dispatches a tool call by name and returns the result or error array. #AI:call
     *
     * @param string $tool Tool name (generic or dynamic method tool).
     * @param array  $args Tool arguments from the MCP client.
     */
    public function call(string $tool, array $args): array {
        return match ($tool) {
            'skim_class'     => $this->tool_class($args),
            'skim_method'    => $this->tool_method($args),
            'skim_search'    => $this->tool_search($args),
            'skim_lifecycle' => $this->tool_lifecycle(),
            'skim_non_goals' => $this->tool_non_goals(),
            default          => $this->tool_dynamic_method($tool, $args),
        };
    }

    private function generic_definitions(): array {
        return [
            [
                'name'        => 'skim_class',
                'description' => 'Get summary, lifecycle, owner, and file path for a SKIM class.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => ['name' => ['type' => 'string', 'description' => 'Class name (snake_case)']],
                    'required'   => ['name'],
                ],
            ],
            [
                'name'        => 'skim_method',
                'description' => 'Get signature, contract, params, returns, throws, and side effects for a method.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'class'  => ['type' => 'string', 'description' => 'Class name (snake_case)'],
                        'method' => ['type' => 'string', 'description' => 'Method name'],
                    ],
                    'required' => ['class', 'method'],
                ],
            ],
            [
                'name'        => 'skim_search',
                'description' => 'Fuzzy search across class names, method names, and contract text.',
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
                'name'        => 'skim_non_goals',
                'description' => 'Get non-goal entries across the codebase, grouped by class.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
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
            'class_name' => $class['title'] ?? $class['class_name'] ?? '',
            'namespace'  => $class['namespace'] ?? '',
            'file'       => $class['source_path'] ?? $class['file'] ?? '',
            'summary'    => $class['description'] ?? $class['summary'] ?? '',
            'lifecycle'  => $class['lifecycle'] ?? '',
            'owner'      => $class['symbol'] ?? $class['owner'] ?? '',
        ];
    }

    private function tool_method(array $args): array {
        $key = strtolower($args['class'] ?? '');
        $class = $this->index[$key] ?? null;
        if ($class === null) {
            return ['error' => "Class not found: {$args['class']}"];
        }
        $method_name = strtolower($args['method'] ?? '');
        foreach ($class['methods'] ?? [] as $method) {
            if (strtolower($method['name']) === $method_name) {
                return $method;
            }
        }
        return ['error' => "Method not found: {$args['class']}::{$args['method']}"];
    }

    private function tool_search(array $args): array {
        $query = strtolower($args['query'] ?? '');
        $results = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            $class_name = (string) ($class['title'] ?? $class['class_name'] ?? '');
            $description = (string) ($class['description'] ?? $class['summary'] ?? '');
            if (str_contains(strtolower($class_name . ' ' . $description), $query)) {
                $results[] = ['type' => 'class', 'class' => $class_name, 'summary' => $description, 'score' => 10];
            }
            foreach ($class['methods'] ?? [] as $method) {
                $haystack = strtolower(($method['name'] ?? '') . ' ' . ($method['contract'] ?? '') . ' ' . implode(' ', $method['contracts'] ?? []));
                if (str_contains($haystack, $query)) {
                    $results[] = ['type' => 'method', 'class' => $class_name, 'method' => $method['name'], 'score' => 8];
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

    private function tool_non_goals(): array {
        $grouped = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            $items = [];
            if (($class['non_goals'] ?? []) !== []) {
                $items['class'] = $class['non_goals'];
            }
            foreach ($class['methods'] ?? [] as $method) {
                if (($method['non_goals'] ?? []) !== []) {
                    $items[$method['name']] = $method['non_goals'];
                }
            }
            if ($items !== []) {
                $grouped[$class['title'] ?? $class['class_name'] ?? 'class'] = $items;
            }
        }
        return $grouped;
    }

    private function tool_dynamic_method(string $tool, array $args): array {
        foreach ($this->data['classes'] ?? [] as $class) {
            foreach ($class['methods'] ?? [] as $method) {
                if ($this->tool_name($class, $method) === $tool) {
                    return [
                        'class'     => $class['symbol'] ?? $class['title'] ?? '',
                        'method'    => $method['name'] ?? '',
                        'arguments' => $args,
                        'contract'  => $method['contract'] ?? '',
                        'return'    => $method['return_detail'] ?? [],
                    ];
                }
            }
        }
        return ['error' => "Unknown tool: {$tool}"];
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
#AI intro: `mcp_tools` is the tool dispatch layer for the MCP server. It loads llm.json into an in-memory index and exposes generic tools (skim_class, skim_method, skim_search, skim_lifecycle, skim_non_goals) plus one dynamic tool per documented non-Architecture method.
#AI lifecycle: instantiated once by mcp_server.php, reused for all requests
#AI fallback: returns error arrays for missing classes/methods/tools
#AI test_seam: instantiate with a test llm.json path
#AI invariants: [class index keyed by lowercase title/class_name/symbol; Architecture methods excluded from dynamic tools; search limited to 20 results; methods without contracts excluded from dynamic tools]
#AI core_behaviors: [Builds in-memory class index for fast lookup; Generates generic MCP tool definitions; Generates one dynamic tool per documented method; Dispatches tool calls by name; Fuzzy search across class names, method names, and contract text]
#AI owns: data (decoded llm.json), index (class lookup map)
#AI entry_points: [definitions; call]
#AI config_reads: []
#AI non_goals: [Does not read from AST at query time; Does not modify llm.json; Does not handle MCP transport]
#AI side_effects: []
#AI flow: definitions() -> generic + method definitions; call(name, args) -> match tool -> tool_* method -> result
#AI lifecycle_steps: [__construct(); -> load llm.json; -> build index; definitions(); -> generic_definitions() + method_definitions(); call(); -> match tool name; -> dispatch to tool_* method]
#AI section_order: [Construction; Tool Definitions; Tool Dispatch; Architecture]
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
#AI contract: Returns the full list of MCP tool definitions — generic tools plus one dynamic tool per documented non-Architecture method.
#AI return_detail: {type: array | desc: Array of MCP tool definition objects with name, description, inputSchema, and annotations.}

#AI:call
#AI group: Tool Dispatch
#AI frequency: high
#AI signature: public function call(string $tool, array $args): array
#AI contract: Dispatches a tool call by name. Routes generic tools to their dedicated handlers and dynamic method tools to tool_dynamic_method.
#AI param_details: [{name: $tool | type: string | required: true | desc: Tool name (generic or dynamic method tool).}; {name: $args | type: array | required: true | desc: Tool arguments from the MCP client.}]
#AI return_detail: {type: array | desc: Result array or error array with 'error' key.}
