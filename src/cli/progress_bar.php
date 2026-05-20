<?php declare(strict_types=1);
// Rewritten: Added custom progress bar styling, spinner support for unknown totals, and dynamic completion output.

namespace skim\cli;

final class progress_bar {
    private int $current = 0;
    private float $started_at;
    private int $bar_width = 20;
    private int $spinner_index = 0;
    private array $spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    public function __construct(
        private readonly int $total = 0,
        private readonly string $label = '',
    ) {
        $this->started_at = microtime(true);
        $this->render();
    }

    public function advance(int $step = 1): void {
        $this->current += $step;
        if ($this->total > 0 && $this->current > $this->total) {
            $this->current = $this->total;
        }
        $this->spinner_index = ($this->spinner_index + 1) % count($this->spinner);
        $this->render();
    }

    public function finish(string $msg = 'Done'): void {
        if ($this->total > 0) {
            $this->current = $this->total;
            $this->render();
        }
        echo "\n\n";
        
        $elapsed = round(microtime(true) - $this->started_at, 2);
        $is_plain = !self::is_tty();
        
        if ($is_plain) {
            echo "✔ {$msg} in {$elapsed}s\n";
        } else {
            echo "\e[32m✔ {$msg} in {$elapsed}s\e[0m\n";
        }
    }

    private function render(): void {
        $is_plain = !self::is_tty();
        if ($is_plain) {
            if ($this->total > 0) {
                $pct = (int) (($this->current / $this->total) * 100);
                printf("\r[%d%%] %s (%d/%d)", $pct, $this->label, $this->current, $this->total);
            } else {
                printf("\r%s (%d)", $this->label, $this->current);
            }
            return;
        }

        if ($this->total > 0) {
            $pct = $this->current / $this->total;
            $filled = (int) round($pct * $this->bar_width);
            $empty = $this->bar_width - $filled;
            $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
            
            $pct_text = sprintf("%d%%", (int)($pct * 100));
            $count_text = sprintf("(%d/%d)", $this->current, $this->total);
            $label_text = $this->label !== '' ? "  " . $this->label : '';
            
            printf("\r[%s] %-4s  %-8s%s", $bar, $pct_text, $count_text, $label_text);
        } else {
            $spin_char = $this->spinner[$this->spinner_index];
            $label_text = $this->label !== '' ? " " . $this->label : '';
            printf("\r\e[36m%s\e[0m%s (%d)", $spin_char, $label_text, $this->current);
        }
    }

    private static function is_tty(): bool {
        if (function_exists('stream_isatty') && @stream_isatty(STDOUT)) {
            return true;
        }
        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
