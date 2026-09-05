<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Extractor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Skim\Dev\Docs\Value\ExtractedClass;

/**
 * Extracts documentation metadata from a single PHP file via AST. #AI:class
 *
 * Use when you need to extract @ai.* annotations from one file. Uses
 * nikic/php-parser for reliable AST traversal — no regex on source code.
 * Returns null for files with no class or parse errors.
 *
 * Example:
 *   $extractor = new ClassExtractor();
 *   $class = $extractor->extract('/path/to/file.php');
 *   if ($class !== null) { ... }
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
class ClassExtractor {
    private readonly \PhpParser\Parser $parser;
    private readonly \Skim\Dev\Docs\Extractor\AnnotationParser $annotations;

    public function __construct() {
        $this->parser      = (new ParserFactory())->createForNewestSupportedVersion();
        $this->annotations = new \Skim\Dev\Docs\Extractor\AnnotationParser();
    }

    /**
     * Parses a PHP file and extracts documentation metadata. #AI:extract
     *
     * Returns null when the file has no class, cannot be read, or has a
     * parse error. Only public methods are extracted by default; private
     * methods are included only when they carry @ai.* tags or detached blocks.
     *
     * @param string $file Absolute path to the PHP file.
     * @return \Skim\Dev\Docs\Value\ExtractedClass|null Extracted metadata, or null on skip/failure.
     */
    public function extract(string $file): \Skim\Dev\Docs\Value\ExtractedClass|null {
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

        $visitor   = new \Skim\Dev\Docs\Extractor\ClassVisitor($this->annotations, $file);
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($stmts);

        return $visitor->result;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Extractor\ClassExtractor
#AI source_path: src/Dev/Docs/Extractor/ClassExtractor.php
#AI title: ClassExtractor
#AI description: Extracts documentation metadata from a single PHP file via nikic/php-parser AST traversal.
#AI role: AST-based class extractor
#AI layer: dev
#AI badges: [extractor; ast; php-parser; docs]
#AI intro: `ClassExtractor` parses a single PHP file using nikic/php-parser, delegates AST traversal to `ClassVisitor`, and returns an `ExtractedClass` value object. It silently returns null for non-class files, unreadable files, and parse errors.
#AI lifecycle: instantiated per-use by ProjectScanner, parser created once in constructor
#AI fallback: returns null on any failure (missing file, parse error, no class)
#AI test_seam: instantiate directly with test file paths
#AI invariants: [returns null for non-class files; returns null on parse errors; only public methods extracted by default; delegates docblock parsing to AnnotationParser]
#AI core_behaviors: [Parses PHP source via nikic/php-parser; Delegates AST traversal to ClassVisitor; Silently skips files that cannot be processed]
#AI owns: PhpParser\Parser, AnnotationParser instances
#AI entry_points: [extract]
#AI config_reads: []
#AI non_goals: [Does not scan directories; Does not write output files; Does not validate annotations]
#AI side_effects: []
#AI flow: extract(file) -> file_get_contents -> parser->parse -> ClassVisitor -> ExtractedClass
#AI lifecycle_steps: [extract(); -> is_file/is_readable check; -> file_get_contents; -> parser->parse(); -> ClassVisitor traversal; -> return visitor->result]
#AI section_order: [Extraction; Architecture]
#AI architectural_notes: Uses nikic/php-parser for reliable AST traversal — never regex on source code.

#AI:extract
#AI group: Extraction
#AI frequency: high
#AI signature: public function extract(string $file): ExtractedClass|null
#AI contract: Parses a PHP file and extracts documentation metadata into an ExtractedClass value object. Returns null when the file has no class, cannot be read, or has a parse error.
#AI param_details: [{name: $file | type: string | required: true | desc: Absolute path to the PHP file to extract.}]
#AI return_detail: {type: ExtractedClass|null | desc: Extracted metadata, or null on skip/failure.}
