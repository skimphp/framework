<?php declare(strict_types=1);

namespace skim\dev\docs\mcp;

// All MCP tool logic — reads from llm.json, no live AST at query time.
class mcp_tools {
    private array $data;
    private array $index = [];

    /**
     * @ai-contract loads llm.json from $json_path and builds an in-memory class index
     * @ai-contract throws \RuntimeException if file is missing or JSON is invalid
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
     * @ai-contract returns generic MCP tools plus one method tool per non-Architecture documented method
     */
    public function definitions(): array {
        return array_merge($this->generic_definitions(), $this->method_definitions());
    }

    /**
     * @ai-contract dispatches tool call by name; returns result array or error array
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
