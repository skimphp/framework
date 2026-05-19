#!/usr/bin/env php
<?php declare(strict_types=1);

// PHP stdio MCP server — reads JSON-RPC 2.0 from stdin, writes responses to stdout.
// Invoked by mcp_serve_command: php mcp_server.php /path/to/llm.json
// All tool logic lives in mcp_tools.php — this file is transport only.

define('SKIM_ROOT', dirname(__DIR__, 4));
require SKIM_ROOT . '/vendor/autoload.php';

$json_path = $argv[1] ?? (SKIM_ROOT . '/llm.json');

try {
    $tools = new \skim\dev\docs\mcp\mcp_tools($json_path);
} catch (\Throwable $e) {
    fwrite(STDERR, "MCP server error: " . $e->getMessage() . "\n");
    exit(1);
}

$server_info = [
    'name'    => 'skim-mcp',
    'version' => '0.1.0',
];

while (true) {
    $line = fgets(STDIN);
    if ($line === false) {
        break;
    }
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $request = json_decode($line, associative: true);
    if (!is_array($request)) {
        write_response(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
        continue;
    }

    $id     = $request['id'] ?? null;
    $method = $request['method'] ?? '';

    $result = match ($method) {
        'initialize' => [
            'protocolVersion' => '2024-11-05',
            'serverInfo'      => $server_info,
            'capabilities'    => ['tools' => []],
        ],
        'tools/list' => [
            'tools' => $tools->definitions(),
        ],
        'tools/call' => handle_tool_call($request, $tools),
        default      => null,
    };

    if ($result === null) {
        write_response([
            'jsonrpc' => '2.0',
            'id'      => $id,
            'error'   => ['code' => -32601, 'message' => "Method not found: {$method}"],
        ]);
        continue;
    }

    write_response(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
}

function handle_tool_call(array $request, \skim\dev\docs\mcp\mcp_tools $tools): array {
    $params = $request['params'] ?? [];
    $name   = $params['name'] ?? '';
    $args   = $params['arguments'] ?? [];

    try {
        $data = $tools->call($name, $args);
        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]],
        ];
    } catch (\Throwable $e) {
        return [
            'content'   => [['type' => 'text', 'text' => $e->getMessage()]],
            'isError'   => true,
        ];
    }
}

function write_response(array $payload): void {
    echo json_encode($payload) . "\n";
    fflush(STDOUT);
}
