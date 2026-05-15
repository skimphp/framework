<?php declare(strict_types=1);

namespace skim\cli;

// Base class for all CLI commands.
// Commands are registered in config/app.php under 'commands' key
// or via $app->command('name', handler) in routes.php.
//
// Exit codes follow POSIX convention: 0 = success, 1+ = error.
abstract class command {
    protected array $args  = [];   // positional arguments after command name
    protected array $flags = [];   // --flag=value or --flag (bool true)

    /**
     * @ai-contract implement the command logic here — return exit code (0 = success)
     */
    abstract public function handle(): int;

    /**
     * @ai-contract called by bin/skim to inject parsed argv before handle()
     */
    public function set_input(array $args, array $flags): void {
        $this->args  = $args;
        $this->flags = $flags;
    }

    /**
     * @ai-contract returns positional argument by index, or $default if not set
     */
    protected function arg(int $index, mixed $default = null): mixed {
        return $this->args[$index] ?? $default;
    }

    /**
     * @ai-contract returns flag value: --flag=val → 'val', --flag → true, absent → $default
     */
    protected function flag(string $name, mixed $default = null): mixed {
        return $this->flags[$name] ?? $default;
    }

    protected function info(string $msg): void    { cli::info($msg); }
    protected function success(string $msg): void { cli::success($msg); }
    protected function warn(string $msg): void    { cli::warn($msg); }
    protected function error(string $msg): void   { cli::error($msg); }
    protected function line(string $msg = ''): void { cli::line($msg); }
    protected function muted(string $msg): void   { cli::muted($msg); }
}
