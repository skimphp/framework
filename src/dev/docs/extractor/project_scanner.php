<?php declare(strict_types=1);

namespace skim\dev\docs\extractor;

use skim\dev\docs\value\extracted_class;

/**
 * Walks all configured scan paths and returns a flat extracted_class[] collection. #AI:class
 *
 * Use as the top-level entry point for scanning an entire project.
 * Reads scan_paths from config/docs.php or accepts explicit path overrides.
 *
 * Example:
 *   $scanner = new project_scanner();
 *   $classes = $scanner->scan();               // uses config scan_paths
 *   $classes = $scanner->scan_paths(['/app']); // explicit paths
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
class project_scanner {
    private readonly class_extractor $extractor;

    public function __construct() {
        $this->extractor = new class_extractor();
    }

    /**
     * Scans config scan_paths and returns all extracted classes. #AI:scan
     *
     * Non-directory paths and files that return null from the extractor
     * are silently discarded.
     *
     * @return extracted_class[] One entry per class found.
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
     * Scans explicit paths instead of config — used by commands with --source override. #AI:scan_paths
     *
     * Same null-discard behaviour as scan().
     *
     * @param string[] $paths Directories to scan recursively.
     * @return extracted_class[] One entry per class found.
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
     * Yields absolute paths of all .php files under a directory, recursively. #AI:php_files
     *
     * @param string $dir Root directory to scan.
     * @return iterable<string> Absolute file paths.
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

#AI:class
#AI symbol: skim\dev\docs\extractor\project_scanner
#AI source_path: src/dev/docs/extractor/project_scanner.php
#AI title: project_scanner
#AI description: Walks configured scan paths, runs class_extractor on each .php file, and returns a flat extracted_class[] collection.
#AI role: project-wide scanner
#AI layer: dev
#AI badges: [extractor; scanner; docs; recursive]
#AI intro: `project_scanner` is the top-level entry point for the docs extraction pipeline. It walks directories, finds .php files, and delegates extraction to `class_extractor`. Supports both config-driven and explicit path scanning.
#AI lifecycle: instantiated per-use by commands, extractor created once in constructor
#AI fallback: non-directory paths silently skipped; null extractions silently discarded
#AI test_seam: instantiate directly; no static state
#AI invariants: [non-directory paths silently skipped; null extractions silently discarded; recursive .php file discovery]
#AI core_behaviors: [Reads scan_paths from config/docs.php; Recursively finds .php files in each path; Runs class_extractor on each file; Discards null results]
#AI owns: class_extractor instance
#AI entry_points: [scan; scan_paths]
#AI config_reads: [docs.scan_paths]
#AI non_goals: [Does not write output files; Does not validate annotations]
#AI side_effects: []
#AI flow: scan() -> config scan_paths -> php_files() -> extractor.extract() -> extracted_class[]
#AI lifecycle_steps: [scan(); -> read config scan_paths; -> iterate paths; -> php_files() recursive; -> extractor.extract(); -> collect non-null results]
#AI section_order: [Scanning; Architecture]
#AI architectural_notes: No framework dependencies beyond the config() helper.

#AI:scan
#AI group: Scanning
#AI frequency: high
#AI signature: public function scan(): array
#AI contract: Reads scan_paths from config/docs.php, recursively finds all .php files in each path, runs class_extractor on each, and returns a flat extracted_class[] with nulls discarded.
#AI return_detail: {type: extracted_class[] | desc: One entry per class found across all scan paths.}

#AI:scan_paths
#AI group: Scanning
#AI frequency: medium
#AI signature: public function scan_paths(array $paths): array
#AI contract: Scans the given explicit paths instead of config scan_paths. Same null-discard behaviour as scan(). Used by commands that override paths via --source flag.
#AI param_details: [{name: $paths | type: string[] | required: true | desc: Directories to scan recursively for .php files.}]
#AI return_detail: {type: extracted_class[] | desc: One entry per class found.}

#AI:php_files
#AI group: Architecture
#AI frequency: internal
#AI signature: private function php_files(string $dir): iterable
#AI contract: Yields absolute paths of all .php files under the given directory, recursively, using RecursiveIteratorIterator.
