<?php declare(strict_types=1);

namespace skim\cli;

/**
 * CLI kernel — central dispatcher that owns command registry, help rendering, and dispatch.
 *
 * Use as the entry point from bin/skim — creates the kernel, parses argv,
 * resolves commands, and dispatches them with header output and error handling.
 * Merges built-in commands with user-defined ones from config/app.php.
 *
 * Example:
 *   $kernel = new kernel();
 *   $exit_code = $kernel->run(argv_parser::parse($argv));
 *   exit($exit_code);
 *
 * Testing: Instantiate directly with known argv_parser input; commands are resolved from built-in + config.
 *
 * #AI:class
 */
final class kernel {

    public const VERSION = '1.0.0';

    private const COMMANDS = [
        'migrate'        => \skim\cli\commands\migrate_command::class,
        'migrate:down'   => \skim\cli\commands\migrate_command::class,
        'migrate:fresh'  => \skim\cli\commands\migrate_command::class,
        'migrate:status' => \skim\cli\commands\migrate_command::class,
        'queue:work'     => \skim\cli\commands\queue_command::class,
        'queue:status'   => \skim\cli\commands\queue_command::class,
        'queue:flush'    => \skim\cli\commands\queue_command::class,
        'queue:restart'  => \skim\cli\commands\queue_command::class,
        'cache:clear'    => \skim\cli\commands\cache_command::class,
        'cache:flush'    => \skim\cli\commands\cache_command::class,
        'cache:build'    => \skim\cli\commands\cache_build_command::class,
        'ext:install'    => \skim\cli\commands\ext_install_command::class,
        'ext:list'       => \skim\cli\commands\ext_list_command::class,
        'serve'          => \skim\cli\commands\serve_command::class,
        'ide:generate'   => \skim\cli\commands\ide_command::class,
        'install'        => \skim\cli\commands\install_command::class,
        'worker:install'   => \skim\cli\commands\worker_install_command::class,
        'worker:uninstall' => \skim\cli\commands\worker_uninstall_command::class,
    ];

