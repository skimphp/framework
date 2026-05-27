<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;
use skim\dev\docs\emitter\json_emitter;
use skim\dev\docs\extractor\project_scanner;
use skim\dev\docs\value\docs_generation_paths;
use skim\ext\ext_registry;

// Scans all configured source paths and writes llm.json.
// Usage: php skim docs:extract [--source=DIR] [--output=DIR]
class docs_extract_command extends command {
    /**
     * @ai-contract runs project_scanner → json_emitter, writes llm.json to config path
     * @ai-contract returns 0 on success, 1 on failure
     */
    public function handle(): int {
        $paths  = docs_generation_paths::from_flags($this->flags);
        $output = $paths->json_path();

        $this->info('Scanning source files…');
        $scanner = new project_scanner();

        try {
            $scan_paths = $paths->scan_paths();
            if ($paths->has_source_override() && !is_dir($scan_paths[0])) {
                $this->error('Source directory not found: ' . $scan_paths[0]);
                return 1;
            }

            $classes = $paths->has_source_override()
                ? $scanner->scan_paths($scan_paths)
                : $scanner->scan();
        } catch (\Throwable $e) {
            $this->error('Scan failed: ' . $e->getMessage());
            return 1;
        }

        $this->muted(count($classes) . ' classes found.');

        $registry            = new ext_registry(base_path());
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
            (new json_emitter())->emit($classes, $output, $capability_map, $installed_names);
        } catch (\Throwable $e) {
            $this->error('Write failed: ' . $e->getMessage());
            return 1;
        }

        $this->success("llm.json written → {$output}");
        return 0;
    }
}
