<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Command;

/**
 * Starts PHP built-in dev server and optionally Vite in parallel via proc_open.
 *
 * Use during local development to serve the application without a full web server.
 * Launches PHP's built-in server pointed at public/ and, if package.json exists,
 * starts Vite dev server alongside it. Both processes are killed on Ctrl+C.
 *
 * Example:
 *   php skim serve                          # PHP 127.0.0.1:8000 + Vite
 *   php skim serve --host=0.0.0.0 --port=8080
 *
 * Testing: Not designed for automated testing — launches long-lived processes.
 *
 * #AI:class
 */
class ServeCommand extends \Skim\Cli\Command {
    /**
     * Launches PHP dev server and optional Vite in parallel. #AI:handle
     *
     * Uses proc_open for non-blocking process control. Both processes
     * share STDIN/STDOUT/STDERR and are killed together on Ctrl+C.
     */
    public function handle(): int {
        $host = (string) $this->flag('host', '127.0.0.1');
        $port = (string) $this->flag('port', '8000');
        $root = basePath('public');

        $this->success("SKIM dev server: http://{$host}:{$port}");
        $this->muted('Press Ctrl+C to stop.');
        $this->line();

        $iniFlags = trim((string) (getenv('PHP_INI_FLAGS') ?? ''));
        $iniArgs  = $iniFlags === '' ? '' : ' ' . $iniFlags;
        $workers   = (int) (getenv('PHP_CLI_SERVER_WORKERS') ?? 0);
        $preload   = basePath('storage/preload.php');
        $preloadArg = is_file($preload) ? ' -d opcache.preload=' . escapeshellarg($preload) : '';
        $phpCmd   = "php{$iniArgs}{$preloadArg} -S {$host}:{$port} -t {$root}";
        if ($workers > 0) {
            $phpCmd = "PHP_CLI_SERVER_WORKERS={$workers} {$phpCmd}";
        }
        $viteCmd = file_exists(basePath('package.json')) ? 'npm run dev' : null;

        $procs = [];
        $procs[] = proc_open($phpCmd, [STDIN, STDOUT, STDERR], $pipes, basePath());

        if ($viteCmd !== null) {
            $procs[] = proc_open($viteCmd, [STDIN, STDOUT, STDERR], $pipes2, basePath());
        }

        foreach ($procs as $proc) {
            if (is_resource($proc)) {
                proc_close($proc);
            }
        }

        return 0;
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\ServeCommand
#AI source_path: src/Cli/Commands/ServeCommand.php
#AI title: ServeCommand
#AI description: CLI command that starts PHP built-in dev server and optional Vite in parallel.
#AI role: CLI dev server launcher
#AI layer: cli
#AI badges: [cli; command; server; development]
#AI intro: `ServeCommand` implements the `php skim serve` CLI command. It launches PHP's built-in development server pointed at the public/ directory and, if package.json exists, starts a Vite dev server alongside it using proc_open for parallel process control.
#AI lifecycle: instantiated by kernel, handle() blocks until both processes exit
#AI fallback: Vite is skipped when no package.json exists
#AI test_seam: not designed for automated testing — launches long-lived OS processes
#AI invariants: [PHP server always starts; Vite starts only when package.json exists; both processes share STDIN/STDOUT/STDERR]
#AI core_behaviors: [Launches PHP built-in server via proc_open; Conditionally launches Vite; Blocks until processes exit; Ctrl+C kills both via signal propagation]
#AI owns: nothing — delegates to OS process management
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not configure production web servers; Does not manage SSL certificates; Does not proxy requests between PHP and Vite]
#AI side_effects: [launches OS processes; binds to network port]
#AI flow: ServeCommand::handle() -> resolve host/port -> proc_open(php -S) -> optionally proc_open(npm run dev) -> proc_close both
#AI lifecycle_steps: [handle(); -> read --host and --port flags; -> proc_open(php -S host:port -t public/); -> if package.json: proc_open(npm run dev); -> proc_close all processes]
#AI section_order: [Command Execution]
#AI architectural_notes: Uses proc_open over shell_exec for non-blocking, controlled process management. Both processes are killed cleanly on Ctrl+C via signal propagation.

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Launches PHP dev server and optional Vite in parallel. Blocks until both processes exit.
#AI return_detail: {type: int | desc: Always 0.}
