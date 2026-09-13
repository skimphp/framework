<?php declare(strict_types=1);

namespace Skim\Cli;

/**
 * ANSI-colored CLI output helper with TTY-aware escape code suppression.
 *
 * Use when any CLI command or worker needs styled terminal output.
 * Escape codes are emitted only when stdout is a real TTY — piped output
 * (grep, CI logs, file redirects) stays plain text. Call forcePlain(true)
 * to suppress colors unconditionally (e.g. --no-ansi flag).
 *
 * Example:
 *   Cli::success('Migration complete.');
 *   Cli::table(['name', 'status'], [['users', 'applied'], ['posts', 'pending']]);
 *
 * Testing: Call Cli::forcePlain(true) in setUp() to get deterministic output.
 *
 * #AI:class
 */
final class Cli {
    private static bool $forcePlain = false;

    /**
     * Forces plain-text output regardless of TTY detection. #AI:forcePlain
     *
     * @param bool $plain True to suppress all ANSI escape codes.
     */
    public static function forcePlain(bool $plain): void {
        self::$forcePlain = $plain;
    }

    /**
     * Prints a line to stdout followed by a newline. #AI:line
     *
     * @param string $msg Text to print. Empty string prints a blank line.
     */
    public static function line(string $msg = ''): void {
        echo $msg . "\n";
    }

    /**
     * Prints a cyan informational message. #AI:info
     *
     * @param string $msg Message text.
     */
    public static function info(string $msg): void {
        echo self::color("\e[36m", $msg) . "\n";
    }

    /**
     * Prints a green success message with checkmark prefix. #AI:success
     *
     * @param string $msg Success message text.
     */
    public static function success(string $msg): void {
        echo self::color("\e[32m", "✔ " . $msg) . "\n";
    }

    /**
     * Prints a yellow warning message with warning prefix. #AI:warn
     *
     * @param string $msg Warning message text.
     */
    public static function warn(string $msg): void {
        echo self::color("\e[33m", "⚠ " . $msg) . "\n";
    }

    /**
     * Prints a red error message to stderr with cross prefix. #AI:error
     *
     * @param string $msg Error message text.
     */
    public static function error(string $msg): void {
        fwrite(STDERR, self::color("\e[31m", "✖ " . $msg) . "\n");
    }

    /**
     * Prints a gray muted message (dimmed for secondary info). #AI:muted
     *
     * @param string $msg Muted text.
     */
    public static function muted(string $msg): void {
        echo self::color("\e[90m", $msg) . "\n";
    }

    /**
     * Prints a bold message. #AI:bold
     *
     * @param string $msg Bold text.
     */
    public static function bold(string $msg): void {
        echo self::color("\e[1m", $msg) . "\n";
    }

    /**
     * Prompts user for text input, returning the trimmed response. #AI:ask
     *
     * Shows $default in brackets — returned when user presses Enter with no input.
     *
     * @param string $question Prompt text displayed to the user.
     * @param string $default Value returned when user presses Enter without typing.
     */
    public static function ask(string $question, string $default = ''): string {
        $hint = $default !== '' ? " [{$default}]" : '';
        echo self::color("\e[36m", "? " . $question . $hint) . " ";
        $input = trim((string) fgets(STDIN));
        return $input !== '' ? $input : $default;
    }

    /**
     * Prompts user with y/n, returning true for yes. #AI:confirm
     *
     * $default determines what pressing Enter alone returns.
     *
     * @param string $question Prompt text.
     * @param bool   $default  Value returned when user presses Enter without typing.
     */
    public static function confirm(string $question, bool $default = true): bool {
        $hint = $default ? '[Y/n]' : '[y/N]';
        echo self::color("\e[36m", "? " . $question . " {$hint}") . " ";
        $input = strtolower(trim((string) fgets(STDIN)));
        if ($input === '') {
            return $default;
        }
        return in_array($input, ['y', 'yes'], true);
    }

