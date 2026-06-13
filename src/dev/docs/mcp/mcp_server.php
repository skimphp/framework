#!/usr/bin/env php
<?php declare(strict_types=1);

#AI:class
#AI symbol: skim\dev\docs\mcp\mcp_server
#AI source_path: src/dev/docs/mcp/mcp_server.php
#AI title: mcp_server
#AI description: PHP stdio MCP server transport — reads JSON-RPC 2.0 from stdin, writes responses to stdout.
#AI role: MCP stdio transport
#AI layer: dev
#AI badges: [mcp; stdio; transport; json-rpc]
#AI intro: `mcp_server.php` is the transport layer for the MCP protocol. It reads JSON-RPC 2.0 requests from stdin, dispatches to mcp_tools for tool execution, and writes responses to stdout. All tool logic lives in mcp_tools — this file handles only protocol framing.
#AI lifecycle: launched by mcp_serve_command, runs until stdin closes
#AI fallback: returns JSON-RPC error on parse failure or unknown method
#AI test_seam: invoke directly with a test llm.json path as argv[1]
#AI invariants: [reads one JSON line per request; writes one JSON line per response; exits on stdin EOF; parse errors return -32700; unknown methods return -32601]
#AI core_behaviors: [Reads JSON-RPC 2.0 from stdin line by line; Dispatches initialize, tools/list, and tools/call methods; Delegates tool execution to mcp_tools; Writes JSON responses to stdout]
#AI owns: mcp_tools instance
#AI entry_points: [main loop; handle_tool_call; write_response]
#AI config_reads: []
#AI non_goals: [Does not implement tool logic; Does not validate tool arguments; Does not handle HTTP transport]
#AI side_effects: [reads stdin; writes stdout; writes stderr on fatal errors]
#AI flow: stdin -> json_decode -> match method -> mcp_tools -> json_encode -> stdout
#AI lifecycle_steps: [load llm.json path from argv; -> create mcp_tools; -> while stdin; -> json_decode request; -> match method; -> dispatch to mcp_tools; -> write_response]
#AI section_order: [Transport; Architecture]
#AI architectural_notes: This is a script file, not a class. All tool logic is delegated to mcp_tools. The script handles only JSON-RPC 2.0 framing and stdio I/O.

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

    // Notifications (no id) — silently consume, no response
    if ($id === null && str_starts_with($method, 'notifications/')) {
        continue;
    }

    $result = match ($method) {
        'initialize' => [
            'protocolVersion' => '2024-11-05',
            'serverInfo'      => $server_info,
            'capabilities'    => (object) [],
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
