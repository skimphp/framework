<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Commands;

use Skim\Cli\Command;
use Skim\Dev\Docs\Emitter\JsonEmitter;
use Skim\Dev\Docs\Extractor\ProjectScanner;
use Skim\Dev\Docs\Value\DocsGenerationPaths;
use Skim\Ext\ExtRegistry;

/**
 * Scans all configured source paths and writes llm.json. #AI:class
 *
 * Use as the first step of the docs pipeline, or standalone to regenerate
 * the JSON source of truth. Reads scan paths from config/docs.php or
 * --source flag override. Enriches output with extension capability data.
 *
 * Example:
 *   php skim docs:extract
 *   php skim docs:extract --source=app --output=build/docs
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
class DocsExtractCommand extends \Skim\Cli\Command {
    /**
     * Runs project_scanner → json_emitter, writes llm.json. #AI:handle
     *
     * Enriches output with extension capability details from ext_registry.
     * Returns 1 when the source directory does not exist or scan/write fails.
     *
     * @return int 0 on success, 1 on failure.
     */
    public function handle(): int {
        $paths  = \Skim\Dev\Docs\Value\DocsGenerationPaths::fromFlags($this->flags);
        $output = $paths->jsonPath();

        $this->info('Scanning source files…');
        $scanner = new \Skim\Dev\Docs\Extractor\ProjectScanner();

        try {
            $scan_paths = $paths->scanPaths();
            if ($paths->hasSourceOverride() && !is_dir($scan_paths[0])) {
                $this->error('Source directory not found: ' . $scan_paths[0]);
                return 1;
            }

            $classes = $paths->hasSourceOverride()
                ? $scanner->scanPaths($scan_paths)
                : $scanner->scan();
        } catch (\Throwable $e) {
            $this->error('Scan failed: ' . $e->getMessage());
            return 1;
        }

        $this->muted(count($classes) . ' classes found.');

        $registry            = new \Skim\Ext\ExtRegistry(basePath());
        $extensions          = $registry->installed();
        $capability_map      = [];
        $installed_names     = [];
        foreach ($extensions as $ext) {
            $installed_names[] = $ext['name'];
            foreach ($ext['capability_details'] as $cap => $info) {
                $capability_map[$cap] ??= array_merge(['provided_by' => $ext['name']], (array) $info);
            }
            foreach ($ext['capabilities'] as $cap) {
                $capability_map[$cap] ??= ['provided_by' => $ext['name']];
            }
        }

        try {
            (new \Skim\Dev\Docs\Emitter\JsonEmitter())->emit($classes, $output, $capability_map, $installed_names);
        } catch (\Throwable $e) {
            $this->error('Write failed: ' . $e->getMessage());
            return 1;
        }

        $this->success("llm.json written → {$output}");
        return 0;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Commands\DocsExtractCommand
#AI source_path: src/dev/docs/commands/docs_extract_command.php
#AI title: docs_extract_command
#AI description: CLI command that scans PHP source files, extracts @ai.* annotations via AST, and writes llm.json.
#AI role: CLI extraction command
#AI layer: dev
#AI badges: [cli; docs; extraction; ast]
#AI intro: `docs_extract_command` is the first step in the docs pipeline. It uses `project_scanner` to walk source paths, runs `class_extractor` on each .php file, enriches the result with extension capability data from `ext_registry`, and writes the output to llm.json via `json_emitter`.
#AI lifecycle: instantiated by CLI router or docs_command, runs synchronously
#AI fallback: none — returns 1 on scan or write failure
#AI test_seam: instantiate directly with set_input() to inject flags
#AI invariants: [scan failure returns 1 with error message; write failure returns 1; null classes are silently discarded by scanner]
#AI core_behaviors: [Scans configured or overridden source paths for .php files; Extracts annotations via AST using class_extractor; Enriches output with extension capabilities from ext_registry]
#AI owns: project_scanner, json_emitter instances
#AI entry_points: [handle]
#AI config_reads: [docs.scan_paths; docs.output.json]
#AI non_goals: [Does not generate MDX or Markdown; Does not validate annotation coverage]
#AI side_effects: [writes llm.json to configured or overridden output path]
#AI flow: handle() -> docs_generation_paths -> project_scanner -> ext_registry -> json_emitter -> llm.json
#AI lifecycle_steps: [handle(); -> resolve paths from flags/config; -> project_scanner.scan(); -> ext_registry.installed(); -> json_emitter.emit(); -> llm.json written]
#AI section_order: [Pipeline; Architecture]
#AI architectural_notes: Separates scanning, enrichment, and writing into distinct steps for clear error reporting.

#AI:handle
#AI group: Pipeline
#AI frequency: high
#AI signature: public function handle(): int
#AI contract: Scans source paths, extracts class annotations, enriches with extension data, and writes llm.json. Returns 1 when the source directory is missing or any step throws.
#AI return_detail: {type: int | desc: 0 on success, 1 on scan or write failure.}
#AI side_effects: [writes llm.json to disk]
