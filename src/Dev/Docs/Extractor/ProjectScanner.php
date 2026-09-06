<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Extractor;

use Skim\Dev\Docs\Value\ExtractedClass;

/**
 * Walks all configured scan paths and returns a flat extracted_class[] collection. #AI:class
 *
 * Use as the top-level entry point for scanning an entire project.
 * Reads scan_paths from config/docs.php or accepts explicit path overrides.
 *
 * Example:
 *   $scanner = new ProjectScanner();
 *   $classes = $scanner->scan();               // uses config scan_paths
 *   $classes = $scanner->scanPaths(['/app']); // explicit paths
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
class ProjectScanner {
    private readonly \Skim\Dev\Docs\Extractor\ClassExtractor $extractor;

    public function __construct() {
        $this->extractor = new \Skim\Dev\Docs\Extractor\ClassExtractor();
    }

    /**
     * Scans config scan_paths and returns all extracted classes. #AI:scan
     *
     * Non-directory paths and files that return null from the extractor
     * are silently discarded.
     *
     * @return \Skim\Dev\Docs\Value\ExtractedClass[] One entry per class found.
     */
    public function scan(): array {
        $paths = config('docs.scan_paths', []);
        $result = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            foreach ($this->phpFiles($path) as $file) {
                $class = $this->extractor->extract($file);
                if ($class !== null) {
                    $result[] = $class;
                }
            }
        }
        return $result;
    }

    /**
     * Scans explicit paths instead of config — used by commands with --source override. #AI:scanPaths
     *
     * Same null-discard behaviour as scan().
     *
     * @param string[] $paths Directories to scan recursively.
     * @return \Skim\Dev\Docs\Value\ExtractedClass[] One entry per class found.
     */
    public function scanPaths(array $paths): array {
        $result = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }
            foreach ($this->phpFiles($path) as $file) {
                $class = $this->extractor->extract($file);
                if ($class !== null) {
                    $result[] = $class;
                }
            }
        }
        return $result;
    }

    /**
     * Yields absolute paths of all .php files under a directory, recursively. #AI:phpFiles
     *
     * Paths are sorted byte-wise so generated documentation is deterministic
     * regardless of filesystem readdir order.
     *
     * @param string $dir Root directory to scan.
     * @return iterable<string> Absolute file paths.
     */
    private function phpFiles(string $dir): iterable {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        $paths = [];
        foreach ($iter as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths, SORT_STRING);
        yield from $paths;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Extractor\ProjectScanner
#AI source_path: src/Dev/Docs/Extractor/ProjectScanner.php
#AI title: ProjectScanner
#AI description: Walks configured scan paths, runs ClassExtractor on each .php file, and returns a flat ExtractedClass[] collection.
#AI role: project-wide scanner
#AI layer: dev
#AI badges: [extractor; scanner; docs; recursive]
#AI intro: `ProjectScanner` is the top-level entry point for the docs extraction pipeline. It walks directories, finds .php files, and delegates extraction to `ClassExtractor`. Supports both config-driven and explicit path scanning.
#AI lifecycle: instantiated per-use by commands, extractor created once in constructor
#AI fallback: non-directory paths silently skipped; null extractions silently discarded
#AI test_seam: instantiate directly; no static state
#AI invariants: [non-directory paths silently skipped; null extractions silently discarded; recursive .php file discovery]
#AI core_behaviors: [Reads scanPaths from config/docs.php; Recursively finds .php files in each path; Runs ClassExtractor on each file; Discards null results]
#AI owns: ClassExtractor instance
#AI entry_points: [scan; scanPaths]
#AI config_reads: [docs.scan_paths]
#AI non_goals: [Does not write output files; Does not validate annotations]
#AI side_effects: []
#AI flow: scan() -> config scan_paths -> phpFiles() -> extractor.extract() -> ExtractedClass[]
#AI lifecycle_steps: [scan(); -> read config scan_paths; -> iterate paths; -> phpFiles() recursive; -> extractor.extract(); -> collect non-null results]
#AI section_order: [Scanning; Architecture]
#AI architectural_notes: No framework dependencies beyond the config() helper.

#AI:scan
#AI group: Scanning
#AI frequency: high
#AI signature: public function scan(): array
#AI contract: Reads scanPaths from config/docs.php, recursively finds all .php files in each path, runs ClassExtractor on each, and returns a flat ExtractedClass[] with nulls discarded.
#AI return_detail: {type: ExtractedClass[] | desc: One entry per class found across all scan paths.}

#AI:scanPaths
#AI group: Scanning
#AI frequency: medium
#AI signature: public function scanPaths(array $paths): array
#AI contract: Scans the given explicit paths instead of config scan_paths. Same null-discard behaviour as scan(). Used by commands that override paths via --source flag.
#AI param_details: [{name: $paths | type: string[] | required: true | desc: Directories to scan recursively for .php files.}]
#AI return_detail: {type: ExtractedClass[] | desc: One entry per class found.}

#AI:phpFiles
#AI group: Architecture
#AI frequency: internal
#AI signature: private function phpFiles(string $dir): iterable
#AI contract: Yields absolute paths of all .php files under the given directory, recursively, using RecursiveIteratorIterator.
