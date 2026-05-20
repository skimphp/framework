<?php declare(strict_types=1);
// Modified: Added custom styling methods for CLI header, sections, steps, error boxes, suggestions, and dividers.

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
    public static function progress_bar(int $total = 0, string $label = ''): progress_bar {
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


    public static function header(string $version, string $php, string $env, string $os): void {
        $logo_path = defined('SKIM_ROOT') ? SKIM_ROOT . '/skim_ascii.txt' : null;
        $logo = [];
        if ($logo_path && file_exists($logo_path)) {
            $logo_lines = explode("\n", file_get_contents($logo_path));
            while (count($logo_lines) > 0 && trim($logo_lines[0]) === '') {
                array_shift($logo_lines);
            }
            while (count($logo_lines) > 0 && trim($logo_lines[count($logo_lines) - 1]) === '') {
                array_pop($logo_lines);
            }
            $logo = $logo_lines;
        }

        if (empty($logo)) {
            $logo = [
                '  ___  _  _ ___ __  __  ',
                ' / __|| |/ /|_ _|  \/  |',
                ' \__ \|   <  | || |\/| |',
                ' |___/|_|\_\|___|_|  |_|'
            ];
        }

        $is_plain = self::$force_plain || !self::is_tty();

        if ($is_plain) {
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
        
        $meta_str = self::color("\e[1m", "SKIM Framework CLI") . " (v{$version}) | " .
                    self::color("\e[2m", "PHP:") . " {$php} | " .
                    self::color("\e[2m", "Env:") . " {$env} | " .
                    self::color("\e[2m", "OS:") . " {$os}";
        self::line($meta_str);
        self::divider();
    }

    public static function section(string $title): void {
        self::line(self::color("\e[38;5;245m", strtoupper($title)));
    }

    public static function step(int $n, int $total, string $msg, string $status = 'running'): void {
        $indicator = match ($status) {
            'success' => self::color("\e[32m", "✔"),
            'error'   => self::color("\e[31m", "✖"),
            default   => self::color("\e[36m", "●"),
        };
        self::line("{$indicator} " . self::color("\e[2m", "[{$n}/{$total}]") . " {$msg}");
    }

    public static function error_box(string $title, string $body = ''): void {
        $is_plain = self::$force_plain || !self::is_tty();
        
        $msg = $body !== '' ? $body : $title;
        $hdr = $body !== '' ? $title : 'Error';
        
        $lines = explode("\n", $msg);
        $max_line_len = 0;
        foreach ($lines as $line) {
            $max_line_len = max($max_line_len, mb_strlen($line));
        }
        
        $outer_width = max($max_line_len + 6, mb_strlen($hdr) + 8);
        
        $cols = 80;
        if (self::is_tty()) {
            $cols = (int)(shell_exec('tput cols 2>/dev/null') ?: 80);
        }
        $outer_width = min($outer_width, $cols - 4);
        if ($outer_width < 20) {
            $outer_width = 20;
        }
        
        $dash_count = $outer_width - mb_strlen($hdr) - 5;
        if ($dash_count < 2) {
            $dash_count = 2;
        }
        
        $top_border = ($is_plain ? '+- ' : '╭─ ') . self::color("\e[31m", $hdr) . " " . str_repeat($is_plain ? '-' : '─', $dash_count) . ($is_plain ? '+' : '╮');
        self::line($top_border);
        
        foreach ($lines as $line) {
            $inner_width = $outer_width - 6;
            if (mb_strlen($line) > $inner_width) {
                $line = mb_substr($line, 0, $inner_width);
            }
            $padding = str_repeat(' ', $inner_width - mb_strlen($line));
            self::line(($is_plain ? '|  ' : '│  ') . $line . $padding . ($is_plain ? '  |' : '  │'));
        }
        
        $bottom_border = ($is_plain ? '+-' : '╰─') . str_repeat($is_plain ? '-' : '─', $outer_width - 4) . ($is_plain ? '-+' : '─╯');
        self::line($bottom_border);
    }

    public static function did_you_mean(string $input, array $candidates): void {
        $best = null;
        $best_dist = 4;
        foreach ($candidates as $candidate) {
            $dist = levenshtein($input, $candidate);
            if ($dist < $best_dist) {
                $best_dist = $dist;
                $best = $candidate;
            }
        }
        if ($best !== null) {
            self::line("Did you mean:  " . self::color("\e[33m", $best) . "?");
        }
    }

    public static function duration(float $start): void {
        $diff = round(microtime(true) - $start, 2);
        self::line(self::color("\e[32m", "✔ Done in {$diff}s"));
    }

    public static function divider(string $char = '─'): void {
        $cols = 80;
        if (self::is_tty()) {
            $cols = (int)(shell_exec('tput cols 2>/dev/null') ?: 80);
        }
        if (self::$force_plain || !self::is_tty()) {
            $char = '-';
        }
        self::line(str_repeat($char, $cols));
    }

    public static function newline(int $n = 1): void {
        echo str_repeat("\n", $n);
    }

    // --- internals ---


    private static function color(string $code, string $text): string {
        if (self::$force_plain || !self::is_tty()) {
            return $text;
        }
        return $code . $text . "\e[0m";
    }

    public static function is_tty(): bool {
        if (function_exists('stream_isatty') && @stream_isatty(STDOUT)) {
            return true;
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