    /**
     * Presents a numbered list and returns the selected item. #AI:choice
     *
     * Re-prompts on invalid input until a valid selection is made.
     *
     * @param string $question Prompt heading displayed above the options.
     * @param array  $options  Indexed array of selectable items.
     * @param mixed  $default  Returned when user presses Enter without typing.
     */
    public static function choice(string $question, array $options, mixed $default = null): mixed {
        echo self::color("\e[36m", "? " . $question) . "\n";
        foreach ($options as $i => $option) {
            $label = is_string($option) ? $option : (string) $option;
            echo "  " . self::color("\e[33m", (string)($i + 1)) . ". {$label}\n";
        }
        while (true) {
            $hint = $default !== null ? " [default: {$default}]" : '';
            echo self::color("\e[36m", "> Enter number{$hint}:") . " ";
            $input = trim((string) fgets(STDIN));
            if ($input === '' && $default !== null) {
                return $default;
            }
            $idx = (int) $input - 1;
            if (isset($options[$idx])) {
                return $options[$idx];
            }
            self::warn("Invalid choice. Please enter a number between 1 and " . count($options));
        }
    }

    /**
     * Creates a progress bar instance for tracking long-running operations. #AI:progressBar
     *
     * @param int    $total Total steps (0 for indeterminate spinner mode).
     * @param string $label Text displayed alongside the bar.
     */
    public static function progressBar(int $total = 0, string $label = ''): \Skim\Cli\ProgressBar {
        return new \Skim\Cli\ProgressBar($total, $label);
    }

