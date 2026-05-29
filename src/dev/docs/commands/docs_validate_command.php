<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;
use skim\dev\docs\extractor\project_scanner;

/**
 * Reports public methods missing @ai.* annotation coverage. #AI:class
 *
 * Use in CI or pre-commit hooks to enforce annotation discipline.
 * Exits non-zero when coverage falls below the configured threshold
 * (default 80%, configurable via docs.validate.min_coverage).
 *
 * Example:
 *   php skim docs:validate
 *   # In .git/hooks/pre-push: php skim docs:validate || exit 1
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
class docs_validate_command extends command {
    /**
     * Scans source, reports unannotated methods, exits 1 if below threshold. #AI:handle
     *
     * Threshold is read from config('docs.validate.min_coverage', 0.8).
     * A method is considered annotated when it has at least one @ai.* tag
     * (contracts, invariants, non_goals, side_effects, or any other field).
     *
     * @return int 0 if coverage meets threshold, 1 otherwise.
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

#AI:class
#AI symbol: skim\dev\docs\commands\docs_validate_command
#AI source_path: src/dev/docs/commands/docs_validate_command.php
#AI title: docs_validate_command
#AI description: CLI command that reports @ai.* annotation coverage and fails the build when below the configured threshold.
#AI role: CI annotation gate
#AI layer: dev
#AI badges: [cli; docs; validation; ci]
#AI intro: `docs_validate_command` scans all source files, counts public methods with at least one @ai.* annotation, and compares the ratio against a configurable threshold. Use in CI pipelines or pre-commit hooks to prevent annotation regressions.
#AI lifecycle: instantiated by CLI router, runs synchronously
#AI fallback: none — returns 1 on scan failure or below-threshold coverage
#AI test_seam: instantiate directly; no static state
#AI invariants: [a method is annotated if it has any @ai.* tag; threshold defaults to 0.8 (80%); scan failure returns 1]
#AI core_behaviors: [Scans all configured source paths via project_scanner; Prints a table of unannotated methods; Compares coverage ratio against threshold]
#AI owns: project_scanner instance
#AI entry_points: [handle]
#AI config_reads: [docs.validate.min_coverage; docs.scan_paths]
#AI non_goals: [Does not fix missing annotations; Does not generate docs]
#AI side_effects: [prints table of unannotated methods to stdout]
#AI flow: handle() -> project_scanner.scan() -> count annotated vs total -> compare threshold -> exit code
#AI lifecycle_steps: [handle(); -> project_scanner.scan(); -> iterate methods; -> count annotated; -> compare threshold; -> exit 0 or 1]
#AI section_order: [Pipeline; Architecture]
#AI architectural_notes: Designed for CI gate usage — non-zero exit code blocks merges when coverage drops.

#AI:handle
#AI group: Pipeline
#AI frequency: high
#AI signature: public function handle(): int
#AI contract: Scans all source files, reports unannotated public methods in a table, and exits 1 when coverage is below the configured threshold.
#AI return_detail: {type: int | desc: 0 if coverage meets or exceeds threshold, 1 if below threshold or scan fails.}
#AI side_effects: [prints coverage report and missing-methods table to stdout]
