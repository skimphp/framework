<?php declare(strict_types=1);

namespace skim\cli;

/**
 * CLI kernel — central dispatcher for the SKIM CLI.
 *
 * Owns: version constant, command registry, group ordering,
 * help/list rendering (TUI + static), command dispatch, timing, error output.
 */
final class kernel {

    public const VERSION = '1.0.0';

    /** Built-in command registry: name → class. */
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
        'ext:install'    => \skim\cli\commands\ext_install_command::class,
        'ext:list'       => \skim\cli\commands\ext_list_command::class,
        'serve'          => \skim\cli\commands\serve_command::class,
        'ide:generate'   => \skim\cli\commands\ide_command::class,
        'install'        => \skim\cli\commands\install_command::class,
        'docs'           => \skim\dev\docs\commands\docs_command::class,
        'docs:extract'   => \skim\dev\docs\commands\docs_extract_command::class,
        'docs:llm'       => \skim\dev\docs\commands\docs_llm_command::class,
        'docs:site'      => \skim\dev\docs\commands\docs_site_command::class,
        'docs:validate'  => \skim\dev\docs\commands\docs_validate_command::class,
        'mcp:serve'      => \skim\dev\docs\commands\mcp_serve_command::class,
    ];

    /** Display order for command groups. */
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

    /**
     * Run the CLI. Returns an exit code.
     */
    public function run(argv_parser $input): int {
        $this->quiet   = $input->has_flag('quiet', 'q');
        $this->no_ansi = $input->has_flag('no-ansi');

        if ($input->has_flag('version', 'V')) {
            echo "SKIM Framework CLI v" . self::VERSION . "\n";
            return 0;
        }

        if ($this->no_ansi) {
            cli::force_plain(true);
        }

        $command_name = $input->command;

        // Resolve the command — loop allows TUI re-dispatch
        while (true) {
            $class = $this->resolve($command_name);

            if ($class !== null) {
                return $this->dispatch($class, $command_name, $input);
            }

            // help / list → show command listing
            if ($command_name === 'help' || $command_name === 'list') {
                $selected = $this->show_help();
                if ($selected === null) {
                    return 0;
                }
                // Re-dispatch with the selected command
                $command_name = $selected;
                continue;
            }

            // Unknown command
            cli::error_box('Error', "Unknown command: {$command_name}");
            cli::did_you_mean($command_name, array_keys($this->all_commands()));
            return 1;
        }
    }

    /**
     * Resolve a command name to its class, or null if not found.
     */
    private function resolve(string $name): ?string {
        $all = $this->all_commands();

        // Direct match
        if (isset($all[$name])) {
            return $all[$name];
        }

        // Try base prefix for colon commands (e.g. migrate:down → migrate)
        if (str_contains($name, ':')) {
            $base = substr($name, 0, strpos($name, ':'));
            return $all[$name] ?? ($all[$base] ?? null);
        }

        return null;
    }

    /**
     * Merge built-in commands with user-defined ones from config.
     */
    private function all_commands(): array {
        $user_commands = \skim\core\config::get('app.commands', []);
        return array_merge(self::COMMANDS, $user_commands);
    }

    /**
     * Dispatch a resolved command class.
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
            cli::error_box('Error', $e->getMessage());
            if (\skim\core\config::get('app.debug')) {
                cli::muted($e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Show help: interactive TUI (if TTY) or static list.
     * Returns selected command name, or null to exit.
     */
    private function show_help(): ?string {
        $groups = $this->build_groups();

        $is_interactive = cli::is_tty() && !$this->no_ansi && !$this->quiet;

        if ($is_interactive) {
            $this->print_header();
            $menu = new interactive_menu($groups);
            return $menu->run();
        }

        // Static fallback
        $this->print_header();
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
     * Build ordered command groups for display.
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

        // Order groups according to GROUP_ORDER, then any extras
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
     * Print the SKIM header banner (logo + environment metadata).
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
