<?php declare(strict_types=1);

namespace skim\dev\docs\extractor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use skim\dev\docs\value\extracted_class;

/**
 * Extracts documentation metadata from a single PHP file via AST. #AI:class
 *
 * Use when you need to extract @ai.* annotations from one file. Uses
 * nikic/php-parser for reliable AST traversal — no regex on source code.
 * Returns null for files with no class or parse errors.
 *
 * Example:
 *   $extractor = new class_extractor();
 *   $class = $extractor->extract('/path/to/file.php');
 *   if ($class !== null) { ... }
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
class class_extractor {
    private readonly \PhpParser\Parser $parser;
    private readonly annotation_parser $annotations;

    public function __construct() {
        $this->parser      = (new ParserFactory())->createForNewestSupportedVersion();
        $this->annotations = new annotation_parser();
    }

    /**
     * Parses a PHP file and extracts documentation metadata. #AI:extract
     *
     * Returns null when the file has no class, cannot be read, or has a
     * parse error. Only public methods are extracted by default; private
     * methods are included only when they carry @ai.* tags or detached blocks.
     *
     * @param string $file Absolute path to the PHP file.
     * @return extracted_class|null Extracted metadata, or null on skip/failure.
     */
    public function extract(string $file): extracted_class|null {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }

        $source = @file_get_contents($file);
        if ($source === false) {
            return null;
        }

        try {
            $stmts = $this->parser->parse($source);
        } catch (\Throwable) {
            return null;
        }

        if ($stmts === null) {
            return null;
        }

        $visitor   = new class_visitor($this->annotations, $file);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->result;
    }
}

#AI:class
#AI symbol: skim\dev\docs\extractor\class_extractor
#AI source_path: src/dev/docs/extractor/class_extractor.php
#AI title: class_extractor
#AI description: Extracts documentation metadata from a single PHP file via nikic/php-parser AST traversal.
#AI role: AST-based class extractor
#AI layer: dev
#AI badges: [extractor; ast; php-parser; docs]
#AI intro: `class_extractor` parses a single PHP file using nikic/php-parser, delegates AST traversal to `class_visitor`, and returns an `extracted_class` value object. It silently returns null for non-class files, unreadable files, and parse errors.
#AI lifecycle: instantiated per-use by project_scanner, parser created once in constructor
#AI fallback: returns null on any failure (missing file, parse error, no class)
#AI test_seam: instantiate directly with test file paths
#AI invariants: [returns null for non-class files; returns null on parse errors; only public methods extracted by default; delegates docblock parsing to annotation_parser]
#AI core_behaviors: [Parses PHP source via nikic/php-parser; Delegates AST traversal to class_visitor; Silently skips files that cannot be processed]
#AI owns: PhpParser\Parser, annotation_parser instances
#AI entry_points: [extract]
#AI config_reads: []
#AI non_goals: [Does not scan directories; Does not write output files; Does not validate annotations]
#AI side_effects: []
#AI flow: extract(file) -> file_get_contents -> parser->parse -> class_visitor -> extracted_class
#AI lifecycle_steps: [extract(); -> is_file/is_readable check; -> file_get_contents; -> parser->parse(); -> class_visitor traversal; -> return visitor->result]
#AI section_order: [Extraction; Architecture]
#AI architectural_notes: Uses nikic/php-parser for reliable AST traversal — never regex on source code.

#AI:extract
#AI group: Extraction
#AI frequency: high
#AI signature: public function extract(string $file): extracted_class|null
#AI contract: Parses a PHP file and extracts documentation metadata into an extracted_class value object. Returns null when the file has no class, cannot be read, or has a parse error.
#AI param_details: [{name: $file | type: string | required: true | desc: Absolute path to the PHP file to extract.}]
#AI return_detail: {type: extracted_class|null | desc: Extracted metadata, or null on skip/failure.}
