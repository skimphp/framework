<?php declare(strict_types=1);

namespace skim\cli;

// Terminal progress bar. Overwrites the current line using \r.
// Uses ANSI escape codes only when running in a real TTY.
final class progress_bar {
    private int   $current = 0;
    private float $started_at;
    private int   $bar_width = 40;

    public function __construct(
        private readonly int    $total,
        private readonly string $label = '',
    ) {
        $this->started_at = microtime(true);
        $this->render();
    }

    /**
     * @ai-contract increments progress by $step and re-renders the bar
     */
    public function advance(int $step = 1): void {
        $this->current = min($this->total, $this->current + $step);
        $this->render();
    }

    /**
     * @ai-contract marks the bar as complete (100%) and moves to a new line
     */
    public function finish(): void {
        $this->current = $this->total;
        $this->render();
        echo "\n";
    }

    private function render(): void {
        $pct     = $this->total > 0 ? $this->current / $this->total : 0;
        $filled  = (int) round($pct * $this->bar_width);
        $empty   = $this->bar_width - $filled;
        $bar     = str_repeat('█', $filled) . str_repeat('░', $empty);
        $elapsed = round(microtime(true) - $this->started_at, 1);
        $label   = $this->label !== '' ? $this->label . ' ' : '';
        printf("\r%s[%s] %d/%d (%d%%) %.1fs", $label, $bar, $this->current, $this->total, (int)($pct * 100), $elapsed);
    }
}
