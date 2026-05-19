<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;
use skim\dev\docs\extractor\project_scanner;

// Reports public methods missing all @ai.* tags.
// Exits non-zero when coverage is below the configured threshold.
// Usage: php skim docs:validate
class docs_validate_command extends command {
    /**
     * @ai-contract scans source, reports unannotated public methods, exits 1 if below threshold
     * @ai-contract threshold read from config/docs.php validate.min_coverage (default 0.8)
     * @ai-contract exits 0 if coverage meets or exceeds threshold
     */
    public function handle(): int {
        $threshold = (float) config('docs.validate.min_coverage', 0.8);

        $this->info('Scanning for @ai.* coverage…');
        $scanner = new project_scanner();

        try {
            $classes = $scanner->scan();
        } catch (\Throwable $e) {
            $this->error('Scan failed: ' . $e->getMessage());
            return 1;
        }

        $total_methods    = 0;
        $annotated        = 0;
        $missing_rows     = [];

        foreach ($classes as $class) {
            foreach ($class->methods as $method) {
                $total_methods++;
                $has_annotation = $method->contracts    !== []
                    || $method->invariants   !== []
                    || $method->non_goals    !== []
                    || $method->side_effects !== [];
                if ($has_annotation) {
                    $annotated++;
                } else {
                    $missing_rows[] = [
                        'class'  => $class->class_name,
                        'method' => $method->name,
                        'file'   => str_replace(base_path() . '/', '', $class->file),
                    ];
                }
            }
        }

        if ($missing_rows !== []) {
            $this->warn('Methods missing @ai.* annotations:');
            \skim\cli\cli::table(['class', 'method', 'file'], $missing_rows);
            $this->line();
        }

        $coverage  = $total_methods > 0 ? $annotated / $total_methods : 1.0;
        $pct       = (int) round($coverage * 100);
        $threshold_pct = (int) round($threshold * 100);

        $this->line("Coverage: {$annotated}/{$total_methods} methods annotated ({$pct}%)");
        $this->line("Threshold: {$threshold_pct}%");

        if ($coverage < $threshold) {
            $this->error("Coverage {$pct}% is below threshold {$threshold_pct}% — failing build.");
            return 1;
        }

        $this->success("Coverage {$pct}% meets threshold {$threshold_pct}%.");
        return 0;
    }
}