    /**
     * Renders rows as an ASCII table with auto-fitted column widths. #AI:table
     *
     * @param array $headers Column header labels.
     * @param array $rows    Array of row arrays (values aligned to headers by index).
     */
    public static function table(array $headers, array $rows): void {
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach (array_values($row) as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        $sep = '+' . implode('+', array_map(fn($w) => str_repeat('-', $w + 2), $widths)) . '+';
        $fmt = function(array $cells) use ($widths): string {
            $cols = [];
            foreach (array_values($cells) as $i => $cell) {
                $cols[] = ' ' . str_pad((string) $cell, $widths[$i] ?? 0) . ' ';
            }
            return '|' . implode('|', $cols) . '|';
        };

        echo $sep . "\n";
        echo $fmt($headers) . "\n";
        echo $sep . "\n";
        foreach ($rows as $row) {
            echo $fmt(array_values($row)) . "\n";
        }
        echo $sep . "\n";
    }

    /**
     * Prints the SKIM ASCII logo and environment metadata banner. #AI:header
     *
     * Loads logo from skim_ascii.txt if present, falls back to built-in ASCII art.
     * Renders in purple when TTY, plain text otherwise.
     *
     * @param string $version Framework version string.
     * @param string $php     PHP version string.
     * @param string $env     Application environment (local, staging, production).
     * @param string $os      Operating system identifier.
     */
    public static function header(string $version, string $php, string $env, string $os): void {
        $logoPath = defined('SKIM_ROOT') ? SKIM_ROOT . '/skim_ascii.txt' : null;
        $logo = [];
        if ($logoPath && file_exists($logoPath)) {
            $logoLines = explode("\n", file_get_contents($logoPath));
            while (count($logoLines) > 0 && trim($logoLines[0]) === '') {
                array_shift($logoLines);
            }
            while (count($logoLines) > 0 && trim($logoLines[count($logoLines) - 1]) === '') {
                array_pop($logoLines);
            }
            $logo = $logoLines;
        }

        if (empty($logo)) {
            $logo = [
                '  ___  _  _ ___ __  __  ',
                ' / __|| |/ /|_ _|  \/  |',
                ' \__ \|   <  | || |\/| |',
                ' |___/|_|\_\|___|_|  |_|'
            ];
        }

        $isPlain = self::$forcePlain || !self::isTty();

        if ($isPlain) {
            foreach ($logo as $line) {
                self::line($line);
            }
            self::line();
            self::line("SKIM Framework CLI (v{$version}) | PHP: {$php} | Env: {$env} | OS: {$os}");
            self::divider();
            return;
        }

        $purple = "\e[38;5;141m";
        foreach ($logo as $line) {
            self::line(self::color($purple, $line));
        }
        self::line();
        
        $metaStr = self::color("\e[1m", "SKIM Framework CLI") . " (v{$version}) | " .
                    self::color("\e[2m", "PHP:") . " {$php} | " .
                    self::color("\e[2m", "Env:") . " {$env} | " .
                    self::color("\e[2m", "OS:") . " {$os}";
        self::line($metaStr);
        self::divider();
    }

    /**
     * Prints an uppercase section heading in dim gray. #AI:section
     *
     * @param string $title Section title text.
     */
    public static function section(string $title): void {
        self::line(self::color("\e[38;5;245m", strtoupper($title)));
    }

    /**
     * Prints a numbered step indicator with status icon. #AI:step
     *
     * @param int    $n      Current step number.
     * @param int    $total  Total step count.
     * @param string $msg    Step description.
     * @param string $status One of 'running', 'success', 'error'.
     */
    public static function step(int $n, int $total, string $msg, string $status = 'running'): void {
        $indicator = match ($status) {
            'success' => self::color("\e[32m", "✔"),
            'error'   => self::color("\e[31m", "✖"),
            default   => self::color("\e[36m", "●"),
        };
        self::line("{$indicator} " . self::color("\e[2m", "[{$n}/{$total}]") . " {$msg}");
    }

    /**
     * Renders a boxed error message with Unicode or plain borders. #AI:errorBox
     *
     * Auto-detects TTY for Unicode box-drawing characters, falls back to
     * +---+ borders for piped output. Truncates lines wider than terminal.
     *
     * @param string $title Error title displayed in the box header.
     * @param string $body  Error body. When empty, $title is used as the body.
     */
    public static function errorBox(string $title, string $body = ''): void {
        $isPlain = self::$forcePlain || !self::isTty();
        
        $msg = $body !== '' ? $body : $title;
        $hdr = $body !== '' ? $title : 'Error';
        
        $lines = explode("\n", $msg);
        $maxLineLen = 0;
        foreach ($lines as $line) {
            $maxLineLen = max($maxLineLen, mb_strlen($line));
        }
        
        $outerWidth = max($maxLineLen + 6, mb_strlen($hdr) + 8);
        
        $cols = 80;
        if (self::isTty()) {
            $cols = (int)(shell_exec('tput cols 2>/dev/null') ?: 80);
        }
        $outerWidth = min($outerWidth, $cols - 4);
        if ($outerWidth < 20) {
            $outerWidth = 20;
        }
        
        $dashCount = $outerWidth - mb_strlen($hdr) - 5;
        if ($dashCount < 2) {
            $dashCount = 2;
        }
        
        $topBorder = ($isPlain ? '+- ' : '╭─ ') . self::color("\e[31m", $hdr) . " " . str_repeat($isPlain ? '-' : '─', $dashCount) . ($isPlain ? '+' : '╮');
        self::line($topBorder);
        
        foreach ($lines as $line) {
            $innerWidth = $outerWidth - 6;
            if (mb_strlen($line) > $innerWidth) {
                $line = mb_substr($line, 0, $innerWidth);
            }
            $padding = str_repeat(' ', $innerWidth - mb_strlen($line));
            self::line(($isPlain ? '|  ' : '│  ') . $line . $padding . ($isPlain ? '  |' : '  │'));
        }
        
        $bottomBorder = ($isPlain ? '+-' : '╰─') . str_repeat($isPlain ? '-' : '─', $outerWidth - 4) . ($isPlain ? '-+' : '─╯');
        self::line($bottomBorder);
    }

    /**
     * Suggests the closest matching command using Levenshtein distance. #AI:didYouMean
     *
     * Only prints a suggestion when a candidate is within edit distance 3.
     *
     * @param string $input      Mistyped command name.
     * @param array  $candidates List of valid command names to compare against.
     */
    public static function didYouMean(string $input, array $candidates): void {
        $best = null;
        $bestDist = 4;
        foreach ($candidates as $candidate) {
            $dist = levenshtein($input, $candidate);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $candidate;
            }
        }
        if ($best !== null) {
            self::line("Did you mean:  " . self::color("\e[33m", $best) . "?");
        }
    }

