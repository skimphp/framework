<?php declare(strict_types=1);

namespace skim\cli;

// ANSI-colored CLI output helpers.
// Only emit escape codes when the output is a real TTY (not piped/redirected).
// Reason: piped output (grep, CI logs) must be plain text — escape codes corrupt it.
final class cli {
    private static bool $force_plain = false;

    public static function force_plain(bool $plain): void {
        self::$force_plain = $plain;
    }

    // --- output ---

    public static function line(string $msg = ''): void {
        echo $msg . "\n";
    }

    public static function info(string $msg): void {
        echo self::color("\e[36m", $msg) . "\n";   // cyan
    }

    public static function success(string $msg): void {
        echo self::color("\e[32m", "✔ " . $msg) . "\n";   // green
    }

    public static function warn(string $msg): void {
        echo self::color("\e[33m", "⚠ " . $msg) . "\n";   // yellow
    }

    public static function error(string $msg): void {
        fwrite(STDERR, self::color("\e[31m", "✖ " . $msg) . "\n");   // red
    }

    public static function muted(string $msg): void {
        echo self::color("\e[90m", $msg) . "\n";   // gray
    }

    public static function bold(string $msg): void {
        echo self::color("\e[1m", $msg) . "\n";
    }

    // --- interactive ---

    /**
     * @ai-contract prompts user for text input, returns trimmed string
     * @ai-contract $default shown in brackets — returned when user presses Enter with no input
     */
    public static function ask(string $question, string $default = ''): string {
        $hint = $default !== '' ? " [{$default}]" : '';
        echo self::color("\e[36m", "? " . $question . $hint) . " ";
        $input = trim((string) fgets(STDIN));
        return $input !== '' ? $input : $default;
    }

    /**
     * @ai-contract prompts user with y/n — returns true for y/yes, false for n/no
     * @ai-contract $default determines what Enter alone returns
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
     * @ai-contract presents numbered list of options, returns selected item
     * @ai-contract invalid input re-prompts
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

    // --- progress ---

    /**
     * @ai-contract starts a progress bar — call advance() to increment, finish() to complete
     */
    public static function progress_bar(int $total, string $label = ''): progress_bar {
        return new progress_bar($total, $label);
    }

    // --- table ---

    /**
     * @ai-contract renders $rows as ASCII table with $headers as column names
     * @ai-contract column widths auto-fit to longest value
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

    // --- internals ---

    private static function color(string $code, string $text): string {
        if (self::$force_plain || !self::is_tty()) {
            return $text;
        }
        return $code . $text . "\e[0m";
    }

    private static function is_tty(): bool {
        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }
}
