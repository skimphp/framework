<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Commands;

use Skim\Cli\Command;
use Skim\Dev\Docs\Emitter\JsonEmitter;
use Skim\Dev\Docs\Emitter\MdxEmitter;
use Skim\Dev\Docs\Value\DocsGenerationPaths;

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
class DocsSiteCommand extends \Skim\Cli\Command {
    /**
     * Reads llm.json → writes one MDX file per class via mdx_emitter. #AI:handle
     *
     * Fails with a clear message when llm.json does not exist.
     *
     * @return int 0 on success, 1 on failure.
     */
    public function handle(): int {
        $paths     = \Skim\Dev\Docs\Value\DocsGenerationPaths::fromFlags($this->flags);
        $jsonPath = $paths->jsonPath();
        $mdxDir   = $paths->mdxDir();

        try {
            $data = (new \Skim\Dev\Docs\Emitter\JsonEmitter())->load($jsonPath);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        try {
            $count = (new \Skim\Dev\Docs\Emitter\MdxEmitter())->emit($data, $mdxDir);
        } catch (\Throwable $e) {
            $this->error('Write failed: ' . $e->getMessage());
            return 1;
        }

        $this->success("{$count} MDX files written → {$mdxDir}");
        return 0;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Commands\DocsSiteCommand
#AI source_path: src/Dev/Docs/Commands/DocsSiteCommand.php
#AI title: DocsSiteCommand
#AI description: CLI command that reads llm.json and generates one MDX file per class for the Starlight documentation site.
#AI role: CLI MDX generator
#AI layer: dev
#AI badges: [cli; docs; mdx; starlight]
#AI intro: `DocsSiteCommand` converts the structured llm.json into component-style MDX files suitable for a Starlight/Astro documentation site. Each class becomes one .mdx file; duplicate class names receive a source-file suffix.
#AI lifecycle: instantiated by CLI router or DocsCommand, runs synchronously
#AI fallback: none — returns 1 when llm.json is missing or write fails
#AI test_seam: instantiate directly with setInput() to inject flags
#AI invariants: [fails when llm.json does not exist; duplicate class names get suffixed filenames]
#AI core_behaviors: [Loads llm.json via JsonEmitter; Delegates MDX generation to MdxEmitter; Reports count of files written]
#AI owns: JsonEmitter, MdxEmitter instances
#AI entry_points: [handle]
#AI config_reads: [docs.output.json; docs.output.mdx_dir]
#AI non_goals: [Does not generate llm.md; Does not run extraction]
#AI side_effects: [writes MDX files to configured or overridden output directory]
#AI flow: handle() -> JsonEmitter.load() -> MdxEmitter.emit() -> *.mdx files
#AI lifecycle_steps: [handle(); -> resolve paths; -> JsonEmitter.load(llm.json); -> MdxEmitter.emit(); -> MDX files written]
#AI section_order: [Pipeline; Architecture]
#AI architectural_notes: Requires docs:extract to have been run first; does not invoke it automatically.

#AI:handle
#AI group: Pipeline
#AI frequency: high
#AI signature: public function handle(): int
#AI contract: Loads llm.json and generates one MDX file per class. Returns 1 when llm.json is missing or write fails.
#AI return_detail: {type: int | desc: 0 on success, 1 on failure.}
#AI side_effects: [writes MDX files to disk]
