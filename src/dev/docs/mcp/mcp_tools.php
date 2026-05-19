<?php declare(strict_types=1);

namespace skim\dev\docs\mcp;

// All MCP tool logic — reads from llm.json, no live AST at query time.
// Called by mcp_server.php when a tool is invoked.
class mcp_tools {
    private array $data;
    /** @var array<string,array> class_name → class array */
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
            $this->index[strtolower($class['class_name'])] = $class;
        }
    }

    /**
     * @ai-contract returns tool definitions array for MCP initialize response
     */
    public function definitions(): array {
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
                'description' => 'Get signature, contracts, invariants, non_goals, side_effects, perf, and throws for a method.',
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
                'description' => 'Get all @ai.non_goal entries across the codebase, grouped by class.',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ],
        ];
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
            default          => ['error' => "Unknown tool: {$tool}"],
        };
    }

    private function tool_class(array $args): array {
        $key   = strtolower($args['name'] ?? '');
        $class = $this->index[$key] ?? null;
        if ($class === null) {
            return ['error' => "Class not found: {$args['name']}"];
        }
        return [
            'class_name' => $class['class_name'],
            'namespace'  => $class['namespace'],
            'file'       => $class['file'],
            'summary'    => $class['summary'],
            'lifecycle'  => $class['lifecycle'],
            'owner'      => $class['owner'],
        ];
    }

    private function tool_method(array $args): array {
        $key   = strtolower($args['class'] ?? '');
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
        $query   = strtolower($args['query'] ?? '');
        $results = [];

        foreach ($this->data['classes'] ?? [] as $class) {
            $score = 0;
            if (str_contains(strtolower($class['class_name']), $query)) {
                $score += 10;
            }
            if (str_contains(strtolower($class['summary'] ?? ''), $query)) {
                $score += 5;
            }
            foreach ($class['methods'] ?? [] as $method) {
                $method_score = 0;
                if (str_contains(strtolower($method['name']), $query)) {
                    $method_score += 8;
                }
                foreach ($method['contracts'] ?? [] as $contract) {
                    if (str_contains(strtolower($contract), $query)) {
                        $method_score += 3;
                    }
                }
                if ($method_score > 0) {
                    $results[] = [
                        'type'   => 'method',
                        'class'  => $class['class_name'],
                        'method' => $method['name'],
                        'score'  => $method_score,
                    ];
                }
            }
            if ($score > 0) {
                $results[] = [
                    'type'    => 'class',
                    'class'   => $class['class_name'],
                    'summary' => $class['summary'],
                    'score'   => $score,
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, 20);
    }

    private function tool_lifecycle(): array {
        $lifecycle = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            if (($class['lifecycle'] ?? '') !== '') {
                $lifecycle[] = [
                    'class'     => $class['class_name'],
                    'lifecycle' => $class['lifecycle'],
                ];
            }
        }
        return $lifecycle;
    }

    private function tool_non_goals(): array {
        $grouped = [];
        foreach ($this->data['classes'] ?? [] as $class) {
            $class_non_goals = [];
            foreach ($class['methods'] ?? [] as $method) {
                if (($method['non_goals'] ?? []) !== []) {
                    $class_non_goals[$method['name']] = $method['non_goals'];
                }
            }
            if ($class_non_goals !== []) {
                $grouped[$class['class_name']] = $class_non_goals;
            }
        }
        return $grouped;
    }
}
