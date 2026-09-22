<?php declare(strict_types=1);

namespace Skim\Cli;

/**
 * CLI kernel — central dispatcher that owns command registry, help rendering, and dispatch.
 *
 * Use as the entry point from bin/skim — creates the kernel, parses argv,
 * resolves commands, and dispatches them with header output and error handling.
 * Merges built-in commands with user-defined ones from config/app.php.
 *
 * Example:
 *   $kernel = new Kernel();
 *   $exit_code = $kernel->run(ArgvParser::parse($argv));
 *   exit($exit_code);
 *
 * Testing: Instantiate directly with known argv_parser input; commands are resolved from built-in + config.
 *
 * #AI:class
 */
final class Kernel {

    public const VERSION = '1.0.0';

    private const COMMANDS = [
        'migrate'        => \Skim\Cli\Commands\MigrateCommand::class,
        'migrate:down'   => \Skim\Cli\Commands\MigrateCommand::class,
        'migrate:fresh'  => \Skim\Cli\Commands\MigrateCommand::class,
        'migrate:status' => \Skim\Cli\Commands\MigrateCommand::class,
        'queue:work'     => \Skim\Cli\Commands\QueueCommand::class,
        'queue:status'   => \Skim\Cli\Commands\QueueCommand::class,
        'queue:flush'    => \Skim\Cli\Commands\QueueCommand::class,
        'queue:restart'  => \Skim\Cli\Commands\QueueCommand::class,
        'cache:clear'    => \Skim\Cli\Commands\CacheCommand::class,
        'cache:flush'    => \Skim\Cli\Commands\CacheCommand::class,
        'cache:build'    => \Skim\Cli\Commands\CacheBuildCommand::class,
        'ext:install'    => \Skim\Cli\Commands\ExtInstallCommand::class,
        'ext:list'       => \Skim\Cli\Commands\ExtListCommand::class,
        'serve'          => \Skim\Cli\Commands\ServeCommand::class,
        'ide:generate'   => \Skim\Cli\Commands\IdeCommand::class,
        'install'        => \Skim\Cli\Commands\InstallCommand::class,
        'worker:install'   => \Skim\Cli\Commands\WorkerInstallCommand::class,
        'worker:uninstall' => \Skim\Cli\Commands\WorkerUninstallCommand::class,
    ];

    private const DEV_COMMANDS = [
        'docs'           => \Skim\Dev\Docs\Commands\DocsCommand::class,
        'docs:extract'   => \Skim\Dev\Docs\Commands\DocsExtractCommand::class,
        'docs:llm'       => \Skim\Dev\Docs\Commands\DocsLlmCommand::class,
        'docs:site'      => \Skim\Dev\Docs\Commands\DocsSiteCommand::class,
        'docs:validate'  => \Skim\Dev\Docs\Commands\DocsValidateCommand::class,
        'mcp:serve'      => \Skim\Dev\Docs\Commands\McpServeCommand::class,
        'mcp:install'    => \Skim\Dev\Docs\Commands\McpInstallCommand::class,
    ];

    private const GROUP_ORDER = [
        'database'    => 'Database',
        'queue'       => 'Queue',
        'cache'       => 'Cache',
        'extension'   => 'Extensions',
        'server'      => 'Server',
        'development' => 'Development',
        'general'     => 'General',
    ];

    private bool $quiet;
    private bool $noAnsi;
    private bool $agent;

