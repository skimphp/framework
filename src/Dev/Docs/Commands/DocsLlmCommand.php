<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Commands;

use Skim\Cli\Command;
use Skim\Dev\Docs\Emitter\JsonEmitter;
use Skim\Dev\Docs\Emitter\LlmMdEmitter;
use Skim\Dev\Docs\Value\DocsGenerationPaths;

/**
 * Reads llm.json and writes llm.md to the repo root. #AI:class
 *
 * Use after docs:extract to produce a single Markdown file suitable for
 * pasting into any LLM context window. Optionally prepends framework-level
 * llm.md when config('docs.include_framework_context') is true.
 *
 * Example:
 *   php skim docs:llm
 *   php skim docs:llm --output=build/docs
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
class DocsLlmCommand extends \Skim\Cli\Command {
    /**
     * Reads llm.json → writes llm.md via llm_md_emitter. #AI:handle
     *
     * Fails with a clear message when llm.json does not exist.
     * Prepends framework context when docs.include_framework_context is true.
     *
     * @return int 0 on success, 1 on failure.
     */
    public function handle(): int {
        $paths     = \Skim\Dev\Docs\Value\DocsGenerationPaths::fromFlags($this->flags);
        $jsonPath = $paths->jsonPath();
        $mdPath   = $paths->llmMdPath();

        try {
            $data = (new \Skim\Dev\Docs\Emitter\JsonEmitter())->load($jsonPath);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $includeFramework = (bool) config('docs.include_framework_context', false);
        $frameworkLlmMd  = $includeFramework
            ? basePath('vendor/skim/framework/llm.md')
            : null;

        try {
            (new \Skim\Dev\Docs\Emitter\LlmMdEmitter())->emit($data, $mdPath, frameworkLlmMd: $frameworkLlmMd);
        } catch (\Throwable $e) {
            $this->error('Write failed: ' . $e->getMessage());
            return 1;
        }

        $this->success("llm.md written → {$mdPath}");
        return 0;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Commands\DocsLlmCommand
#AI source_path: src/Dev/Docs/Commands/DocsLlmCommand.php
#AI title: DocsLlmCommand
#AI description: CLI command that reads llm.json and writes a compact llm.md Markdown file for LLM context windows.
#AI role: CLI markdown emitter
#AI layer: dev
#AI badges: [cli; docs; markdown; llm]
#AI intro: `DocsLlmCommand` converts the structured llm.json into a single grouped Markdown file (llm.md) that fits within LLM context windows. Optionally prepends the framework-level llm.md for full API context.
#AI lifecycle: instantiated by CLI router or DocsCommand, runs synchronously
#AI fallback: none — returns 1 when llm.json is missing or write fails
#AI test_seam: instantiate directly with setInput() to inject flags
#AI invariants: [fails with clear message when llm.json does not exist; framework context prepended only when config flag is true]
#AI core_behaviors: [Loads llm.json via JsonEmitter; Delegates Markdown rendering to LlmMdEmitter; Optionally prepends framework llm.md]
#AI owns: JsonEmitter, LlmMdEmitter instances
#AI entry_points: [handle]
#AI config_reads: [docs.output.json; docs.output.llm_md; docs.include_framework_context]
#AI non_goals: [Does not generate MDX; Does not run extraction]
#AI side_effects: [writes llm.md to configured or overridden output path]
#AI flow: handle() -> JsonEmitter.load() -> LlmMdEmitter.emit() -> llm.md
#AI lifecycle_steps: [handle(); -> resolve paths; -> JsonEmitter.load(llm.json); -> optionally load framework llm.md; -> LlmMdEmitter.emit(); -> llm.md written]
#AI section_order: [Pipeline; Architecture]
#AI architectural_notes: Requires docs:extract to have been run first; does not invoke it automatically.

#AI:handle
#AI group: Pipeline
#AI frequency: high
#AI signature: public function handle(): int
#AI contract: Loads llm.json, optionally prepends framework context, and writes llm.md. Returns 1 when llm.json is missing or write fails.
#AI return_detail: {type: int | desc: 0 on success, 1 on failure.}
#AI side_effects: [writes llm.md to disk]
