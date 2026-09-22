<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Commands;

use Skim\Cli\Command;
use Skim\Dev\Docs\Value\DocsGenerationPaths;

/**
 * Umbrella command that runs docs:extract → docs:llm → docs:site in sequence. #AI:class
 *
 * Use when a full documentation rebuild is needed in one step.
 * Supports --watch flag to poll source paths every 2 seconds and rebuild on
 * mtime change. Stops the pipeline on the first non-zero exit code.
 *
 * Example:
 *   php skim docs                              # one-shot full build
 *   php skim docs --watch                      # rebuild on .php file change
 *   php skim docs --source=app --output=build  # custom paths
 *
 * Testing: Instantiate directly; no static state to reset.
 *
 * #AI:class
 */
class DocsCommand extends \Skim\Cli\Command {
    /**
     * Dispatches to watch mode or one-shot build based on --watch flag. #AI:handle
     *
     * @return int 0 on success, first non-zero exit code on failure.
     */
    public function handle(): int {
        $watch = (bool) $this->flag('watch', false);

        if ($watch) {
            return $this->watchMode();
        }

        return $this->build();
    }

    private function build(): int {
        foreach ([
            new \Skim\Dev\Docs\Commands\DocsExtractCommand(),
            new \Skim\Dev\Docs\Commands\DocsLlmCommand(),
            new \Skim\Dev\Docs\Commands\DocsSiteCommand(),
        ] as $cmd) {
            $cmd->setInput($this->args, $this->flags);
            $code = $cmd->handle();
            if ($code !== 0) {
                return $code;
            }
        }
        return 0;
    }

    private function watchMode(): int {
        $this->info('Watch mode — press Ctrl+C to stop.');
        $scanPaths = \Skim\Dev\Docs\Value\DocsGenerationPaths::fromFlags($this->flags)->scanPaths();
        $lastHash  = $this->mtimeHash($scanPaths);

        $this->build();

        while (true) {
            sleep(2);
            $current = $this->mtimeHash($scanPaths);
            if ($current !== $lastHash) {
                $this->muted('Change detected — rebuilding…');
                $this->build();
                $lastHash = $current;
            }
        }
    }

    /**
     * Returns md5 of concatenated mtimes for change detection in watch mode. #AI:mtimeHash
     *
     * @param string[] $paths Directories to scan recursively for .php files.
     */
    private function mtimeHash(array $paths): string {
        $mtimes = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iter as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $mtimes[] = $file->getPathname() . ':' . $file->getMTime();
                }
            }
        }
        sort($mtimes);
        return md5(implode("\n", $mtimes));
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Commands\DocsCommand
#AI source_path: src/Dev/Docs/Commands/DocsCommand.php
#AI title: DocsCommand
#AI description: Umbrella CLI command that runs the full docs generation pipeline (extract → llm → site) with optional watch mode.
#AI role: CLI pipeline orchestrator
#AI layer: dev
#AI badges: [cli; docs; pipeline; watch-mode]
#AI intro: `DocsCommand` chains the three sub-commands (extract, llm, site) into a single invocation. With `--watch`, it polls source directories every 2 seconds and triggers a full rebuild when any .php file mtime changes.
#AI lifecycle: instantiated by CLI router, runs synchronously, exits with pipeline status
#AI fallback: none — stops on first sub-command failure
#AI test_seam: instantiate directly with setInput() to inject flags
#AI invariants: [pipeline stops on first non-zero exit; watch mode loops until Ctrl+C; mtimeHash is deterministic for identical file states]
#AI core_behaviors: [Runs extract → llm → site in sequence; Watch mode polls scanPaths every 2 seconds and rebuilds on mtime change]
#AI warnings: [Watch mode runs indefinitely until interrupted]
#AI owns: sub-command instances
#AI entry_points: [handle]
#AI config_reads: [docs.scan_paths; docs.output.json; docs.output.llm_md; docs.output.mdx_dir]
#AI non_goals: [Does not perform incremental builds; Does not parallelize sub-commands]
#AI side_effects: [writes llm.json, llm.md, and MDX files to configured output paths]
#AI flow: handle() -> --watch? -> watchMode() | build() -> [extract, llm, site]
#AI lifecycle_steps: [handle(); -> --watch flag?; -> build() runs extract → llm → site; -> watchMode() polls mtimeHash every 2s]
#AI section_order: [Pipeline; Watch Mode; Architecture]
#AI architectural_notes: Delegates all work to sub-commands; this class only orchestrates ordering and watch polling.

#AI:handle
#AI group: Pipeline
#AI frequency: high
#AI signature: public function handle(): int
#AI contract: Dispatches to watch mode when --watch flag is set, otherwise runs a one-shot full build pipeline.
#AI return_detail: {type: int | desc: 0 on success, first non-zero exit code from any sub-command on failure.}

#AI:mtimeHash
#AI group: Watch Mode
#AI frequency: internal
#AI signature: private function mtimeHash(array $paths): string
#AI contract: Computes an md5 hash of sorted file:mtimes pairs across all .php files in the given directories. Used to detect any file change between polling intervals.
#AI param_details: [{name: $paths | type: string[] | required: true | desc: Directories to scan recursively for .php files.}]
#AI return_detail: {type: string | desc: MD5 hash representing the current mtime state of all .php files.}
