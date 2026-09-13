<?php declare(strict_types=1);

namespace Skim\Cli;

/**
 * Full-screen interactive terminal menu for CLI command discovery and navigation.
 *
 * Use when the CLI is invoked without a command (or with `help`/`list`) on a TTY.
 * Provides keyboard-driven navigation with collapsible groups, live search (/ or :),
 * and ANSI-styled rendering. Falls back to null (quit) when not connected to a TTY.
 *
 * Example:
 *   $menu = new InteractiveMenu($groups);
 *   $selected = $menu->run(); // returns command name or null
 *
 * Testing: Not designed for automated testing — requires interactive TTY with raw mode.
 *
 * #AI:class
 */
final class InteractiveMenu {
    private array $groups;
    private array $groupExpanded = [];
    private array $flatItems = [];
    private int $selectedIndex = 0;
    private bool $searchMode = false;
    private string $searchQuery = '';
    private bool $forcePlain = false;
    private ?string $originalTtySettings = null;
    private int $lastRenderedLines = 0;
    private ?string $selectedCommand = null;

    /**
     * @param array $groups Ordered array of group records with 'label' and 'commands' keys.
     */
    public function __construct(array $groups) {
        $this->groups = $groups;
        $this->rebuildFlatItems();
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
        if (!self::isTty()) {
            return null;
        }

        $this->setupTty();

        if (function_exists('pcntl_signal')) {
            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
            }
            pcntl_signal(SIGINT, function() {
                $this->restoreTty();
                exit(1);
            });
        }

        $this->render();

