<?php declare(strict_types=1);

namespace skim\dev\docs\commands;

use skim\cli\command;

// Umbrella command: runs docs:extract → docs:llm → docs:site in sequence.
// Supports --watch flag to rebuild llm.json on file change (inotifywait / fsevents).
// Usage:
//   php skim docs            → one-shot full build
//   php skim docs --watch    → rebuild on .php file change
class docs_command extends command {
    /**
     * @ai-contract runs extract → llm → site in sequence; stops on first non-zero exit
     * @ai-contract with --watch: polls scan_paths every 2s; triggers full rebuild on mtime change
     * @ai-contract returns 0 on success, first non-zero exit code on failure
     */
    public function handle(): int {
        $watch = (bool) $this->flag('watch', false);

        if ($watch) {
            return $this->watch_mode();
        }

        return $this->build();
    }

    private function build(): int {
        foreach ([
            new docs_extract_command(),
            new docs_llm_command(),
            new docs_site_command(),
        ] as $cmd) {
            $cmd->set_input($this->args, $this->flags);
            $code = $cmd->handle();
            if ($code !== 0) {
                return $code;
            }
        }
        return 0;
    }

    private function watch_mode(): int {
        $this->info('Watch mode — press Ctrl+C to stop.');
        $scan_paths = config('docs.scan_paths', []);
        $last_hash  = $this->mtime_hash($scan_paths);

        $this->build();

        while (true) {
            sleep(2);
            $current = $this->mtime_hash($scan_paths);
            if ($current !== $last_hash) {
                $this->muted('Change detected — rebuilding…');
                $this->build();
                $last_hash = $current;
            }
        }
    }

    /**
     * @ai-contract returns md5 of concatenated mtimes for all .php files in $paths
     * @ai-contract used to detect file changes in watch mode
     */
    private function mtime_hash(array $paths): string {
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
