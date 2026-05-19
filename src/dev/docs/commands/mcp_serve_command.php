<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;

// Launches the PHP stdio MCP server.
// Claude Code / Cursor wires this as: php skim mcp:serve
// Usage: php skim mcp:serve
class mcp_serve_command extends command {
    /**
     * @ai-contract starts mcp_server.php as a long-running stdio process
     * @ai-contract reads JSON-RPC from stdin, writes responses to stdout
     * @ai-contract requires llm.json to exist — run docs:extract first
     * @ai-contract returns 1 if llm.json is missing, 0 after normal shutdown
     */
    public function handle(): int {
        $json_path = config('docs.output.json', base_path('llm.json'));

        if (!file_exists($json_path)) {
            $this->error("llm.json not found at {$json_path} — run 'php skim docs:extract' first.");
            return 1;
        }

        $server_path = dirname(__DIR__) . '/mcp/mcp_server.php';

        if (!file_exists($server_path)) {
            $this->error("MCP server not found at {$server_path}");
            return 1;
        }

        $this->muted('Starting MCP server (stdio)…');
        $cmd  = PHP_BINARY . ' ' . escapeshellarg($server_path) . ' ' . escapeshellarg($json_path);
        passthru($cmd, $exit);
        return (int) $exit;
    }
}
