<?php declare(strict_types=1);

namespace skim\dev\docs\extractor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use skim\dev\docs\value\extracted_class;

// Extracts documentation metadata from a single PHP file via AST.
// Uses nikic/php-parser — no regex on source code.
// Skips non-public methods. Returns null for files with no class.
class class_extractor {
    private readonly \PhpParser\Parser $parser;
    private readonly annotation_parser $annotations;

    public function __construct() {
        $this->parser      = (new ParserFactory())->createForNewestSupportedVersion();
        $this->annotations = new annotation_parser();
    }

    /**
     * @ai-contract accepts absolute file path, returns extracted_class or null
     * @ai-contract returns null if file has no class, cannot be parsed, or has a parse error
     * @ai-contract only public methods are extracted; private/protected methods are skipped
     * @ai-contract delegates docblock parsing to annotation_parser via class_visitor
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
