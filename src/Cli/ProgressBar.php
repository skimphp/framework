<?php declare(strict_types=1);

namespace Skim\Cli;

/**
 * Terminal progress bar with TTY-aware rendering and spinner mode for unknown totals.
 *
 * Use when CLI commands need to show progress for long-running operations.
 * Renders a filled bar with percentage when total is known, or an animated
 * braille spinner when total is 0 (indeterminate). Degrades to plain text
 * when not connected to a TTY.
 *
 * Example:
 *   $bar = new ProgressBar(total: 100, label: 'Processing');
 *   foreach ($items as $item) { $bar->advance(); }
 *   $bar->finish('All items processed');
 *
 * Testing: Not designed for automated testing — writes directly to stdout.
 *
 * #AI:class
 */
final class ProgressBar {
    private int $current = 0;
    private float $startedAt;
    private int $barWidth = 20;
    private int $spinnerIndex = 0;
    private array $spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    /**
     * Creates and immediately renders the progress bar. #AI:__construct
     *
     * @param int    $total Total steps. 0 for indeterminate spinner mode.
     * @param string $label Text displayed alongside the bar.
     */
    public function __construct(
        private readonly int $total = 0,
        private readonly string $label = '',
    ) {
        $this->startedAt = microtime(true);
        $this->render();
    }

    /**
     * Increments the progress counter and re-renders the bar. #AI:advance
     *
     * Clamps at $total when known. Cycles the spinner frame in indeterminate mode.
     *
     * @param int $step Number of steps to advance (default 1).
     */
    public function advance(int $step = 1): void {
        $this->current += $step;
        if ($this->total > 0 && $this->current > $this->total) {
            $this->current = $this->total;
        }
        $this->spinnerIndex = ($this->spinnerIndex + 1) % count($this->spinner);
        $this->render();
    }

    /**
     * Completes the bar and prints elapsed time with a success message. #AI:finish
     *
     * @param string $msg Completion message displayed after the bar.
     */
    public function finish(string $msg = 'Done'): void {
        if ($this->total > 0) {
            $this->current = $this->total;
            $this->render();
        }
        echo "\n\n";
        
        $elapsed = round(microtime(true) - $this->startedAt, 2);
        $isPlain = !self::isTty();
        
        if ($isPlain) {
            echo "✔ {$msg} in {$elapsed}s\n";
        } else {
            echo "\e[32m✔ {$msg} in {$elapsed}s\e[0m\n";
        }
    }

    /**
     * Renders the current state of the bar to stdout using carriage return. #AI:render
     */
    private function render(): void {
        $isPlain = !self::isTty();
        if ($isPlain) {
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
            $filled = (int) round($pct * $this->barWidth);
            $empty = $this->barWidth - $filled;
            $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
            
            $pctText = sprintf("%d%%", (int)($pct * 100));
            $countText = sprintf("(%d/%d)", $this->current, $this->total);
            $labelText = $this->label !== '' ? "  " . $this->label : '';
            
            printf("\r[%s] %-4s  %-8s%s", $bar, $pctText, $countText, $labelText);
        } else {
            $spinChar = $this->spinner[$this->spinnerIndex];
            $labelText = $this->label !== '' ? " " . $this->label : '';
            printf("\r\e[36m%s\e[0m%s (%d)", $spinChar, $labelText, $this->current);
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
#AI symbol: Skim\Cli\ProgressBar
#AI source_path: src/Cli/ProgressBar.php
#AI title: ProgressBar
#AI description: Terminal progress bar with TTY-aware rendering, percentage display, and braille spinner for unknown totals.
#AI role: CLI progress indicator
#AI layer: cli
#AI badges: [cli; progress; tty-aware; spinner]
#AI intro: `ProgressBar` provides a terminal progress indicator that renders a filled bar with percentage when total is known, or an animated braille spinner for indeterminate operations. Degrades to plain text on non-TTY output.
#AI lifecycle: instantiated with total and label, advance() called per step, finish() completes the bar
#AI fallback: plain text percentage on non-TTY output
#AI test_seam: not designed for automated testing — writes directly to stdout
#AI invariants: [current is clamped at total; spinner cycles through 10 braille frames; render uses carriage return for in-place updates]
#AI core_behaviors: [Filled bar with █ and ░ characters; Percentage and count display; Braille spinner for indeterminate mode; Elapsed time on finish; TTY detection for ANSI vs plain output]
#AI owns: current count, startedAt timestamp, spinner state
#AI entry_points: [advance; finish]
#AI config_reads: []
#AI non_goals: [Does not support multiple concurrent bars; Does not estimate remaining time; Does not support custom bar characters]
#AI side_effects: [writes to stdout via printf with carriage return]
#AI flow: new ProgressBar(total, label) -> render() -> advance() loop -> finish(msg) -> print elapsed
#AI lifecycle_steps: [__construct(total, label); -> render() initial frame; -> advance(step) per iteration; -> render() updated frame; -> finish(msg); -> print elapsed time]
#AI section_order: [Progress Control; Rendering; Architecture]
#AI architectural_notes: Uses carriage return (\r) for in-place terminal updates. The bar is rendered immediately on construction so the user sees progress from the start.

#AI:__construct
#AI group: Progress Control
#AI frequency: medium
#AI signature: public function __construct(int $total = 0, string $label = '')
#AI contract: Creates and immediately renders the progress bar. Total of 0 activates indeterminate spinner mode.
#AI param_details: [{name: $total | type: int | required: false | desc: Total steps. 0 for indeterminate spinner mode.}; {name: $label | type: string | required: false | desc: Text displayed alongside the bar.}]

#AI:advance
#AI group: Progress Control
#AI frequency: high
#AI signature: public function advance(int $step = 1): void
#AI contract: Increments the progress counter by $step and re-renders. Clamps at total when known.
#AI param_details: [{name: $step | type: int | required: false | desc: Number of steps to advance. Default 1.}]

#AI:finish
#AI group: Progress Control
#AI frequency: medium
#AI signature: public function finish(string $msg = 'Done'): void
#AI contract: Completes the bar, prints a newline, and displays elapsed time with a green success message.
#AI param_details: [{name: $msg | type: string | required: false | desc: Completion message. Default 'Done'.}]

#AI:render
#AI group: Rendering
#AI frequency: internal
#AI signature: private function render(): void
#AI contract: Renders the current state to stdout using carriage return for in-place updates. Switches between bar and spinner based on total.

#AI:isTty
#AI group: Architecture
#AI frequency: internal
#AI signature: private static function isTty(): bool
#AI contract: Returns true when stdout is connected to an interactive terminal.