    /**
     * Prints elapsed time since $start with a green checkmark. #AI:duration
     *
     * @param float $start microtime(true) value captured at operation start.
     */
    public static function duration(float $start): void {
        $diff = round(microtime(true) - $start, 2);
        self::line(self::color("\e[32m", "✔ Done in {$diff}s"));
    }

    /**
     * Prints a horizontal divider spanning the terminal width. #AI:divider
     *
     * Uses Unicode box-drawing character on TTY, ASCII dash otherwise.
     *
     * @param string $char Character to repeat (default: Unicode horizontal line).
     */
    public static function divider(string $char = '─'): void {
        $cols = 80;
        if (self::isTty()) {
            $cols = (int)(shell_exec('tput cols 2>/dev/null') ?: 80);
        }
        if (self::$forcePlain || !self::isTty()) {
            $char = '-';
        }
        self::line(str_repeat($char, $cols));
    }

    /**
     * Prints one or more blank lines. #AI:newline
     *
     * @param int $n Number of blank lines to print.
     */
    public static function newline(int $n = 1): void {
        echo str_repeat("\n", $n);
    }

    /**
     * Wraps text in ANSI color codes when output is a TTY. #AI:color
     *
     * @param string $code ANSI escape sequence (e.g. "\e[36m").
     * @param string $text Text to colorize.
     */
    private static function color(string $code, string $text): string {
        if (self::$forcePlain || !self::isTty()) {
            return $text;
        }
        return $code . $text . "\e[0m";
    }