        try {
            while (true) {
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                $rawKey = $this->readKey();
                if ($rawKey === '') {
                    continue;
                }

                $key = $this->decodeKey($rawKey);

                if ($key === 'q' && !$this->searchMode) {
                    $this->clearMenu();
                    return null;
                }

                if ($key === 'esc') {
                    if ($this->searchMode) {
                        $this->searchMode = false;
                        $this->searchQuery = '';
                        $this->rebuildFlatItems();
                        $this->selectedIndex = 0;
                    } else {
                        $this->clearMenu();
                        return null;
                    }
                    $this->render();
                    continue;
                }

                $this->handleKey($key);

                if ($this->selectedCommand !== null) {
                    $cmd = $this->selectedCommand;
                    $this->selectedCommand = null;
                    $this->clearMenu();
                    return $cmd;
                }

                $this->render();
            }
        } finally {
            $this->restoreTty();
        }
    }

    /**
     * Moves cursor up over the rendered menu and erases it. #AI:clearMenu
     */
    private function clearMenu(): void {
        if ($this->lastRenderedLines > 0) {
            echo "\e[" . $this->lastRenderedLines . "A";
        }
        echo "\e[J";
    }

    /**
     * Dispatches a decoded key press to navigation or search logic. #AI:handleKey
     *
     * @param string $key Decoded key name (up, down, left, right, enter, etc.).
     */
    private function handleKey(string $key): void {
        $item = $this->flatItems[$this->selectedIndex] ?? null;

        if ($key === 'up') {
            $this->selectedIndex = max(0, $this->selectedIndex - 1);
            return;
        }

        if ($key === 'down') {
            $this->selectedIndex = min(count($this->flatItems) - 1, $this->selectedIndex + 1);
            return;
        }

        if ($key === 'right') {
            if ($item && $item['type'] === 'group') {
                $this->groupExpanded[$item['group_idx']] = true;
                $this->rebuildFlatItems();
            }
            return;
        }

        if ($key === 'left') {
            if ($item) {
                if ($item['type'] === 'group') {
                    $this->groupExpanded[$item['group_idx']] = false;
                    $this->rebuildFlatItems();
                } elseif ($item['type'] === 'command') {
                    foreach ($this->flatItems as $idx => $fit) {
                        if ($fit['type'] === 'group' && $fit['group_idx'] === $item['group_idx']) {
                            $this->selectedIndex = $idx;
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
                    $this->groupExpanded[$idx] = !($this->groupExpanded[$idx] ?? false);
                    $this->rebuildFlatItems();
                } elseif ($item['type'] === 'command') {
                    $this->selectedCommand = $item['name'];
                }
            }
            return;
        }

        if (!$this->searchMode && ($key === '/' || $key === ':')) {
            $this->searchMode = true;
            $this->searchQuery = '';
            $this->rebuildFlatItems();
            $this->selectedIndex = 0;
            return;
        }

        if ($this->searchMode) {
            if ($key === 'backspace') {
                $this->searchQuery = mb_substr($this->searchQuery, 0, -1);
                $this->rebuildFlatItems();
                $this->selectedIndex = 0;
            } elseif (strlen($key) === 1 && ord($key) >= 32 && ord($key) <= 126) {
                $this->searchQuery .= $key;
                $this->rebuildFlatItems();
                $this->selectedIndex = 0;
            }
        }
    }

    /**
     * Rebuilds the flat item list from groups, filtered by search query when active. #AI:rebuildFlatItems
     */
    private function rebuildFlatItems(): void {
        $this->flatItems = [];
        if ($this->searchMode) {
            foreach ($this->groups as $gIdx => $g) {
                foreach ($g['commands'] as $cIdx => $cmd) {
                    if ($this->searchQuery === '' ||
                        str_contains(strtolower($cmd['name']), strtolower($this->searchQuery)) ||
                        str_contains(strtolower($cmd['description']), strtolower($this->searchQuery))) {
                        $this->flatItems[] = [
                            'type' => 'command',
                            'group_idx' => $gIdx,
                            'cmd_idx' => $cIdx,
                            'name' => $cmd['name'],
                            'usage' => $cmd['usage'] ?? '',
                            'description' => $cmd['description'] ?? '',
                        ];
                    }
                }
            }
        } else {
            foreach ($this->groups as $gIdx => $g) {
                $this->flatItems[] = [
                    'type' => 'group',
                    'group_idx' => $gIdx,
                    'label' => $g['label'],
                ];
                $isExpanded = $this->groupExpanded[$gIdx] ?? false;
                if ($isExpanded) {
                    foreach ($g['commands'] as $cIdx => $cmd) {
                        $this->flatItems[] = [
                            'type' => 'command',
                            'group_idx' => $gIdx,
                            'cmd_idx' => $cIdx,
                            'name' => $cmd['name'],
                            'usage' => $cmd['usage'] ?? '',
                            'description' => $cmd['description'] ?? '',
                        ];
                    }
                }
            }
        }

        if ($this->selectedIndex >= count($this->flatItems)) {
            $this->selectedIndex = max(0, count($this->flatItems) - 1);
        }
    }

    /**
     * Renders the full menu to stdout, overwriting the previous frame. #AI:render
     */
    private function render(): void {
        if ($this->forcePlain) {
            return;
        }

        if ($this->lastRenderedLines > 0) {
            echo "\e[" . $this->lastRenderedLines . "A";
        }
        echo "\e[J";

        $output = '';

        if ($this->searchMode) {
            $output .= "\e[38;5;245mSEARCH RESULTS\e[0m\n";
            if (empty($this->flatItems)) {
                $output .= "  \e[2mNo commands match '{$this->searchQuery}'\e[0m\n";
            } else {
                foreach ($this->flatItems as $idx => $item) {
                    $cmdFocused = ($idx === $this->selectedIndex);
                    $prefix = $cmdFocused ? "\e[38;5;141m❯\e[0m " : '  ';
                    $cmdName = str_pad($item['name'], 20);
                    $cmdNameStr = "\e[97m" . $cmdName . "\e[0m";
                    $usage = $item['usage'] !== '' ? " \e[2m" . $item['usage'] . "\e[0m" : '';
                    $desc = $item['description'] !== '' ? "   \e[2m" . $item['description'] . "\e[0m" : '';
                    
                    $rowText = $prefix . $cmdNameStr . $usage . $desc;
                    if ($cmdFocused) {
                        $rowText = "\e[48;5;235m" . str_pad($rowText, 80) . "\e[0m";
                    }
                    $output .= "  " . $rowText . "\n";
                }
            }
        } else {
            $lastGIdx = -1;
            foreach ($this->flatItems as $idx => $item) {
                $focused = ($idx === $this->selectedIndex);

                if ($item['type'] === 'group') {
                    $gIdx = $item['group_idx'];
                    if ($lastGIdx !== -1) {
                        $output .= "\n";
                    }
                    $lastGIdx = $gIdx;

                    $isExpanded = $this->groupExpanded[$gIdx] ?? false;
                    $arrow = $isExpanded ? '▼' : '▶';
                    $prefix = $focused ? "\e[38;5;141m❯\e[0m " : '  ';
                    $arrowStr = "\e[38;5;141m" . $arrow . "\e[0m";
                    $labelStr = $focused ? "\e[1;97m" . strtoupper($item['label']) . "\e[0m" : "\e[1;38;5;245m" . strtoupper($item['label']) . "\e[0m";

                    $rowText = $prefix . $labelStr . ' ' . $arrowStr;
                    if ($focused) {
                        $rowText = "\e[48;5;235m" . str_pad($rowText, 80) . "\e[0m";
                    }
                    $output .= $rowText . "\n";
                } elseif ($item['type'] === 'command') {
                    $prefix = $focused ? "  \e[38;5;141m❯\e[0m " : '    ';
                    $cmdName = str_pad($item['name'], 20);
                    $cmdNameStr = "\e[97m" . $cmdName . "\e[0m";
                    $usage = $item['usage'] !== '' ? " \e[2m" . $item['usage'] . "\e[0m" : '';
                    $desc = $item['description'] !== '' ? "   \e[2m" . $item['description'] . "\e[0m" : '';

                    $rowText = $prefix . $cmdNameStr . $usage . $desc;
                    if ($focused) {
                        $rowText = "\e[48;5;235m" . str_pad($rowText, 80) . "\e[0m";
                    }
                    $output .= $rowText . "\n";
                }
            }
        }

        $cols = 80;
        if (self::isTty()) {
            $cols = (int)(shell_exec('tput cols 2>/dev/null') ?: 80);
        }
        $divider = "\e[2m" . str_repeat('─', $cols) . "\e[0m";
        
        $selectedItem = $this->flatItems[$this->selectedIndex] ?? null;
        if ($this->searchMode) {
            $prompt = "\e[38;5;141m❯\e[0m php skim " . $this->searchQuery . "\e[5m_\e[0m";
        } else {
            $cmdPart = '';
            if ($selectedItem && $selectedItem['type'] === 'command') {
                $cmdPart = $selectedItem['name'] . '_';
            }
            $prompt = "\e[38;5;141m❯\e[0m php skim " . $cmdPart;
        }

        $statusBar = "\e[2m↑↓\e[0m navigate   \e[2m→\e[0m expand   \e[2m←\e[0m collapse   \e[2m/\e[0m search   \e[2menter\e[0m run   \e[2mq\e[0m quit";

        $output .= "\n" . $prompt . "\n" . $divider . "\n" . $statusBar . "\n";

        echo $output;
        $this->lastRenderedLines = substr_count($output, "\n");
    }

    /**
     * Reads raw key bytes from STDIN with a 100ms timeout. #AI:readKey
     */
    private function readKey(): string {
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
     * Decodes raw terminal escape sequences into named key identifiers. #AI:decodeKey
     *
     * @param string $raw Raw bytes from STDIN.
     */
    private function decodeKey(string $raw): string {
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
     * Puts the terminal into raw mode for character-by-character input. #AI:setupTty
     */
    private function setupTty(): void {
        $this->originalTtySettings = shell_exec('stty -g');
        system('stty -echo -icanon min 1 time 0');
        echo "\e[?25l";
    }

    /**
     * Restores original TTY settings and shows the cursor. #AI:restoreTty
     */
    private function restoreTty(): void {
        echo "\e[?25h";
        if ($this->originalTtySettings !== null && trim($this->originalTtySettings) !== '') {
            system('stty ' . escapeshellarg(trim($this->originalTtySettings)));
        } else {
            system('stty echo icanon');
        }
    }

    /**
     * Returns true when stdout is connected to an interactive terminal. #AI:isTty
     */
    private static function isTty(): bool {
        if (function_exists('stream_isatty') && @stream_isatty(STDOUT)) {
            return true;
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}

#AI:class
#AI symbol: Skim\Cli\InteractiveMenu
#AI source_path: src/Cli/InteractiveMenu.php
#AI title: InteractiveMenu
#AI description: Full-screen interactive terminal menu for CLI command discovery with keyboard navigation and live search.
#AI role: CLI interactive command browser
#AI layer: cli
#AI badges: [cli; interactive; tui; keyboard-driven; ansi]
#AI intro: `InteractiveMenu` provides a full-screen, keyboard-driven terminal UI for browsing and selecting CLI commands. It supports collapsible command groups, live search with / or :, and ANSI-styled rendering. Returns null when not on a TTY.
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
#AI flow: InteractiveMenu::run() -> setupTty() -> render loop: readKey() -> decodeKey() -> handleKey() -> render() -> return command or null
#AI lifecycle_steps: [run(); -> isTty() check; -> setupTty() raw mode; -> register SIGINT handler; -> render() initial frame; -> loop: readKey() -> decodeKey() -> handleKey() -> render(); -> on quit: clearMenu() -> restoreTty(); -> return command name or null]
#AI section_order: [Menu Execution; Key Handling; Rendering; TTY Management; Architecture]
#AI architectural_notes: The menu takes full control of the terminal in raw mode. TTY settings are saved and restored in a finally block to prevent terminal corruption even on exceptions.

#AI:run
#AI group: Menu Execution
#AI frequency: low
#AI signature: public function run(): ?string
#AI contract: Runs the interactive menu loop. Sets up raw TTY mode, renders frames, reads key input, and returns the selected command name or null on quit.
#AI return_detail: {type: ?string | desc: Selected command name, or null if user quit.}

#AI:clearMenu
#AI group: Rendering
#AI frequency: internal
#AI signature: private function clearMenu(): void
#AI contract: Moves cursor up over the rendered menu and erases it using ANSI escape sequences.

#AI:handleKey
#AI group: Key Handling
#AI frequency: internal
#AI signature: private function handleKey(string $key): void
#AI contract: Dispatches a decoded key press to navigation (up/down/left/right/enter) or search logic.
#AI param_details: [{name: $key | type: string | required: true | desc: Decoded key name (up, down, left, right, enter, esc, backspace, or single character).}]

#AI:rebuildFlatItems
#AI group: Rendering
#AI frequency: internal
#AI signature: private function rebuildFlatItems(): void
#AI contract: Rebuilds the flat item list from groups. In search mode, filters by query. In browse mode, respects group expansion state.

#AI:render
#AI group: Rendering
#AI frequency: internal
#AI signature: private function render(): void
#AI contract: Renders the full menu to stdout, overwriting the previous frame with ANSI cursor control.

#AI:readKey
#AI group: Key Handling
#AI frequency: internal
#AI signature: private function readKey(): string
#AI contract: Reads raw key bytes from STDIN with a 100ms timeout using stream_select.
#AI return_detail: {type: string | desc: Raw bytes from STDIN, or empty string on timeout.}

#AI:decodeKey
#AI group: Key Handling
#AI frequency: internal
#AI signature: private function decodeKey(string $raw): string
#AI contract: Decodes raw terminal escape sequences into named key identifiers (up, down, left, right, enter, esc, backspace).
#AI param_details: [{name: $raw | type: string | required: true | desc: Raw bytes from STDIN.}]
#AI return_detail: {type: string | desc: Named key identifier or the raw character.}

#AI:setupTty
#AI group: TTY Management
#AI frequency: internal
#AI signature: private function setupTty(): void
#AI contract: Saves current TTY settings and puts terminal into raw mode for character-by-character input. Hides cursor.

#AI:restoreTty
#AI group: TTY Management
#AI frequency: internal
#AI signature: private function restoreTty(): void
#AI contract: Restores original TTY settings and shows the cursor. Falls back to stty echo icanon if saved settings are unavailable.

#AI:isTty
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function isTty(): bool
#AI contract: Returns true when stdout is connected to an interactive terminal.
