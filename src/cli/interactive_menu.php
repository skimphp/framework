<?php declare(strict_types=1);
// Created: Interactive full-screen terminal UI menu for SKIM CLI command discovery and navigation.

namespace skim\cli;

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

    public function __construct(array $groups) {
        $this->groups = $groups;
        $this->rebuild_flat_items();
    }

    /**
     * Run the interactive menu. Returns the selected command name, or null if quit.
     * The caller (kernel) is responsible for printing the header before calling this.
     */
    public function run(): ?string {
        if (!self::is_tty()) {
            return null;
        }

        $this->setup_tty();

        // Register pcntl signal handler if available
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

                // Check if a command was selected
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

    /** Move cursor up over the rendered menu and erase it. */
    private function clear_menu(): void {
        if ($this->last_rendered_lines > 0) {
            echo "\e[" . $this->last_rendered_lines . "A";
        }
        echo "\e[J";
    }

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
                    // Jump to group header
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

        // Search trigger
        if (!$this->search_mode && ($key === '/' || $key === ':')) {
            $this->search_mode = true;
            $this->search_query = '';
            $this->rebuild_flat_items();
            $this->selected_index = 0;
            return;
        }

        // Search text input
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

        // Clamp selected index
        if ($this->selected_index >= count($this->flat_items)) {
            $this->selected_index = max(0, count($this->flat_items) - 1);
        }
    }

    private function render(): void {
        if ($this->force_plain) {
            return;
        }

        // Restore cursor to start of menu and clear everything below
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

        // Divider
        $cols = 80;
        if (self::is_tty()) {
            $cols = (int)(shell_exec('tput cols 2>/dev/null') ?: 80);
        }
        $divider = "\e[2m" . str_repeat('─', $cols) . "\e[0m";
        
        // Prompt line
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

        // Status bar
        $status_bar = "\e[2m↑↓\e[0m navigate   \e[2m→\e[0m expand   \e[2m←\e[0m collapse   \e[2m/\e[0m search   \e[2menter\e[0m run   \e[2mq\e[0m quit";

        $output .= "\n" . $prompt . "\n" . $divider . "\n" . $status_bar . "\n";

        echo $output;
        $this->last_rendered_lines = substr_count($output, "\n");
    }

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

    private function setup_tty(): void {
        $this->original_tty_settings = shell_exec('stty -g');
        system('stty -echo -icanon min 1 time 0');
        echo "\e[?25l"; // Hide cursor
    }

    private function restore_tty(): void {
        echo "\e[?25h"; // Show cursor
        if ($this->original_tty_settings !== null && trim($this->original_tty_settings) !== '') {
            system('stty ' . escapeshellarg(trim($this->original_tty_settings)));
        } else {
            system('stty echo icanon');
        }
    }

    private static function is_tty(): bool {
        if (function_exists('stream_isatty') && @stream_isatty(STDOUT)) {
            return true;
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
