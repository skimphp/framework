<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\command;

// Starts PHP built-in dev server + Vite in parallel via proc_open.
// Why proc_open over shell_exec: non-blocking, both processes controlled,
// Ctrl+C kills both cleanly via the signal handler.
//
// Usage:
//   php skim serve                 → PHP 127.0.0.1:8000 + Vite default port
//   php skim serve --host=0.0.0.0 --port=8080
class serve_command extends command {
    public function handle(): int {
        $host = (string) $this->flag('host', '127.0.0.1');
        $port = (string) $this->flag('port', '8000');
        $root = base_path('public');

        $this->success("SKIM dev server: http://{$host}:{$port}");
        $this->muted('Press Ctrl+C to stop.');
        $this->line();

        $php_cmd  = "php -S {$host}:{$port} -t {$root}";
        $vite_cmd = file_exists(base_path('package.json')) ? 'npm run dev' : null;

        $procs = [];
        $procs[] = proc_open($php_cmd, [STDIN, STDOUT, STDERR], $pipes, base_path());

        if ($vite_cmd !== null) {
            $procs[] = proc_open($vite_cmd, [STDIN, STDOUT, STDERR], $pipes2, base_path());
        }

        // Wait for both processes
        foreach ($procs as $proc) {
            if (is_resource($proc)) {
                proc_close($proc);
            }
        }

        return 0;
    }
}