    /**
     * Returns true when stdout is connected to an interactive terminal. #AI:isTty
     */
    public static function isTty(): bool {
        if (function_exists('stream_isatty') && @stream_isatty(STDOUT)) {
            return true;
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}

#AI:class
#AI symbol: Skim\Cli\Cli
#AI source_path: src/Cli/Cli.php
#AI title: cli
#AI description: ANSI-colored CLI output helper with TTY detection, interactive prompts, tables, and styled error boxes.
#AI role: CLI output and interaction helper
#AI layer: cli
#AI badges: [cli; output; ansi; tty-aware; interactive]
#AI intro: `cli` is the central terminal output helper for the SKIM CLI. It provides colored output methods, interactive prompts (ask, confirm, choice), ASCII tables, progress bars, and styled error boxes. All ANSI escape codes are suppressed when stdout is not a TTY or when forcePlain(true) is set.
#AI lifecycle: static utility — no instantiation needed, methods called directly
#AI fallback: all output degrades gracefully to plain text when not connected to a TTY
#AI test_seam: forcePlain(true) to suppress ANSI codes for deterministic test assertions
#AI invariants: [ANSI codes emitted only on TTY; forcePlain(true) overrides TTY detection; error() writes to STDERR; all other methods write to STDOUT]
#AI core_behaviors: [TTY detection via stream_isatty/posix_isatty; Colored output with automatic reset; Interactive prompts read from STDIN; ASCII tables with auto-fitted column widths; Error boxes with Unicode or plain borders]
#AI owns: static forcePlain flag
#AI entry_points: [line; info; success; warn; error; muted; bold; ask; confirm; choice; table; header; errorBox; didYouMean]
#AI config_reads: []
#AI non_goals: [Does not handle input parsing (see ArgvParser); Does not manage process lifecycle (see kernel); Does not provide full-screen TUI (see InteractiveMenu)]
#AI side_effects: [Writes to STDOUT or STDERR; Reads from STDIN for interactive prompts; forcePlain() mutates static state]
#AI flow: Cli::method() -> isTty() check -> color() wraps text -> echo/fwrite output
#AI lifecycle_steps: [caller invokes Cli::method(); -> isTty() checks stdout; -> color() applies ANSI if TTY; -> echo or fwrite outputs text]
#AI section_order: [Output; Interactive Prompts; Progress and Tables; Display Components; Architecture]
#AI architectural_notes: Static utility class with no dependencies on framework config or container. TTY detection ensures CI logs and piped output remain clean plain text.

#AI:forcePlain
#AI group: Architecture
#AI frequency: low
#AI signature: public static function forcePlain(bool $plain): void
#AI contract: Forces plain-text output regardless of TTY detection. Used by --no-ansi flag and in tests.
#AI param_details: [{name: $plain | type: bool | required: true | desc: True to suppress all ANSI escape codes.}]
#AI side_effects: Mutates static forcePlain flag.

#AI:line
#AI group: Output
#AI frequency: high
#AI signature: public static function line(string $msg = ''): void
#AI contract: Prints a line to stdout followed by a newline. Empty string prints a blank line.
#AI param_details: [{name: $msg | type: string | required: false | desc: Text to print. Defaults to empty string for blank lines.}]

#AI:info
#AI group: Output
#AI frequency: high
#AI signature: public static function info(string $msg): void
#AI contract: Prints a cyan informational message to stdout.
#AI param_details: [{name: $msg | type: string | required: true | desc: Informational message text.}]

#AI:success
#AI group: Output
#AI frequency: high
#AI signature: public static function success(string $msg): void
#AI contract: Prints a green success message with checkmark prefix to stdout.
#AI param_details: [{name: $msg | type: string | required: true | desc: Success message text.}]

#AI:warn
#AI group: Output
#AI frequency: medium
#AI signature: public static function warn(string $msg): void
#AI contract: Prints a yellow warning message with warning prefix to stdout.
#AI param_details: [{name: $msg | type: string | required: true | desc: Warning message text.}]

#AI:error
#AI group: Output
#AI frequency: medium
#AI signature: public static function error(string $msg): void
#AI contract: Prints a red error message to STDERR with cross prefix.
#AI param_details: [{name: $msg | type: string | required: true | desc: Error message text.}]
#AI side_effects: Writes to STDERR, not STDOUT.

#AI:muted
#AI group: Output
#AI frequency: medium
#AI signature: public static function muted(string $msg): void
#AI contract: Prints a gray dimmed message for secondary information.
#AI param_details: [{name: $msg | type: string | required: true | desc: Muted text.}]

#AI:bold
#AI group: Output
#AI frequency: low
#AI signature: public static function bold(string $msg): void
#AI contract: Prints bold text to stdout.
#AI param_details: [{name: $msg | type: string | required: true | desc: Bold text.}]

#AI:ask
#AI group: Interactive Prompts
#AI frequency: medium
#AI signature: public static function ask(string $question, string $default = ''): string
#AI contract: Prompts user for text input via STDIN. Shows $default in brackets and returns it when user presses Enter without typing.
#AI param_details: [{name: $question | type: string | required: true | desc: Prompt text displayed to the user.}; {name: $default | type: string | required: false | desc: Value returned when user presses Enter without typing.}]
#AI return_detail: {type: string | desc: Trimmed user input, or $default if empty.}

#AI:confirm
#AI group: Interactive Prompts
#AI frequency: medium
#AI signature: public static function confirm(string $question, bool $default = true): bool
#AI contract: Prompts user with y/n question. Returns true for y/yes, false for n/no. $default determines what Enter alone returns.
#AI param_details: [{name: $question | type: string | required: true | desc: Prompt text.}; {name: $default | type: bool | required: false | desc: Value returned when user presses Enter without typing.}]
#AI return_detail: {type: bool | desc: True for yes, false for no.}

#AI:choice
#AI group: Interactive Prompts
#AI frequency: medium
#AI signature: public static function choice(string $question, array $options, mixed $default = null): mixed
#AI contract: Presents a numbered list of options and returns the selected item. Re-prompts on invalid input until a valid selection is made.
#AI param_details: [{name: $question | type: string | required: true | desc: Prompt heading displayed above the options.}; {name: $options | type: array | required: true | desc: Indexed array of selectable items.}; {name: $default | type: mixed | required: false | desc: Returned when user presses Enter without typing.}]
#AI return_detail: {type: mixed | desc: The selected option value, or $default.}

#AI:progressBar
#AI group: Progress and Tables
#AI frequency: medium
#AI signature: public static function progressBar(int $total = 0, string $label = ''): ProgressBar
#AI contract: Creates a progress bar instance. Pass total=0 for indeterminate spinner mode.
#AI param_details: [{name: $total | type: int | required: false | desc: Total steps. 0 for indeterminate spinner mode.}; {name: $label | type: string | required: false | desc: Text displayed alongside the bar.}]
#AI return_detail: {type: ProgressBar | desc: Progress bar instance to call advance() and finish() on.}

#AI:table
#AI group: Progress and Tables
#AI frequency: medium
#AI signature: public static function table(array $headers, array $rows): void
#AI contract: Renders rows as an ASCII table with auto-fitted column widths.
#AI param_details: [{name: $headers | type: array | required: true | desc: Column header labels.}; {name: $rows | type: array | required: true | desc: Array of row arrays, values aligned to headers by index.}]

#AI:header
#AI group: Display Components
#AI frequency: low
#AI signature: public static function header(string $version, string $php, string $env, string $os): void
#AI contract: Prints the SKIM ASCII logo and environment metadata banner. Loads logo from skim_ascii.txt if present, falls back to built-in art.
#AI param_details: [{name: $version | type: string | required: true | desc: Framework version string.}; {name: $php | type: string | required: true | desc: PHP version string.}; {name: $env | type: string | required: true | desc: Application environment name.}; {name: $os | type: string | required: true | desc: Operating system identifier.}]

#AI:section
#AI group: Display Components
#AI frequency: medium
#AI signature: public static function section(string $title): void
#AI contract: Prints an uppercase section heading in dim gray.
#AI param_details: [{name: $title | type: string | required: true | desc: Section title text.}]

#AI:step
#AI group: Display Components
#AI frequency: medium
#AI signature: public static function step(int $n, int $total, string $msg, string $status = 'running'): void
#AI contract: Prints a numbered step indicator with colored status icon (running=blue, success=green, error=red).
#AI param_details: [{name: $n | type: int | required: true | desc: Current step number.}; {name: $total | type: int | required: true | desc: Total step count.}; {name: $msg | type: string | required: true | desc: Step description.}; {name: $status | type: string | required: false | desc: One of 'running', 'success', 'error'.}]

#AI:errorBox
#AI group: Display Components
#AI frequency: low
#AI signature: public static function errorBox(string $title, string $body = ''): void
#AI contract: Renders a boxed error message with Unicode box-drawing on TTY or plain ASCII borders otherwise. Truncates lines wider than terminal width.
#AI param_details: [{name: $title | type: string | required: true | desc: Error title displayed in the box header.}; {name: $body | type: string | required: false | desc: Error body. When empty, $title is used as the body.}]

#AI:didYouMean
#AI group: Display Components
#AI frequency: low
#AI signature: public static function didYouMean(string $input, array $candidates): void
#AI contract: Suggests the closest matching command using Levenshtein distance. Only prints when a candidate is within edit distance 3.
#AI param_details: [{name: $input | type: string | required: true | desc: Mistyped command name.}; {name: $candidates | type: array | required: true | desc: List of valid command names to compare against.}]

#AI:duration
#AI group: Display Components
#AI frequency: low
#AI signature: public static function duration(float $start): void
#AI contract: Prints elapsed time since $start with a green checkmark.
#AI param_details: [{name: $start | type: float | required: true | desc: microtime(true) value captured at operation start.}]

#AI:divider
#AI group: Display Components
#AI frequency: low
#AI signature: public static function divider(string $char = '─'): void
#AI contract: Prints a horizontal divider spanning the terminal width. Uses Unicode on TTY, ASCII dash otherwise.
#AI param_details: [{name: $char | type: string | required: false | desc: Character to repeat. Defaults to Unicode horizontal line.}]

#AI:newline
#AI group: Output
#AI frequency: low
#AI signature: public static function newline(int $n = 1): void
#AI contract: Prints one or more blank lines.
#AI param_details: [{name: $n | type: int | required: false | desc: Number of blank lines to print.}]

#AI:color
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function color(string $code, string $text): string
#AI contract: Wraps text in ANSI color codes when output is a TTY, returns plain text otherwise.

#AI:isTty
#AI group: Architecture
#AI frequency: internal
#AI signature: public static function isTty(): bool
#AI contract: Returns true when stdout is connected to an interactive terminal. Uses stream_isatty with posix_isatty fallback.
#AI notes: Public because other CLI classes (InteractiveMenu, ProgressBar) need TTY detection.
