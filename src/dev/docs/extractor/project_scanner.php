<?php declare(strict_types=1);

namespace skim\dev\docs\extractor;

use skim\dev\docs\value\extracted_class;

// Walks all scan_paths from config/docs.php, runs class_extractor on each .php file,
// and returns a flat extracted_class[] collection.
// No framework dependencies beyond config() helper.
class project_scanner {
    private readonly class_extractor $extractor;

    public function __construct() {
        $this->extractor = new class_extractor();
    }

    /**
     * @ai-contract reads scan_paths from config/docs.php
     * @ai-contract recursively finds all .php files in each path
     * @ai-contract runs class_extractor on each file; nulls are silently discarded
     * @ai-contract returns flat extracted_class[] — one entry per class found
     */
    public function scan(): array {
        $paths = config('docs.scan_paths', []);
        $result = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            foreach ($this->php_files($path) as $file) {
                $class = $this->extractor->extract($file);
                if ($class !== null) {
                    $result[] = $class;
                }
            }
        }
        return $result;
    }

    /**
     * @ai-contract scans $paths instead of config scan_paths — used by commands that override paths
     * @ai-contract same null-discard behaviour as scan()
     *
     * @param string[] $paths
     * @return extracted_class[]
     */
    public function scan_paths(array $paths): array {
        $result = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            foreach ($this->php_files($path) as $file) {
                $class = $this->extractor->extract($file);
                if ($class !== null) {
                    $result[] = $class;
                }
            }
        }
        return $result;
    }

    /**
     * @ai-contract yields absolute paths of all .php files under $dir, recursively
     *
     * @return iterable<string>
     */
    private function php_files(string $dir): iterable {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iter as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
