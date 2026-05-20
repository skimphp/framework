<?php declare(strict_types=1);
// Modified: Added command metadata properties, help display, and dynamic metadata resolution.

namespace skim\cli;

// Base class for all CLI commands.
// Commands are registered in config/app.php under 'commands' key
// or via $app->command('name', handler) in routes.php.
//
// Exit codes follow POSIX convention: 0 = success, 1+ = error.
abstract class command {
    protected array $args  = [];   // positional arguments after command name
    protected array $flags = [];   // --flag=value or --flag (bool true)

    protected string $name        = '';
    protected string $description = '';
    protected string $group       = 'general';
    protected string $usage       = '';

    public function get_name(): string {
        return $this->name;
    }

    public function get_description(): string {
        return $this->description;
    }

    public function get_group(): string {
        return $this->group;
    }

    public function get_usage(): string {
        return $this->usage;
    }

    public function configure_for_name(string $name): void {
        if ($this->name === '') {
            $this->name = $name;
        }

        if ($this->group === 'general') {
            $prefix = str_contains($name, ':') ? explode(':', $name)[0] : $name;
            $this->group = match ($prefix) {
                'migrate' => 'database',
                'queue'   => 'queue',
                'cache'   => 'cache',
                'serve'   => 'server',
                'ide', 'install', 'docs', 'mcp' => 'development',
                default   => 'general',
            };
        }

        if ($this->description === '') {
            $this->description = match ($name) {
                'migrate'        => 'run pending migrations',
                'migrate:down'   => 'rollback last batch',
                'migrate:fresh'  => 'drop all tables and re-run',
                'migrate:status' => 'show migration table status',
                'queue:work'     => 'run queue worker',
                'queue:status'   => 'show queue worker status',
                'queue:flush'    => 'flush all queued jobs',
                'queue:restart'  => 'restart all queue workers',
                'cache:clear'    => 'flush the cache',
                'cache:flush'    => 'flush the cache',
                'serve'          => 'start the built-in development server',
                'ide:generate'   => 'generate helper files for IDEs',
                'install'        => 'install framework components',
                'docs'           => 'open documentation',
                'docs:extract'   => 'extract documentation metadata',
                'docs:llm'       => 'generate documentation for LLMs',
                'docs:site'      => 'build documentation site',
                'docs:validate'  => 'validate documentation',
                'mcp:serve'      => 'start MCP documentation server',
                default          => '',
            };
        }

        if ($this->usage === '') {
            $this->usage = match ($name) {
                'migrate:down' => '[--steps=N]',
                'queue:work'   => '[queue] [--sleep=3] [--max-jobs=0]',
                'queue:flush'  => '[queue]',
                'cache:clear'  => '[prefix]',
                default        => '',
            };
        }
    }

    public function help(): void {
        cli::bold("Usage:");
        $usage_str = $this->name;
        if ($this->usage !== '') {
            $usage_str .= ' ' . $this->usage;
        }
        cli::line("  php skim " . $usage_str);
        if ($this->description !== '') {
            cli::line();
            cli::bold("Description:");
            cli::line("  " . $this->description);
        }
    }


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
