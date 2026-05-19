<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;
use skim\dev\docs\emitter\json_emitter;
use skim\dev\docs\emitter\mdx_emitter;

// Reads llm.json and generates MDX files for the Starlight docs site.
// Requires docs:extract to have been run first.
// Usage: php skim docs:site
class docs_site_command extends command {
    /**
     * @ai-contract reads llm.json → writes one MDX file per class via mdx_emitter
     * @ai-contract returns 0 on success, 1 on failure
     * @ai-contract fails with clear message if llm.json does not exist
     */
    public function handle(): int {
        $json_path = config('docs.output.json',    base_path('llm.json'));
        $mdx_dir   = config('docs.output.mdx_dir', base_path('docs/src/content/docs/api'));

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
