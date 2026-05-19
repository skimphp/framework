<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;
use skim\dev\docs\emitter\json_emitter;
use skim\dev\docs\extractor\project_scanner;

// Scans all configured source paths and writes llm.json.
// Usage: php skim docs:extract
class docs_extract_command extends command {
    /**
     * @ai-contract runs project_scanner → json_emitter, writes llm.json to config path
     * @ai-contract returns 0 on success, 1 on failure
     */
    public function handle(): int {
        $output = config('docs.output.json', base_path('llm.json'));

        $this->info('Scanning source files…');
        $scanner = new project_scanner();

        try {
            $classes = $scanner->scan();
        } catch (\Throwable $e) {
            $this->error('Scan failed: ' . $e->getMessage());
            return 1;
        }

        $this->muted(count($classes) . ' classes found.');

        try {
            (new json_emitter())->emit($classes, $output);
        } catch (\Throwable $e) {
            $this->error('Write failed: ' . $e->getMessage());
            return 1;
        }

        $this->success("llm.json written → {$output}");
        return 0;
    }
}
