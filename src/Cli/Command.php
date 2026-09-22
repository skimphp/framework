<?php declare(strict_types=1);

namespace Skim\Cli;

/**
 * Abstract base class for all CLI commands with arg/flag access and output helpers.
 *
 * Use when creating new CLI commands — extend this class and implement handle().
 * Commands are registered in config/app.php under the 'commands' key or via
 * the kernel's built-in COMMANDS map. Exit codes follow POSIX: 0 = success, 1+ = error.
 *
 * Example:
 *   class GreetCommand extends Command {
 *       public function handle(): int {
 *           $name = $this->arg(0, 'World');
 *           $this->info("Hello {$name}!");
 *           return 0;
 *       }
 *   }
 *
 * Testing: Instantiate the command, call setInput() with test args/flags, then handle().
 *
 * #AI:class
 */
abstract class Command {
    protected array $args  = [];
    protected array $flags = [];

    protected string $name        = '';
    protected string $description = '';
    protected string $group       = 'general';
    protected string $usage       = '';

    /**
     * Returns the registered command name. #AI:getName
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Returns the command description for help display. #AI:getDescription
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns the command group for categorized help listing. #AI:getGroup
     */
    public function getGroup(): string {
        return $this->group;
    }

    /**
     * Returns the usage string for help display. #AI:getUsage
     */
    public function getUsage(): string {
        return $this->usage;
    }

    /**
     * Auto-fills name, group, description, and usage from the registered command name. #AI:configureForName
     *
     * Called by the kernel before dispatch. Only sets values that are still at
     * their defaults — subclass overrides are preserved.
     *
     * @param string $name The registered command name (e.g. 'migrate:down').
     */
    public function configureForName(string $name): void {
        if ($this->name === '') {
            $this->name = $name;
        }

        if ($this->group === 'general') {
            $prefix = str_contains($name, ':') ? explode(':', $name)[0] : $name;
            $this->group = match ($prefix) {
                'migrate' => 'database',
                'queue'   => 'queue',
                'cache'   => 'cache',
                'ext'     => 'extension',
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
                'cache:build'    => 'compile env and config to PHP array cache',
                'ext:install'    => 'install a SKIM extension',
                'ext:list'       => 'list installed SKIM extensions',
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
                'ext:install'  => '<name> [--prefix=skim_]',
                default        => '',
            };
        }
    }

    /**
     * Prints usage and description to the terminal. #AI:help
     */
    public function help(): void {
        \Skim\Cli\Cli::bold("Usage:");
        $usageStr = $this->name;
        if ($this->usage !== '') {
            $usageStr .= ' ' . $this->usage;
        }
        \Skim\Cli\Cli::line("  php skim " . $usageStr);
        if ($this->description !== '') {
            \Skim\Cli\Cli::line();
            \Skim\Cli\Cli::bold("Description:");
            \Skim\Cli\Cli::line("  " . $this->description);
        }
    }

    /**
     * Implements the command logic — return POSIX exit code (0 = success). #AI:handle
     */
    abstract public function handle(): int;

    /**
     * Injects parsed argv args and flags before handle() is called. #AI:setInput
     *
     * Called by the kernel after resolving the command class.
     *
     * @param array $args  Positional arguments after the command name.
     * @param array $flags Parsed flags (--flag=value or --flag as true).
     */
    public function setInput(array $args, array $flags): void {
        $this->args  = $args;
        $this->flags = $flags;
    }

    /**
     * Returns a positional argument by index, or $default if not set. #AI:arg
     *
     * @param int   $index   Zero-based argument position.
     * @param mixed $default Returned when the index does not exist.
     */
    protected function arg(int $index, mixed $default = null): mixed {
        return $this->args[$index] ?? $default;
    }

    /**
     * Returns a flag value: --flag=val returns 'val', --flag returns true. #AI:flag
     *
     * @param string $name    Flag name without leading dashes.
     * @param mixed  $default Returned when the flag is absent.
     */
    protected function flag(string $name, mixed $default = null): mixed {
        return $this->flags[$name] ?? $default;
    }

    /** @see cli::info */
    protected function info(string $msg): void    { \Skim\Cli\Cli::info($msg); }
    /** @see cli::success */
    protected function success(string $msg): void { \Skim\Cli\Cli::success($msg); }
    /** @see cli::warn */
    protected function warn(string $msg): void    { \Skim\Cli\Cli::warn($msg); }
    /** @see cli::error */
    protected function error(string $msg): void   { \Skim\Cli\Cli::error($msg); }
    /** @see cli::line */
    protected function line(string $msg = ''): void { \Skim\Cli\Cli::line($msg); }
    /** @see cli::muted */
    protected function muted(string $msg): void   { \Skim\Cli\Cli::muted($msg); }
}

