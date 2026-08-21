<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;
use skim\dev\docs\emitter\json_emitter;
use skim\dev\docs\emitter\mdx_emitter;
use skim\dev\docs\value\docs_generation_paths;

/**
 * Reads llm.json and generates MDX files for the Starlight docs site. #AI:class
 *
 * Use after docs:extract to produce one MDX file per class. Duplicate class
 * names get a source-file suffix to avoid overwrites.
 *
 * Example:
 *   php skim docs:site
 *   php skim docs:site --output=build/docs
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
class docs_site_command extends command {
    /**
     * Reads llm.json → writes one MDX file per class via mdx_emitter. #AI:handle
     *
     * Fails with a clear message when llm.json does not exist.
     *
     * @return int 0 on success, 1 on failure.
     */
    public function handle(): int {
        $paths     = docs_generation_paths::from_flags($this->flags);
        $json_path = $paths->json_path();
        $mdx_dir   = $paths->mdx_dir();

        try {
            $data = (new json_emitter())->load($json_path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        try {
            $count = (new mdx_emitter())->emit($data, $mdx_dir);
        } catch (\Throwable $e) {
            $this->error('Write failed: ' . $e->getMessage());
            return 1;
        }

        $this->success("{$count} MDX files written → {$mdx_dir}");
        return 0;
    }
}

#AI:class
#AI symbol: skim\dev\docs\commands\docs_site_command
#AI source_path: src/dev/docs/commands/docs_site_command.php
#AI title: docs_site_command
#AI description: CLI command that reads llm.json and generates one MDX file per class for the Starlight documentation site.
#AI role: CLI MDX generator
#AI layer: dev
#AI badges: [cli; docs; mdx; starlight]
#AI intro: `docs_site_command` converts the structured llm.json into component-style MDX files suitable for a Starlight/Astro documentation site. Each class becomes one .mdx file; duplicate class names receive a source-file suffix.
#AI lifecycle: instantiated by CLI router or docs_command, runs synchronously
#AI fallback: none — returns 1 when llm.json is missing or write fails
#AI test_seam: instantiate directly with set_input() to inject flags
#AI invariants: [fails when llm.json does not exist; duplicate class names get suffixed filenames]
#AI core_behaviors: [Loads llm.json via json_emitter; Delegates MDX generation to mdx_emitter; Reports count of files written]
#AI owns: json_emitter, mdx_emitter instances
#AI entry_points: [handle]
#AI config_reads: [docs.output.json; docs.output.mdx_dir]
#AI non_goals: [Does not generate llm.md; Does not run extraction]
#AI side_effects: [writes MDX files to configured or overridden output directory]
#AI flow: handle() -> json_emitter.load() -> mdx_emitter.emit() -> *.mdx files
#AI lifecycle_steps: [handle(); -> resolve paths; -> json_emitter.load(llm.json); -> mdx_emitter.emit(); -> MDX files written]
#AI section_order: [Pipeline; Architecture]
#AI architectural_notes: Requires docs:extract to have been run first; does not invoke it automatically.

#AI:handle
#AI group: Pipeline
#AI frequency: high
#AI signature: public function handle(): int
#AI contract: Loads llm.json and generates one MDX file per class. Returns 1 when llm.json is missing or write fails.
#AI return_detail: {type: int | desc: 0 on success, 1 on failure.}
#AI side_effects: [writes MDX files to disk]