    private const DEV_COMMANDS = [
        'docs'           => \skim\dev\docs\commands\docs_command::class,
        'docs:extract'   => \skim\dev\docs\commands\docs_extract_command::class,
        'docs:llm'       => \skim\dev\docs\commands\docs_llm_command::class,
        'docs:site'      => \skim\dev\docs\commands\docs_site_command::class,
        'docs:validate'  => \skim\dev\docs\commands\docs_validate_command::class,
        'mcp:serve'      => \skim\dev\docs\commands\mcp_serve_command::class,
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
    private bool $no_ansi;
    private bool $agent;

    /**
     * Runs the CLI: resolves command, prints header, dispatches handle(). #AI:run
     *
     * Handles --version, --quiet, --no-ansi, --agent flags. For unknown commands, shows
     * error box with Levenshtein "did you mean" suggestion. For help/list,
     * shows interactive TUI (TTY) or static listing (piped).
     *
     * @param argv_parser $input Parsed CLI input from argv_parser::parse().
     * @return int POSIX exit code.
     */
    public function run(argv_parser $input): int {
        $this->agent   = $input->has_flag('agent');
        $this->quiet   = $this->agent || $input->has_flag('quiet', 'q');
        $this->no_ansi = $this->agent || $input->has_flag('no-ansi');

        if ($input->has_flag('version', 'V')) {
            echo "SKIM Framework CLI v" . self::VERSION . "\n";
            return 0;
        }

        if ($this->no_ansi) {
            cli::force_plain(true);
        }

        $command_name = $input->command;

        while (true) {
            $class = $this->resolve($command_name);

            if ($class !== null) {
                return $this->dispatch($class, $command_name, $input);
            }

            if ($command_name === 'help' || $command_name === 'list') {
                $selected = $this->show_help();
                if ($selected === null) {
                    return 0;
                }
                $command_name = $selected;
                continue;
            }

            if ($this->agent) {
                fwrite(STDERR, "error\tunknown_command\t{$command_name}\n");
                $suggestion = $this->closest_command($command_name, array_keys($this->all_commands()));
                if ($suggestion !== null) {
                    fwrite(STDERR, "suggestion\t{$suggestion}\n");
                }
                return 1;
            }

            cli::error_box('Error', "Unknown command: {$command_name}");
            cli::did_you_mean($command_name, array_keys($this->all_commands()));
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
        $all = $this->all_commands();

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
     * Merges built-in commands with user-defined ones from config/app.php. #AI:all_commands
     *
     * Includes dev-only commands (docs, mcp:serve) when SKIM_DEV is true.
     */
    private function all_commands(): array {
        $commands = self::COMMANDS;
        if (defined('SKIM_DEV') && SKIM_DEV) {
            $commands = array_merge($commands, self::DEV_COMMANDS);
        }
        $user_commands = \skim\core\config::get('app.commands', []);
        return array_merge($commands, $user_commands);
    }

    /**
     * Instantiates, configures, and dispatches a resolved command class. #AI:dispatch
     *
     * Prints header (unless --quiet), records timing, catches exceptions and
     * renders error boxes. In debug mode, includes stack trace.
     *
     * @param string      $class        FQCN of the command class.
     * @param string      $command_name Registered command name.
     * @param argv_parser $input        Parsed CLI input.
     */
    private function dispatch(string $class, string $command_name, argv_parser $input): int {
        /** @var command $cmd */
        $cmd = new $class();
        $cmd->set_input($input->args, $input->flags);

        if (!$this->quiet) {
            $this->print_header();
        }

        $start_time = microtime(true);

        try {
            $code = $cmd->handle();
            if (cli::is_tty() && !$this->quiet) {
                cli::newline();
                cli::duration($start_time);
            }
            return (int) $code;
        } catch (\Throwable $e) {
            if ($this->agent) {
                $message = str_replace(["\t", "\r", "\n"], ' ', $e->getMessage());
                fwrite(STDERR, "error\tcommand_failed\t{$message}\n");
                return 1;
            }

            cli::error_box('Error', $e->getMessage());
            if (\skim\core\config::get('app.debug')) {
                cli::muted($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Shows help: interactive TUI on TTY, static listing otherwise. #AI:show_help
     *
     * Returns a selected command name for re-dispatch, or null to exit.
     */
    private function show_help(): ?string {
        $groups = $this->build_groups();

        if ($this->agent) {
            $this->print_agent_help($groups);
            return null;
        }

        $is_interactive = cli::is_tty() && !$this->no_ansi && !$this->quiet;

        if ($is_interactive) {
            $this->print_header();
            $menu = new interactive_menu($groups);
            return $menu->run();
        }

        if (!$this->quiet) {
            $this->print_header();
        }

        cli::section('Available commands:');
        foreach ($groups as $g) {
            cli::line('  ' . strtoupper($g['label']));
            foreach ($g['commands'] as $c) {
                $usage = $c['usage'] !== '' ? ' ' . $c['usage'] : '';
                cli::line('    ' . str_pad($c['name'] . $usage, 30) . $c['description']);
            }
            cli::line();
        }
        return null;
    }

    /**
     * Prints compact tab-separated help for automation and LLM agents.
     */
    private function print_agent_help(array $groups): void {
        cli::line("command\tusage\tdescription");
        foreach ($groups as $group) {
            foreach ($group['commands'] as $command) {
                cli::line($command['name'] . "\t" . $command['usage'] . "\t" . $command['description']);
            }
        }
    }

    /**
     * Returns the closest command suggestion, or null when no close match exists.
     */
    private function closest_command(string $input, array $candidates): ?string {
        $best = null;
        $best_dist = 4;
        foreach ($candidates as $candidate) {
            $dist = levenshtein($input, $candidate);
            if ($dist < $best_dist) {
                $best_dist = $dist;
                $best = $candidate;
            }
        }
        return $best;
    }

    /**
     * Builds ordered command groups for display from all registered commands. #AI:build_groups
     */
    private function build_groups(): array {
        $all = $this->all_commands();
        $grouped = [];

        foreach ($all as $name => $class) {
            if (!class_exists($class)) {
                continue;
            }
            /** @var command $cmd */
            $cmd = new $class();
            $cmd->configure_for_name($name);

            $group_key   = $cmd->get_group();
            $group_label = self::GROUP_ORDER[$group_key] ?? ucfirst($group_key);

            $grouped[$group_label][] = [
                'name'        => $name,
                'usage'       => $cmd->get_usage(),
                'description' => $cmd->get_description(),
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
     * Prints the SKIM header banner with logo and environment metadata. #AI:print_header
     */
    private function print_header(): void {
        cli::header(
            self::VERSION,
            PHP_VERSION,
            \skim\core\env::get('APP_ENV', 'development'),
            PHP_OS . ' ' . php_uname('m'),
        );
    }
}

#AI:class
#AI symbol: skim\cli\kernel
#AI source_path: src/cli/kernel.php
#AI title: kernel
#AI description: CLI kernel — central dispatcher owning command registry, help rendering, dispatch, timing, and error output.
#AI role: CLI central dispatcher
#AI layer: cli
#AI badges: [cli; kernel; dispatcher; command-registry]
#AI intro: `kernel` is the central dispatcher for the SKIM CLI. It owns the built-in command registry, merges user-defined commands from config, resolves command names, prints the header banner, dispatches commands with timing and error handling, and provides interactive or static help listings.
#AI lifecycle: instantiated once per CLI invocation in bin/skim, run() called with parsed argv
#AI fallback: unknown commands show error box with Levenshtein suggestion; help/list shows command listing
#AI test_seam: instantiate directly, pass argv_parser with known input; user commands come from config
#AI invariants: [built-in COMMANDS map is immutable; user commands from config/app.php are merged at runtime; --quiet suppresses header and duration; --no-ansi forces plain output; --agent implies --quiet and --no-ansi]
#AI core_behaviors: [Resolves command names with colon-prefix fallback; Merges built-in and user commands; Prints header banner unless --quiet; Catches exceptions and renders error boxes; Shows duration on TTY; Interactive TUI help on TTY, static listing otherwise; Agent mode prints compact tab-separated help and errors]
#AI owns: command registry, group ordering, quiet/no_ansi/agent flags
#AI entry_points: [run]
#AI config_reads: [app.commands; app.debug; APP_ENV]
#AI non_goals: [Does not parse argv (see argv_parser); Does not implement command logic (see command subclasses); Does not manage process signals]
#AI side_effects: [prints to stdout/stderr; reads config for user commands and debug mode]
#AI flow: kernel::run(input) -> check flags -> resolve command -> dispatch or show_help -> return exit code
#AI lifecycle_steps: [run(argv_parser); -> check --version/--quiet/--no-ansi; -> resolve(command_name); -> if found: dispatch(); -> if help/list: show_help(); -> if unknown: error_box + did_you_mean; -> return exit code]
#AI section_order: [Dispatch; Command Resolution; Help Display; Architecture]
#AI architectural_notes: The kernel is the single entry point for all CLI operations. It keeps the command registry as a private constant and merges user commands from config at runtime.

#AI:run
#AI group: Dispatch
#AI frequency: high
#AI signature: public function run(argv_parser $input): int
#AI contract: Runs the CLI. Handles --version/--quiet/--no-ansi/--agent flags, resolves the command, dispatches it, or shows help for unknown/help/list commands.
#AI param_details: [{name: $input | type: argv_parser | required: true | desc: Parsed CLI input from argv_parser::parse().}]
#AI return_detail: {type: int | desc: POSIX exit code from the dispatched command.}

#AI:resolve
#AI group: Command Resolution
#AI frequency: internal
#AI signature: private function resolve(string $name): ?string
#AI contract: Resolves a command name to its FQCN. Supports direct match and colon-prefix fallback.
#AI param_details: [{name: $name | type: string | required: true | desc: Command name from argv.}]
#AI return_detail: {type: ?string | desc: FQCN of the command class, or null if not registered.}

#AI:all_commands
#AI group: Command Resolution
#AI frequency: internal
#AI signature: private function all_commands(): array
#AI contract: Merges built-in COMMANDS with user-defined commands from config/app.php.
#AI return_detail: {type: array | desc: Merged command registry (name => FQCN).}

#AI:dispatch
#AI group: Dispatch
#AI frequency: internal
#AI signature: private function dispatch(string $class, string $command_name, argv_parser $input): int
#AI contract: Instantiates, configures, and dispatches a resolved command class. Prints header, records timing, catches exceptions.
#AI param_details: [{name: $class | type: string | required: true | desc: FQCN of the command class.}; {name: $command_name | type: string | required: true | desc: Registered command name.}; {name: $input | type: argv_parser | required: true | desc: Parsed CLI input.}]
#AI return_detail: {type: int | desc: Exit code from command handle(), or 1 on exception.}

#AI:show_help
#AI group: Help Display
#AI frequency: internal
#AI signature: private function show_help(): ?string
#AI contract: Shows interactive TUI help on TTY or static command listing otherwise. Returns selected command name for re-dispatch or null to exit.
#AI return_detail: {type: ?string | desc: Selected command name for re-dispatch, or null to exit.}

#AI:build_groups
#AI group: Help Display
#AI frequency: internal
#AI signature: private function build_groups(): array
#AI contract: Builds ordered command groups from all registered commands, sorted by GROUP_ORDER.
#AI return_detail: {type: array | desc: Array of group records with label and commands keys.}

#AI:print_header
#AI group: Architecture
#AI frequency: internal
#AI signature: private function print_header(): void
#AI contract: Prints the SKIM header banner with logo and environment metadata via cli::header().
