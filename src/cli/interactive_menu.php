<?php declare(strict_types=1);

namespace skim\cli;

/**
 * Full-screen interactive terminal menu for CLI command discovery and navigation.
 *
 * Use when the CLI is invoked without a command (or with `help`/`list`) on a TTY.
 * Provides keyboard-driven navigation with collapsible groups, live search (/ or :),
 * and ANSI-styled rendering. Falls back to null (quit) when not connected to a TTY.
 *
 * Example:
 *   $menu = new interactive_menu($groups);
 *   $selected = $menu->run(); // returns command name or null
 *
 * Testing: Not designed for automated testing — requires interactive TTY with raw mode.
 *
 * #AI:class
 */
final class interactive_menu {
    private array $groups;
    private array $group_expanded = [];
    private array $flat_items = [];
    private int $selected_index = 0;
    private bool $search_mode = false;
    private string $search_query = '';
    private bool $force_plain = false;
    private ?string $original_tty_settings = null;
    private int $last_rendered_lines = 0;
    private ?string $selected_command = null;

    /**
     * @param array $groups Ordered array of group records with 'label' and 'commands' keys.
     */
    public function __construct(array $groups) {
        $this->groups = $groups;
        $this->rebuild_flat_items();
    }

    /**
     * Runs the interactive menu loop, returning the selected command name or null. #AI:run
     *
     * Sets up raw TTY mode, registers SIGINT handler, and enters a render/input loop.
     * Returns null when the user presses q or Esc to quit. Restores TTY settings
     * in a finally block to prevent terminal corruption on exit.
     *
     * @return string|null Selected command name, or null if user quit.
     */
    public function run(): ?string {
        if (!self::is_tty()) {
            return null;
        }

        $this->setup_tty();

        if (function_exists('pcntl_signal')) {
            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
            }
            pcntl_signal(SIGINT, function() {
                $this->restore_tty();
                exit(1);
            });
        }

        $this->render();