    /**
     * Runs the CLI: resolves command, prints header, dispatches handle(). #AI:run
     *
     * Handles --version, --quiet, --no-ansi, --agent flags. For unknown commands, shows
     * error box with Levenshtein "did you mean" suggestion. For help/list,
     * shows interactive TUI (TTY) or static listing (piped).
     *
     * @param \Skim\Cli\ArgvParser $input Parsed CLI input from ArgvParser::parse().
     * @return int POSIX exit code.
     */
    public function run(\Skim\Cli\ArgvParser $input): int {
        $this->agent   = $input->hasFlag('agent');
        $this->quiet   = $this->agent || $input->hasFlag('quiet', 'q');
        $this->noAnsi = $this->agent || $input->hasFlag('no-ansi');

        if ($input->hasFlag('version', 'V')) {
            echo "SKIM Framework CLI v" . self::VERSION . "\n";
            return 0;
        }

        if ($this->noAnsi) {
            \Skim\Cli\Cli::forcePlain(true);
        }

        $commandName = $input->command;

        while (true) {
            $class = $this->resolve($commandName);

            if ($class !== null) {
                return $this->dispatch($class, $commandName, $input);
            }

            if ($commandName === 'help' || $commandName === 'list') {
                $selected = $this->showHelp();
                if ($selected === null) {
                    return 0;
                }
                $commandName = $selected;
                continue;
            }

            if ($this->agent) {
                fwrite(STDERR, "error\tunknown_command\t{$commandName}\n");
                $suggestion = $this->closestCommand($commandName, array_keys($this->allCommands()));
                if ($suggestion !== null) {
                    fwrite(STDERR, "suggestion\t{$suggestion}\n");
                }
                return 1;
            }

            \Skim\Cli\Cli::errorBox('Error', "Unknown command: {$commandName}");
            \Skim\Cli\Cli::didYouMean($commandName, array_keys($this->allCommands()));
            return 1;
        }
    }

    /**
     * Resolves a command name to its FQCN, or null if not registered. #AI:resolve
     *
     * Supports direct match and colon-prefix fallback (e.g. migrate:down → migrate).
     *
     * @param string $name Command name from argv.
     */
    private function resolve(string $name): ?string {
        $all = $this->allCommands();

        if (isset($all[$name])) {
            return $all[$name];
        }

        if (str_contains($name, ':')) {
            $base = substr($name, 0, strpos($name, ':'));
            return $all[$name] ?? ($all[$base] ?? null);
        }

        return null;
    }

    /**
     * Merges built-in commands with user-defined ones from config/app.php. #AI:allCommands
     *
     * Includes dev-only commands (docs, mcp:serve) when SKIM_DEV is true.
     */
    private function allCommands(): array {
        $commands = self::COMMANDS;
        if (defined('SKIM_DEV') && SKIM_DEV) {
            $commands = array_merge($commands, self::DEV_COMMANDS);
        }
        $userCommands = \Skim\Core\Config::get('app.commands', []);
        return array_merge($commands, $userCommands);
    }

