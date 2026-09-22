<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Commands;

use Skim\Cli\Command;

/**
 * Launches the native skim-mcp binary for LLM tool integration. #AI:class
 *
 * Use when Claude Code, Cursor, or other MCP-capable clients need to query
 * the project's @ai.* annotations at runtime. Requires llm.json to exist —
 * run docs:extract first, and the binary — run mcp:install first.
 *
 * Example:
 *   php skim mcp:serve
 *   # Wire in .claude/mcp_settings.json: "args": ["skim", "mcp:serve"]
 *
 * Testing: Requires llm.json on disk and binary installed; no static state.
 *
 * #AI:class
 */
class McpServeCommand extends \Skim\Cli\Command {
    /**
     * Starts the skim-mcp binary as a long-running stdio process. #AI:handle
     *
     * Forwards stdin/stdout directly via passthru. Returns 1 if prerequisites
     * are missing, otherwise forwards the binary's exit code.
     *
     * @return int Exit code from the child process, or 1 on pre-flight failure.
     */
    public function handle(): int {
        $jsonPath = config('docs.output.json', basePath('llm.json'));

        if (!file_exists($jsonPath)) {
            $this->error("llm.json not found at {$jsonPath} — run 'php skim docs:extract' first.");
            return 1;
        }

        $cmd = $this->resolveRunner();
        if ($cmd === null) {
            $this->error("No MCP runner found. Run one of:\n  php skim mcp:install  (native binary)\n  npm install -g @skim/mcp  (node package)");
            return 1;
        }

        passthru($cmd, $exit);
        return (int) $exit;
    }

    /**
     * Resolves the command string to launch the MCP server. #AI:resolveRunner
     *
     * Priority: native binary → node bundle → null.
     */
    private function resolveRunner(): ?string {
        $projectDir = escapeshellarg(basePath());

        // 1. Native binary (installed via mcp:install)
        $native = basePath('.skim/bin/skim-mcp');
        if (PHP_OS_FAMILY === 'Windows') {
            $native .= '.exe';
        }
        if (file_exists($native) && $this->isNativeRunnable($native)) {
            return escapeshellarg($native) . ' --project-dir=' . $projectDir;
        }

        // 2. Node.js bundle (Docker, CI, or dev without native binary)
        $node = $this->findExecutable('node');
        if ($node !== null) {
            $bundle = $this->findBundle();
            if ($bundle !== null) {
                return escapeshellarg($node) . ' ' . escapeshellarg($bundle) . ' --project-dir=' . $projectDir;
            }
        }

        return null;
    }

    /**
     * Finds a JS bundle path — dev or composer-installed. #AI:findBundle
     */
    private function findBundle(): ?string {
        $candidates = [
            basePath('mcp/dist/index.js'),                       // framework is root
            basePath('vendor/skim/framework/mcp/dist/index.js'),  // composer dependency
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }
        return null;
    }

    /**
     * Verifies the native binary can actually execute on this platform. #AI:isNativeRunnable
     *
     * Prevents macOS binaries from being selected inside Linux Docker.
     * Uses the `file` command when available; falls back to a quick exec test.
     */
    private function isNativeRunnable(string $path): bool {
        if (!is_executable($path)) return false;

        // Best-effort platform check via `file`
        $fileOutput = shell_exec('file ' . escapeshellarg($path) . ' 2>/dev/null');
        if ($fileOutput !== null) {
            $fileOutput = strtolower($fileOutput);
            // Shell scripts are cross-platform — skip format check for them
            if (!str_contains($fileOutput, 'script')) {
                $family = strtolower(PHP_OS_FAMILY);
                $platformOk = match ($family) {
                    'darwin' => str_contains($fileOutput, 'mach-o'),
                    'linux' => str_contains($fileOutput, 'elf'),
                    'windows' => str_contains($fileOutput, 'pe32') || str_contains($fileOutput, 'pe32+'),
                    default => true,
                };
                if (!$platformOk) return false;
            }
        }

        // Quick exec test — if the binary starts and doesn't die with format error
        $exit = -1;
        $proc = @proc_open(
            [$path, '--project-dir=/dev/null'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (is_resource($proc)) {
            foreach ($pipes as $p) fclose($p);
            $exit = proc_close($proc);
        }
        return $exit !== 126 && $exit !== 127;
    }

    /**
     * Locates an executable in PATH. #AI:findExecutable
     */
    private function findExecutable(string $name): ?string {
        $paths = explode(PATH_SEPARATOR, getenv('PATH') ?: '');
        $isWin = PHP_OS_FAMILY === 'Windows';
        foreach ($paths as $dir) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $name . ($isWin ? '.exe' : '');
            if (is_executable($candidate)) return $candidate;
        }
        // Also check `which` / `where` via shell
        $cmd = $isWin ? "where {$name} 2>nul" : "which {$name} 2>/dev/null";
        $output = shell_exec($cmd);
        if ($output) {
            $path = trim($output);
            if ($path !== '' && file_exists($path)) return $path;
        }
        return null;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Commands\McpServeCommand
#AI source_path: src/Dev/Docs/Commands/McpServeCommand.php
#AI title: McpServeCommand
#AI description: CLI command that launches the stdio MCP server for LLM tool integration with Claude Code, Cursor, etc.
#AI role: MCP server launcher
#AI layer: dev
#AI badges: [cli; mcp; stdio; llm]
#AI intro: `McpServeCommand` spawns the `skim-mcp` native binary as a child process communicating over stdio. It validates that llm.json and the binary exist before launching, and forwards the child's exit code.
#AI lifecycle: instantiated by CLI router, runs until stdin closes or Ctrl+C
#AI fallback: none — returns 1 when prerequisites are missing
#AI test_seam: instantiate directly; requires llm.json on disk
#AI invariants: [requires llm.json to exist; requires skim-mcp binary to exist; forwards child exit code]
#AI core_behaviors: [Validates llm.json and skim-mcp binary exist; Spawns skim-mcp binary via passthru; Forwards child process exit code]
#AI owns: child process lifecycle
#AI entry_points: [handle]
#AI config_reads: [docs.output.json]
#AI non_goals: [Does not implement MCP protocol logic; Does not generate llm.json]
#AI side_effects: [spawns long-running child process on stdio]
#AI flow: handle() -> validate llm.json -> validate skim-mcp binary -> passthru(skim-mcp --project-dir=BASE_PATH)
#AI lifecycle_steps: [handle(); -> check llm.json exists; -> check skim-mcp binary exists; -> passthru child process; -> forward exit code]
#AI section_order: [Pipeline; Architecture]
#AI architectural_notes: Transport-only launcher; all MCP protocol logic lives in the skim-mcp Node binary. The PHP command only validates prerequisites and spawns the binary.

#AI:handle
#AI group: Pipeline
#AI frequency: medium
#AI signature: public function handle(): int
#AI contract: Validates prerequisites and spawns the MCP server as a child process over stdio. Returns 1 when llm.json or the server script is missing.
#AI return_detail: {type: int | desc: Exit code from the child MCP server process, or 1 on pre-flight failure.}
#AI side_effects: [spawns long-running child process]