        try {
            while (true) {
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                $raw_key = $this->read_key();
                if ($raw_key === '') {
                    continue;
                }

                $key = $this->decode_key($raw_key);

                if ($key === 'q' && !$this->search_mode) {
                    $this->clear_menu();
                    return null;
                }

                if ($key === 'esc') {
                    if ($this->search_mode) {
                        $this->search_mode = false;
                        $this->search_query = '';
                        $this->rebuild_flat_items();
                        $this->selected_index = 0;
                    } else {
                        $this->clear_menu();
                        return null;
                    }
                    $this->render();
                    continue;
                }

                $this->handle_key($key);

                if ($this->selected_command !== null) {
                    $cmd = $this->selected_command;
                    $this->selected_command = null;
                    $this->clear_menu();
                    return $cmd;
                }

                $this->render();
            }
        } finally {
            $this->restore_tty();
        }
    }

    /**
     * Moves cursor up over the rendered menu and erases it. #AI:clear_menu
     */
    private function clear_menu(): void {
        if ($this->last_rendered_lines > 0) {
            echo "\e[" . $this->last_rendered_lines . "A";
        }
        echo "\e[J";
    }

    /**
     * Dispatches a decoded key press to navigation or search logic. #AI:handle_key
     *
     * @param string $key Decoded key name (up, down, left, right, enter, etc.).
     */
    private function handle_key(string $key): void {
        $item = $this->flat_items[$this->selected_index] ?? null;

        if ($key === 'up') {
            $this->selected_index = max(0, $this->selected_index - 1);
            return;
        }

        if ($key === 'down') {
            $this->selected_index = min(count($this->flat_items) - 1, $this->selected_index + 1);
            return;
        }

        if ($key === 'right') {
            if ($item && $item['type'] === 'group') {
                $this->group_expanded[$item['group_idx']] = true;
                $this->rebuild_flat_items();
            }
            return;
        }

        if ($key === 'left') {
            if ($item) {
                if ($item['type'] === 'group') {
                    $this->group_expanded[$item['group_idx']] = false;
                    $this->rebuild_flat_items();
                } elseif ($item['type'] === 'command') {
                    foreach ($this->flat_items as $idx => $fit) {
                        if ($fit['type'] === 'group' && $fit['group_idx'] === $item['group_idx']) {
                            $this->selected_index = $idx;
                            break;
                        }
                    }
                }
            }
            return;
        }

        if ($key === 'enter') {
            if ($item) {
                if ($item['type'] === 'group') {
                    $idx = $item['group_idx'];
                    $this->group_expanded[$idx] = !($this->group_expanded[$idx] ?? false);
                    $this->rebuild_flat_items();
                } elseif ($item['type'] === 'command') {
                    $this->selected_command = $item['name'];
                }
            }
            return;
        }

        if (!$this->search_mode && ($key === '/' || $key === ':')) {
            $this->search_mode = true;
            $this->search_query = '';
            $this->rebuild_flat_items();
            $this->selected_index = 0;
            return;
        }

        if ($this->search_mode) {
            if ($key === 'backspace') {
                $this->search_query = mb_substr($this->search_query, 0, -1);
                $this->rebuild_flat_items();
                $this->selected_index = 0;
            } elseif (strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
                $this->search_query .= $key;
                $this->rebuild_flat_items();
                $this->selected_index = 0;
            }
        }
    }

    /**
     * Rebuilds the flat item list from groups, filtered by search query when active. #AI:rebuild_flat_items
     */
    private function rebuild_flat_items(): void {
        $this->flat_items = [];
        if ($this->search_mode) {
            foreach ($this->groups as $g_idx => $g) {
                foreach ($g['commands'] as $c_idx => $cmd) {
                    if ($this->search_query === '' || 
                        str_contains(strtolower($cmd['name']), strtolower($this->search_query)) || 
                        str_contains(strtolower($cmd['description']), strtolower($this->search_query))) {
                        $this->flat_items[] = [
                            'type' => 'command',
                            'group_idx' => $g_idx,
                            'cmd_idx' => $c_idx,
                            'name' => $cmd['name'],
                            'usage' => $cmd['usage'] ?? '',
                            'description' => $cmd['description'] ?? '',
                        ];
                    }
                }
            }
        } else {
            foreach ($this->groups as $g_idx => $g) {
                $this->flat_items[] = [
                    'type' => 'group',
                    'group_idx' => $g_idx,
                    'label' => $g['label'],
                ];
                $is_expanded = $this->group_expanded[$g_idx] ?? false;
                if ($is_expanded) {
                    foreach ($g['commands'] as $c_idx => $cmd) {
                        $this->flat_items[] = [
                            'type' => 'command',
                            'group_idx' => $g_idx,
                            'cmd_idx' => $c_idx,
                            'name' => $cmd['name'],
                            'usage' => $cmd['usage'] ?? '',
                            'description' => $cmd['description'] ?? '',
                        ];
                    }
                }
            }
        }

        if ($this->selected_index >= count($this->flat_items)) {
            $this->selected_index = max(0, count($this->flat_items) - 1);
        }
    }

    /**
     * Renders the full menu to stdout, overwriting the previous frame. #AI:render
     */
    private function render(): void {
        if ($this->force_plain) {
            return;
        }

        if ($this->last_rendered_lines > 0) {
            echo "\e[" . $this->last_rendered_lines . "A";
        }
        echo "\e[J";

        $output = '';

        if ($this->search_mode) {
            $output .= "\e[38;5;245mSEARCH RESULTS\e[0m\n";
            if (empty($this->flat_items)) {
                $output .= "  \e[2mNo commands match '{$this->search_query}'\e[0m\n";
            } else {
                foreach ($this->flat_items as $idx => $item) {
                    $cmd_focused = ($idx === $this->selected_index);
                    $prefix = $cmd_focused ? "\e[38;5;141m❯\e[0m " : '  ';
                    $cmd_name = str_pad($item['name'], 20);
                    $cmd_name_str = "\e[97m" . $cmd_name . "\e[0m";
                    $usage = $item['usage'] !== '' ? " \e[2m" . $item['usage'] . "\e[0m" : '';
                    $desc = $item['description'] !== '' ? "   \e[2m" . $item['description'] . "\e[0m" : '';
                    
                    $row_text = $prefix . $cmd_name_str . $usage . $desc;
                    if ($cmd_focused) {
                        $row_text = "\e[48;5;235m" . str_pad($row_text, 80) . "\e[0m";
                    }
                    $output .= "  " . $row_text . "\n";
                }
            }
        } else {
            $last_g_idx = -1;
            foreach ($this->flat_items as $idx => $item) {
                $focused = ($idx === $this->selected_index);

                if ($item['type'] === 'group') {
                    $g_idx = $item['group_idx'];
                    if ($last_g_idx !== -1) {
                        $output .= "\n";
                    }
                    $last_g_idx = $g_idx;

                    $is_expanded = $this->group_expanded[$g_idx] ?? false;
                    $arrow = $is_expanded ? '▼' : '▶';
                    $prefix = $focused ? "\e[38;5;141m❯\e[0m " : '  ';
                    $arrow_str = "\e[38;5;141m" . $arrow . "\e[0m";
                    $label_str = $focused ? "\e[1;97m" . strtoupper($item['label']) . "\e[0m" : "\e[1;38;5;245m" . strtoupper($item['label']) . "\e[0m";

                    $row_text = $prefix . $label_str . ' ' . $arrow_str;
                    if ($focused) {
                        $row_text = "\e[48;5;235m" . str_pad($row_text, 80) . "\e[0m";
                    }
                    $output .= $row_text . "\n";
                } elseif ($item['type'] === 'command') {
                    $prefix = $focused ? "  \e[38;5;141m❯\e[0m " : '    ';
                    $cmd_name = str_pad($item['name'], 20);
                    $cmd_name_str = "\e[97m" . $cmd_name . "\e[0m";
                    $usage = $item['usage'] !== '' ? " \e[2m" . $item['usage'] . "\e[0m" : '';
                    $desc = $item['description'] !== '' ? "   \e[2m" . $item['description'] . "\e[0m" : '';

                    $row_text = $prefix . $cmd_name_str . $usage . $desc;
                    if ($focused) {
                        $row_text = "\e[48;5;235m" . str_pad($row_text, 80) . "\e[0m";
                    }
                    $output .= $row_text . "\n";
                }
            }
        }

        $cols = 80;
        if (self::is_tty()) {
            $cols = (int)(shell_exec('tput cols 2>/dev/null') ?: 80);
        }
        $divider = "\e[2m" . str_repeat('─', $cols) . "\e[0m";
        
        $selected_item = $this->flat_items[$this->selected_index] ?? null;
        if ($this->search_mode) {
            $prompt = "\e[38;5;141m❯\e[0m php skim " . $this->search_query . "\e[5m_\e[0m";
        } else {
            $cmd_part = '';
            if ($selected_item && $selected_item['type'] === 'command') {
                $cmd_part = $selected_item['name'] . '_';
            }
            $prompt = "\e[38;5;141m❯\e[0m php skim " . $cmd_part;
        }

        $status_bar = "\e[2m↑↓\e[0m navigate   \e[2m→\e[0m expand   \e[2m←\e[0m collapse   \e[2m/\e[0m search   \e[2menter\e[0m run   \e[2mq\e[0m quit";

        $output .= "\n" . $prompt . "\n" . $divider . "\n" . $status_bar . "\n";

        echo $output;
        $this->last_rendered_lines = substr_count($output, "\n");
    }

    /**
     * Reads raw key bytes from STDIN with a 100ms timeout. #AI:read_key
     */
    private function read_key(): string {
        if (feof(STDIN)) {
            throw new \RuntimeException("STDIN EOF");
        }
        $r = [STDIN];
        $w = null;
        $e = null;
        $select = @stream_select($r, $w, $e, 0, 100000);
        if ($select === false) {
            return '';
        }
        if ($select > 0) {
            $data = fread(STDIN, 8);
            if ($data === false || $data === '') {
                throw new \RuntimeException("STDIN closed");
            }
            return $data;
        }
        return '';
    }

    /**
     * Decodes raw terminal escape sequences into named key identifiers. #AI:decode_key
     *
     * @param string $raw Raw bytes from STDIN.
     */
    private function decode_key(string $raw): string {
        if ($raw === "\e[A" || $raw === "\eOA") return 'up';
        if ($raw === "\e[B" || $raw === "\eOB") return 'down';
        if ($raw === "\e[C" || $raw === "\eOC") return 'right';
        if ($raw === "\e[D" || $raw === "\eOD") return 'left';
        if ($raw === "\n" || $raw === "\r") return 'enter';
        if ($raw === "\e") return 'esc';
        if ($raw === "\x7f" || $raw === "\x08") return 'backspace';
        return $raw;
    }

    /**
     * Puts the terminal into raw mode for character-by-character input. #AI:setup_tty
     */
    private function setup_tty(): void {
        $this->original_tty_settings = shell_exec('stty -g');
        system('stty -echo -icanon min 1 time 0');
        echo "\e[?25l";
    }

    /**
     * Restores original TTY settings and shows the cursor. #AI:restore_tty
     */
    private function restore_tty(): void {
        echo "\e[?25h";
        if ($this->original_tty_settings !== null && trim($this->original_tty_settings) !== '') {
            system('stty ' . escapeshellarg(trim($this->original_tty_settings)));
        } else {
            system('stty echo icanon');
        }
    }

    /**
     * Returns true when stdout is connected to an interactive terminal. #AI:is_tty
     */
    private static function is_tty(): bool {
        if (function_exists('stream_isatty') && @stream_isatty(STDOUT)) {
            return true;
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}

#AI:class
#AI symbol: skim\cli\interactive_menu
#AI source_path: src/cli/interactive_menu.php
#AI title: interactive_menu
#AI description: Full-screen interactive terminal menu for CLI command discovery with keyboard navigation and live search.
#AI role: CLI interactive command browser
#AI layer: cli
#AI badges: [cli; interactive; tui; keyboard-driven; ansi]
#AI intro: `interactive_menu` provides a full-screen, keyboard-driven terminal UI for browsing and selecting CLI commands. It supports collapsible command groups, live search with / or :, and ANSI-styled rendering. Returns null when not on a TTY.
#AI lifecycle: instantiated by kernel with command groups, run() enters event loop, returns selected command or null
#AI fallback: returns null immediately when not connected to a TTY
#AI test_seam: not designed for automated testing — requires interactive TTY with raw mode
#AI invariants: [TTY settings are always restored in finally block; SIGINT handler restores TTY before exit; cursor is hidden during menu and restored on exit]
#AI core_behaviors: [Raw TTY mode for character-by-character input; Collapsible groups with arrow keys; Live search with / or : prefix; ANSI-styled rendering with focus highlighting; Escape sequences decoded to named keys]
#AI owns: group expansion state, search state, selected index, TTY settings
#AI entry_points: [run]
#AI config_reads: []
#AI non_goals: [Does not execute commands (returns name to caller); Does not support mouse input; Does not support scrolling beyond terminal height]
#AI side_effects: [modifies TTY settings (restored on exit); writes ANSI escape sequences to stdout; registers SIGINT handler]
#AI flow: interactive_menu::run() -> setup_tty() -> render loop: read_key() -> decode_key() -> handle_key() -> render() -> return command or null
#AI lifecycle_steps: [run(); -> is_tty() check; -> setup_tty() raw mode; -> register SIGINT handler; -> render() initial frame; -> loop: read_key() -> decode_key() -> handle_key() -> render(); -> on quit: clear_menu() -> restore_tty(); -> return command name or null]
#AI section_order: [Menu Execution; Key Handling; Rendering; TTY Management; Architecture]
#AI architectural_notes: The menu takes full control of the terminal in raw mode. TTY settings are saved and restored in a finally block to prevent terminal corruption even on exceptions.

#AI:run
#AI group: Menu Execution
#AI frequency: low
#AI signature: public function run(): ?string
#AI contract: Runs the interactive menu loop. Sets up raw TTY mode, renders frames, reads key input, and returns the selected command name or null on quit.
#AI return_detail: {type: ?string | desc: Selected command name, or null if user quit.}

#AI:clear_menu
#AI group: Rendering
#AI frequency: internal
#AI signature: private function clear_menu(): void
#AI contract: Moves cursor up over the rendered menu and erases it using ANSI escape sequences.

#AI:handle_key
#AI group: Key Handling
#AI frequency: internal
#AI signature: private function handle_key(string $key): void
#AI contract: Dispatches a decoded key press to navigation (up/down/left/right/enter) or search logic.
#AI param_details: [{name: $key | type: string | required: true | desc: Decoded key name (up, down, left, right, enter, esc, backspace, or single character).}]

#AI:rebuild_flat_items
#AI group: Rendering
#AI frequency: internal
#AI signature: private function rebuild_flat_items(): void
#AI contract: Rebuilds the flat item list from groups. In search mode, filters by query. In browse mode, respects group expansion state.

#AI:render
#AI group: Rendering
#AI frequency: internal
#AI signature: private function render(): void
#AI contract: Renders the full menu to stdout, overwriting the previous frame with ANSI cursor control.

#AI:read_key
#AI group: Key Handling
#AI frequency: internal
#AI signature: private function read_key(): string
#AI contract: Reads raw key bytes from STDIN with a 100ms timeout using stream_select.
#AI return_detail: {type: string | desc: Raw bytes from STDIN, or empty string on timeout.}

#AI:decode_key
#AI group: Key Handling
#AI frequency: internal
#AI signature: private function decode_key(string $raw): string
#AI contract: Decodes raw terminal escape sequences into named key identifiers (up, down, left, right, enter, esc, backspace).
#AI param_details: [{name: $raw | type: string | required: true | desc: Raw bytes from STDIN.}]
#AI return_detail: {type: string | desc: Named key identifier or the raw character.}

#AI:setup_tty
#AI group: TTY Management
#AI frequency: internal
#AI signature: private function setup_tty(): void
#AI contract: Saves current TTY settings and puts terminal into raw mode for character-by-character input. Hides cursor.

#AI:restore_tty
#AI group: TTY Management
#AI frequency: internal
#AI signature: private function restore_tty(): void
#AI contract: Restores original TTY settings and shows the cursor. Falls back to stty echo icanon if saved settings are unavailable.

#AI:is_tty
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function is_tty(): bool
#AI contract: Returns true when stdout is connected to an interactive terminal.
