<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;
use skim\dev\docs\emitter\json_emitter;
use skim\dev\docs\emitter\llm_md_emitter;
use skim\dev\docs\value\docs_generation_paths;

// Reads llm.json and writes llm.md to the repo root.
// Requires docs:extract to have been run first.
// Usage: php skim docs:llm [--output=DIR]
class docs_llm_command extends command {
    /**
     * @ai-contract reads llm.json → writes llm.md via llm_md_emitter
     * @ai-contract returns 0 on success, 1 on failure
     * @ai-contract fails with clear message if llm.json does not exist
     */
    public function handle(): int {
        $paths     = docs_generation_paths::from_flags($this->flags);
        $json_path = $paths->json_path();
        $md_path   = $paths->llm_md_path();

        try {
            $data = (new json_emitter())->load($json_path);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $include_framework = (bool) config('docs.include_framework_context', false);
        $framework_llm_md  = $include_framework
            ? base_path('vendor/skim/framework/llm.md')
            : null;

        try {
            (new llm_md_emitter())->emit($data, $md_path, framework_llm_md: $framework_llm_md);
        } catch (\Throwable $e) {
            $this->error('Write failed: ' . $e->getMessage());
            return 1;
        }

        $this->success("llm.md written → {$md_path}");
        return 0;
    }
}
