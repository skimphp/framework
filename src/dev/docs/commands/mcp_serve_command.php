<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;

/**
 * Launches the PHP stdio MCP server for LLM tool integration. #AI:class
 *
 * Use when Claude Code, Cursor, or other MCP-capable clients need to query
 * the project's @ai.* annotations at runtime. Requires llm.json to exist —
 * run docs:extract first.
 *
 * Example:
 *   php skim mcp:serve
 *   # Wire in .claude/mcp_settings.json: "args": ["skim", "mcp:serve"]
 *
 * Testing: Requires llm.json on disk; no static state to reset.
 *
 * #AI:class
 */
class mcp_serve_command extends command {
    /**
     * Starts mcp_server.php as a long-running stdio process. #AI:handle
     *
     * Reads JSON-RPC from stdin, writes responses to stdout.
     * Returns 1 if llm.json or the server script is missing.
     *
     * @return int Exit code from the child process, or 1 on pre-flight failure.
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

#AI:class
#AI symbol: skim\dev\docs\commands\mcp_serve_command
#AI source_path: src/dev/docs/commands/mcp_serve_command.php
#AI title: mcp_serve_command
#AI description: CLI command that launches the stdio MCP server for LLM tool integration with Claude Code, Cursor, etc.
#AI role: MCP server launcher
#AI layer: dev
#AI badges: [cli; mcp; stdio; llm]
#AI intro: `mcp_serve_command` spawns `mcp_server.php` as a child process communicating over stdio. It validates that llm.json and the server script exist before launching, and forwards the child's exit code.
#AI lifecycle: instantiated by CLI router, runs until stdin closes or Ctrl+C
#AI fallback: none — returns 1 when prerequisites are missing
#AI test_seam: instantiate directly; requires llm.json on disk
#AI invariants: [requires llm.json to exist; requires mcp_server.php to exist; forwards child exit code]
#AI core_behaviors: [Validates llm.json and server script exist; Spawns mcp_server.php via passthru; Forwards child process exit code]
#AI owns: child process lifecycle
#AI entry_points: [handle]
#AI config_reads: [docs.output.json]
#AI non_goals: [Does not implement MCP protocol logic; Does not generate llm.json]
#AI side_effects: [spawns long-running child process on stdio]
#AI flow: handle() -> validate llm.json -> validate mcp_server.php -> passthru(PHP_BINARY mcp_server.php llm.json)
#AI lifecycle_steps: [handle(); -> check llm.json exists; -> check mcp_server.php exists; -> passthru child process; -> forward exit code]
#AI section_order: [Pipeline; Architecture]
#AI architectural_notes: Transport-only launcher; all MCP protocol logic lives in mcp_server.php and mcp_tools.

#AI:handle
#AI group: Pipeline
#AI frequency: medium
#AI signature: public function handle(): int
#AI contract: Validates prerequisites and spawns the MCP server as a child process over stdio. Returns 1 when llm.json or the server script is missing.
#AI return_detail: {type: int | desc: Exit code from the child MCP server process, or 1 on pre-flight failure.}
#AI side_effects: [spawns long-running child process]