    /**
     * Instantiates, configures, and dispatches a resolved command class. #AI:dispatch
     *
     * Prints header (unless --quiet), records timing, catches exceptions and
     * renders error boxes. In debug mode, includes stack trace.
     *
     * @param string      $class        FQCN of the command class.
     * @param string      $commandName Registered command name.
     * @param \Skim\Cli\ArgvParser $input Parsed CLI input.
     */
    private function dispatch(string $class, string $commandName, \Skim\Cli\ArgvParser $input): int {
        /** @var \Skim\Cli\Command $cmd */
        $cmd = new $class();
        $cmd->setInput($input->args, $input->flags);

        if (!$this->quiet) {
            $this->printHeader();
        }

        $startTime = microtime(true);

        try {
            $code = $cmd->handle();
            if (\Skim\Cli\Cli::isTty() && !$this->quiet) {
                \Skim\Cli\Cli::newline();
                \Skim\Cli\Cli::duration($startTime);
            }
            return (int) $code;
        } catch (\Throwable $e) {
            if ($this->agent) {
                $message = str_replace(["\t", "\r", "\n"], ' ', $e->getMessage());
                fwrite(STDERR, "error\tcommand_failed\t{$message}\n");
                return 1;
            }

            \Skim\Cli\Cli::errorBox('Error', $e->getMessage());
            if (\Skim\Core\Config::get('app.debug')) {
                \Skim\Cli\Cli::muted($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Shows help: interactive TUI on TTY, static listing otherwise. #AI:showHelp
     *
     * Returns a selected command name for re-dispatch, or null to exit.
     */
    private function showHelp(): ?string {
        $groups = $this->buildGroups();

        if ($this->agent) {
            $this->printAgentHelp($groups);
            return null;
        }

        $isInteractive = \Skim\Cli\Cli::isTty() && !$this->noAnsi && !$this->quiet;

        if ($isInteractive) {
            $this->printHeader();
            $menu = new \Skim\Cli\InteractiveMenu($groups);
            return $menu->run();
        }

        if (!$this->quiet) {
            $this->printHeader();
        }

        \Skim\Cli\Cli::section('Available commands:');
        foreach ($groups as $g) {
            \Skim\Cli\Cli::line('  ' . strtoupper($g['label']));
            foreach ($g['commands'] as $c) {
                $usage = $c['usage'] !== '' ? ' ' . $c['usage'] : '';
                \Skim\Cli\Cli::line('    ' . str_pad($c['name'] . $usage, 30) . $c['description']);
            }
            \Skim\Cli\Cli::line();
        }
        return null;
    }

    /**
     * Prints compact tab-separated help for automation and LLM agents.
     */
    private function printAgentHelp(array $groups): void {
        \Skim\Cli\Cli::line("command\tusage\tdescription");
        foreach ($groups as $group) {
            foreach ($group['commands'] as $command) {
                \Skim\Cli\Cli::line($command['name'] . "\t" . $command['usage'] . "\t" . $command['description']);
            }
        }
    }

    /**
     * Returns the closest command suggestion, or null when no close match exists.
     */
    private function closestCommand(string $input, array $candidates): ?string {
        $best = null;
        $bestDist = 4;
        foreach ($candidates as $candidate) {
            $dist = levenshtein($input, $candidate);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $candidate;
            }
        }
        return $best;
    }

    /**
     * Builds ordered command groups for display from all registered commands. #AI:buildGroups
     */
    private function buildGroups(): array {
        $all = $this->allCommands();
        $grouped = [];

        foreach ($all as $name => $class) {
            if (!class_exists($class)) {
                continue;
            }
            /** @var \Skim\Cli\Command $cmd */
            $cmd = new $class();
            $cmd->configureForName($name);

            $groupKey   = $cmd->getGroup();
            $groupLabel = self::GROUP_ORDER[$groupKey] ?? ucfirst($groupKey);

            $grouped[$groupLabel][] = [
                'name'        => $name,
                'usage'       => $cmd->getUsage(),
                'description' => $cmd->getDescription(),
            ];
        }

        $result = [];
        foreach (self::GROUP_ORDER as $label) {
            if (isset($grouped[$label])) {
                $result[] = [
                    'label'    => $label,
                    'commands' => $grouped[$label],
                ];
                unset($grouped[$label]);
            }
        }
        foreach ($grouped as $label => $commands) {
            $result[] = [
                'label'    => $label,
                'commands' => $commands,
            ];
        }

        return $result;
    }

    /**
     * Prints the SKIM header banner with logo and environment metadata. #AI:printHeader
     */
    private function printHeader(): void {
        \Skim\Cli\Cli::header(
            self::VERSION,
            PHP_VERSION,
            \Skim\Core\Env::get('APP_ENV', 'development'),
            PHP_OS . ' ' . php_uname('m'),
        );
    }
}

#AI:class
#AI symbol: Skim\Cli\Kernel
#AI source_path: src/Cli/Kernel.php
#AI title: kernel
#AI description: CLI kernel — central dispatcher owning command registry, help rendering, dispatch, timing, and error output.
#AI role: CLI central dispatcher
#AI layer: cli
#AI badges: [cli; kernel; dispatcher; command-registry]
#AI intro: `kernel` is the central dispatcher for the SKIM CLI. It owns the built-in command registry, merges user-defined commands from config, resolves command names, prints the header banner, dispatches commands with timing and error handling, and provides interactive or static help listings.
#AI lifecycle: instantiated once per CLI invocation in bin/skim, run() called with parsed argv
#AI fallback: unknown commands show error box with Levenshtein suggestion; help/list shows command listing
#AI test_seam: instantiate directly, pass ArgvParser with known input; user commands come from config
#AI invariants: [built-in COMMANDS map is immutable; user commands from config/app.php are merged at runtime; --quiet suppresses header and duration; --no-ansi forces plain output; --agent implies --quiet and --no-ansi]
#AI core_behaviors: [Resolves command names with colon-prefix fallback; Merges built-in and user commands; Prints header banner unless --quiet; Catches exceptions and renders error boxes; Shows duration on TTY; Interactive TUI help on TTY, static listing otherwise; Agent mode prints compact tab-separated help and errors]
#AI owns: command registry, group ordering, quiet/noAnsi/agent flags
#AI entry_points: [run]
#AI config_reads: [app.commands; app.debug; APP_ENV]
#AI non_goals: [Does not parse argv (see ArgvParser); Does not implement command logic (see command subclasses); Does not manage process signals]
#AI side_effects: [prints to stdout/stderr; reads config for user commands and debug mode]
#AI flow: Kernel::run(input) -> check flags -> resolve command -> dispatch or showHelp -> return exit code
#AI lifecycle_steps: [run(ArgvParser); -> check --version/--quiet/--no-ansi; -> resolve(commandName); -> if found: dispatch(); -> if help/list: showHelp(); -> if unknown: errorBox + didYouMean; -> return exit code]
#AI section_order: [Dispatch; Command Resolution; Help Display; Architecture]
#AI architectural_notes: The kernel is the single entry point for all CLI operations. It keeps the command registry as a private constant and merges user commands from config at runtime.

#AI:run
#AI group: Dispatch
#AI frequency: high
#AI signature: public function run(ArgvParser $input): int
#AI contract: Runs the CLI. Handles --version/--quiet/--no-ansi/--agent flags, resolves the command, dispatches it, or shows help for unknown/help/list commands.
#AI param_details: [{name: $input | type: ArgvParser | required: true | desc: Parsed CLI input from ArgvParser::parse().}]
#AI return_detail: {type: int | desc: POSIX exit code from the dispatched command.}

#AI:resolve
#AI group: Command Resolution
#AI frequency: internal
#AI signature: private function resolve(string $name): ?string
#AI contract: Resolves a command name to its FQCN. Supports direct match and colon-prefix fallback.
#AI param_details: [{name: $name | type: string | required: true | desc: Command name from argv.}]
#AI return_detail: {type: ?string | desc: FQCN of the command class, or null if not registered.}

#AI:allCommands
#AI group: Command Resolution
#AI frequency: internal
#AI signature: private function allCommands(): array
#AI contract: Merges built-in COMMANDS with user-defined commands from config/app.php.
#AI return_detail: {type: array | desc: Merged command registry (name => FQCN).}

#AI:dispatch
#AI group: Dispatch
#AI frequency: internal
#AI signature: private function dispatch(string $class, string $commandName, ArgvParser $input): int
#AI contract: Instantiates, configures, and dispatches a resolved command class. Prints header, records timing, catches exceptions.
#AI param_details: [{name: $class | type: string | required: true | desc: FQCN of the command class.}; {name: $commandName | type: string | required: true | desc: Registered command name.}; {name: $input | type: ArgvParser | required: true | desc: Parsed CLI input.}]
#AI return_detail: {type: int | desc: Exit code from command handle(), or 1 on exception.}

#AI:showHelp
#AI group: Help Display
#AI frequency: internal
#AI signature: private function showHelp(): ?string
#AI contract: Shows interactive TUI help on TTY or static command listing otherwise. Returns selected command name for re-dispatch or null to exit.
#AI return_detail: {type: ?string | desc: Selected command name for re-dispatch, or null to exit.}

#AI:buildGroups
#AI group: Help Display
#AI frequency: internal
#AI signature: private function buildGroups(): array
#AI contract: Builds ordered command groups from all registered commands, sorted by GROUP_ORDER.
#AI return_detail: {type: array | desc: Array of group records with label and commands keys.}

#AI:printHeader
#AI group: Architecture
#AI frequency: internal
#AI signature: private function printHeader(): void
#AI contract: Prints the SKIM header banner with logo and environment metadata via Cli::header().