#AI:class
#AI symbol: Skim\Cli\Command
#AI source_path: src/Cli/Command.php
#AI title: command
#AI description: Abstract base class for CLI commands with arg/flag access, output helpers, and auto-configured metadata.
#AI role: CLI command base class
#AI layer: cli
#AI badges: [cli; command; abstract; base-class]
#AI intro: `command` is the abstract base class that all SKIM CLI commands extend. It provides positional arg access, flag access, output helper proxies (info, success, warn, error), and auto-configuration of name/group/description/usage from the registered command name.
#AI lifecycle: instantiated by kernel, setInput() called with parsed argv, then handle() invoked
#AI fallback: configureForName() provides default descriptions and usage strings for built-in commands
#AI test_seam: instantiate subclass, call setInput() with test data, then handle()
#AI invariants: [handle() must return POSIX exit code; args and flags are set by kernel before handle(); configureForName() preserves subclass overrides]
#AI core_behaviors: [arg() and flag() provide safe access with defaults; configureForName() auto-fills metadata from command name; output helpers delegate to Cli:: static methods]
#AI owns: args, flags, name, description, group, usage
#AI entry_points: [handle; setInput; arg; flag; help]
#AI config_reads: []
#AI non_goals: [Does not parse argv (see ArgvParser); Does not register commands (see kernel); Does not handle process signals]
#AI side_effects: [Output helpers write to STDOUT/STDERR via Cli::]
#AI flow: kernel -> new Command() -> configureForName() -> setInput(args, flags) -> handle() -> exit code
#AI lifecycle_steps: [kernel resolves command class; -> new $class(); -> configureForName($name); -> setInput($args, $flags); -> handle(); -> return exit code]
#AI section_order: [Metadata Access; Configuration; Command Execution; Input Access; Output Helpers]
#AI architectural_notes: Abstract base class — never instantiated directly. Subclasses implement handle() and optionally override $name, $description, $group, $usage properties.

#AI:getName
#AI group: Metadata Access
#AI frequency: low
#AI signature: public function getName(): string
#AI contract: Returns the registered command name as set by configureForName().
#AI return_detail: {type: string | desc: Command name (e.g. 'migrate:down').}

#AI:getDescription
#AI group: Metadata Access
#AI frequency: low
#AI signature: public function getDescription(): string
#AI contract: Returns the command description for help display.
#AI return_detail: {type: string | desc: Human-readable description.}

#AI:getGroup
#AI group: Metadata Access
#AI frequency: low
#AI signature: public function getGroup(): string
#AI contract: Returns the command group for categorized help listing.
#AI return_detail: {type: string | desc: Group key (e.g. 'database', 'queue', 'general').}

#AI:getUsage
#AI group: Metadata Access
#AI frequency: low
#AI signature: public function getUsage(): string
#AI contract: Returns the usage string for help display.
#AI return_detail: {type: string | desc: Usage string (e.g. '[--steps=N]').}

#AI:configureForName
#AI group: Configuration
#AI frequency: internal
#AI signature: public function configureForName(string $name): void
#AI contract: Auto-fills name, group, description, and usage from the registered command name. Only sets values still at defaults — subclass property overrides are preserved.
#AI param_details: [{name: $name | type: string | required: true | desc: Registered command name (e.g. 'migrate:down').}]

#AI:help
#AI group: Configuration
#AI frequency: low
#AI signature: public function help(): void
#AI contract: Prints usage and description to the terminal via Cli:: output helpers.

#AI:handle
#AI group: Command Execution
#AI frequency: high
#AI signature: abstract public function handle(): int
#AI contract: Implements the command logic. Must return a POSIX exit code (0 = success, 1+ = error).
#AI return_detail: {type: int | desc: POSIX exit code. 0 for success, 1+ for error.}

#AI:setInput
#AI group: Input Access
#AI frequency: internal
#AI signature: public function setInput(array $args, array $flags): void
#AI contract: Injects parsed argv args and flags. Called by the kernel before handle().
#AI param_details: [{name: $args | type: array | required: true | desc: Positional arguments after the command name.}; {name: $flags | type: array | required: true | desc: Parsed flags (--flag=value or --flag as true).}]

#AI:arg
#AI group: Input Access
#AI frequency: high
#AI signature: protected function arg(int $index, mixed $default = null): mixed
#AI contract: Returns a positional argument by zero-based index, or $default if the index does not exist.
#AI param_details: [{name: $index | type: int | required: true | desc: Zero-based argument position.}; {name: $default | type: mixed | required: false | desc: Returned when the index does not exist.}]
#AI return_detail: {type: mixed | desc: The argument value at $index, or $default.}

#AI:flag
#AI group: Input Access
#AI frequency: high
#AI signature: protected function flag(string $name, mixed $default = null): mixed
#AI contract: Returns a flag value. --flag=val returns 'val', --flag returns true, absent returns $default.
#AI param_details: [{name: $name | type: string | required: true | desc: Flag name without leading dashes.}; {name: $default | type: mixed | required: false | desc: Returned when the flag is absent.}]
#AI return_detail: {type: mixed | desc: Flag value, true for boolean flags, or $default.}
