<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use Skim\Dev\Docs\Value\ExtractedClass;
use Skim\Dev\Docs\Value\ExtractedMethod;

/**
 * Internal AST visitor that captures the first non-anonymous class and its annotated methods. #AI:class
 *
 * Use only via class_extractor — not part of the public API.
 * Captures class-level and method-level @ai.* tags from PHPDoc blocks,
 * inline // comments, and detached #AI blocks at the bottom of the file.
 *
 * Testing: Instantiate via class_extractor; not designed for direct use.
 *
 * #AI:class
 */
class ClassVisitor extends NodeVisitorAbstract {
    public ?\Skim\Dev\Docs\Value\ExtractedClass $result = null;
    private string $currentNamespace = '';
    private ?array $fileLines = null;

    public function __construct(
        private readonly \Skim\Dev\Docs\Extractor\AnnotationParser $annotations,
        private readonly string $file,
    ) {}

    /**
     * Returns cached file lines, reading from disk on first call. #AI:get_file_lines
     */
    private function getFileLines(): array {
        if ($this->fileLines === null) {
            $content = file_exists($this->file) ? file_get_contents($this->file) : '';
            $this->fileLines = array_merge([''], explode("\n", $content));
        }
        return $this->fileLines;
    }

    /**
     * Collects consecutive // comment lines immediately preceding a node. #AI:get_preceding_inline_comments
     */
    private function getPrecedingInlineComments(Node $node): string {
        $lines = $this->getFileLines();
        $startLine = $node->getStartLine();
        
        $commentLines = [];
        for ($i = $startLine - 1; $i >= 1; $i--) {
            $line = $lines[$i] ?? '';
            if (preg_match('/^\s*\/\//', $line)) {
                $commentLines[] = $line;
            } else {
                break;
            }
        }
        
        $commentLines = array_reverse($commentLines);
        return implode("\n", $commentLines);
    }

    /**
     * Visits AST nodes, capturing the first non-anonymous class. #AI:enterNode
     *
     * Merges tags from PHPDoc, inline comments, detached #AI blocks, and
     * comment-trailing #AI blocks. Builds extracted_class with all methods.
     */
    public function enterNode(Node $node): null {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name !== null ? (string) $node->name : '';
            return null;
        }

        if (!$node instanceof Class_ || $node->isAnonymous() || $this->result !== null) {
            return null;
        }

        $classDoc = (string) ($node->getDocComment()?->getText() ?? '');
        $classExamples = $classDoc !== '' ? $this->annotations->extractExamples($classDoc) : [];
        $tags = [];
        $summary = '';
        if ($classDoc !== '') {
            $tags    = $this->annotations->parse($classDoc);
            $summary = $this->annotations->extractSummary($classDoc);
        } else {
            $inline  = $this->getPrecedingInlineComments($node);
            $tags    = $this->annotations->parseInline($inline);
            $summary = $tags['summary'] ?? '';
            unset($tags['summary']);
        }

        foreach ($node->getComments() as $comment) {
            $commentText = $comment->getText();
            if (str_contains($commentText, '#AI')) {
                $hashTags = $this->annotations->parseHashAi($commentText);
                foreach ($hashTags as $k => $v) {
                    $tags[$k][] = $v;
                }
            }
        }

        if ($summary === '' && !empty($tags['role'])) {
            $summary = $tags['role'][0];
        }

        $owner = $this->currentNamespace . '\\' . (string) $node->name;

        $detached = $this->parseDetachedBlocks();
        if (($detached['class'] ?? []) !== []) {
            $tags = array_merge($tags, $detached['class']);
        }

        $methods = [];
        $methodNodes = [];
        foreach ($node->getMethods() as $method) {
            $methodNodes[(string) $method->name] = $method;
            $doc = (string) ($method->getDocComment()?->getText() ?? '');
            if ($doc !== '') {
                $tagsM = $this->annotations->parse($doc);
                $summaryM = $this->annotations->extractSummary($doc);
            } else {
                $inlineM = $this->getPrecedingInlineComments($method);
                $tagsM = $this->annotations->parseInline($inlineM);
                $summaryM = $tagsM['summary'] ?? '';
                unset($tagsM['summary']);
            }

            if (!$method->isPublic()) {
                $hasOwner = !empty($tagsM['owner']);
                $hasLifecycle = !empty($tagsM['lifecycle']);
                $hasDetached = isset($detached['methods'][(string) $method->name]);
                if (!$hasOwner && !$hasLifecycle && !$hasDetached) {
                    continue;
                }
            }

            if (isset($detached['methods'][(string) $method->name])) {
                $tagsM = array_merge($tagsM, $detached['methods'][(string) $method->name]);
            }

            if ($summaryM !== '' && !isset($detached['methods'][(string) $method->name])) {
                if (!isset($tagsM['contract'])) {
                    $tagsM['contract'] = [];
                }
                if (!is_array($tagsM['contract'])) {
                    $tagsM['contract'] = [$tagsM['contract']];
                }
                array_unshift($tagsM['contract'], $summaryM);
            }

            $methods[] = $this->extractMethod($method, $owner, $tagsM);
        }

        foreach ($detached['methods'] ?? [] as $name => $detachedTags) {
            if (isset($methodNodes[$name])) {
                continue;
            }
        }

        $invariants = array_merge($this->listValue($tags, 'invariant'), $this->listValue($tags, 'invariants'));
        $nonGoals = array_merge($this->listValue($tags, 'non_goal'), $this->listValue($tags, 'non_goals'));
        $sideEffects = array_merge($this->listValue($tags, 'side_effect'), $this->listValue($tags, 'side_effects'));

        $this->result = new \Skim\Dev\Docs\Value\ExtractedClass(
            className:   (string) $node->name,
            namespace:    $this->currentNamespace,
            file:         $this->file,
            summary:      $summary,
            lifecycle:    $this->scalarValue($tags, 'lifecycle'),
            owner:        $this->scalarValue($tags, 'role', $this->scalarValue($tags, 'owner')),
            layer:        $this->scalarValue($tags, 'layer'),
            owns:         $this->listValue($tags, 'owns'),
            entryPoints: $this->listValue($tags, 'entry_points'),
            configReads: $this->listValue($tags, 'config_reads'),
            invariants:   $invariants,
            sideEffects: $sideEffects,
            nonGoals:    $nonGoals,
            symbol:       $this->scalarValue($tags, 'symbol', $owner),
            title:        $this->scalarValue($tags, 'title', (string) $node->name),
            description:  $this->scalarValue($tags, 'description', $summary),
            sourcePath:  $this->scalarValue($tags, 'source_path'),
            badges:       $this->listValue($tags, 'badges'),
            intro:        $this->scalarValue($tags, 'intro'),
            fallback:     $this->scalarValue($tags, 'fallback'),
            testSeam:    $this->scalarValue($tags, 'test_seam'),
            drivers:      $this->listValue($tags, 'drivers'),
            coreBehaviors: $this->listValue($tags, 'core_behaviors'),
            warnings:     array_merge($this->listValue($tags, 'warning'), $this->listValue($tags, 'warnings')),
            notes:        $this->listValue($tags, 'notes'),
            scopeItems:  $this->listValue($tags, 'scope_items'),
            flow:         $this->scalarValue($tags, 'flow'),
            lifecycleSteps: $this->listValue($tags, 'lifecycle_steps'),
            sectionOrder: $this->listValue($tags, 'section_order'),
            architecturalNotes: $this->scalarValue($tags, 'architectural_notes'),
            examples:     $classExamples,
            seeAlso:     $this->listValue($tags, 'see_also'),
            methods:      $methods,
        );
        return null;
    }

    /**
     * Builds an extracted_method from a ClassMethod node and its merged tags. #AI:extractMethod
     */
    private function extractMethod(ClassMethod $method, string $owner, array $tags): \Skim\Dev\Docs\Value\ExtractedMethod {
        $params = [];
        foreach ($method->params as $param) {
            $type     = $param->type !== null ? $this->typeToString($param->type) : '';
            $variadic = $param->variadic ? '...' : '';
            $name     = $variadic . '$' . (string) $param->var->name;
            $params[] = ($type !== '' ? $type . ' ' : '') . $name;
        }
        $returnType = $method->returnType !== null
            ? ': ' . $this->typeToString($method->returnType)
            : '';
            
        $visName = match(true) {
            $method->isPrivate() => 'private',
            $method->isProtected() => 'protected',
            default => 'public',
        };
        $visibility = $method->isStatic() ? $visName . ' static' : $visName;
        $signature  = "{$visibility} function {$method->name}("
            . implode(', ', $params) . "){$returnType}";

        $invariants = array_merge($this->listValue($tags, 'invariant'), $this->listValue($tags, 'invariants'));
        $nonGoals = array_merge($this->listValue($tags, 'non_goal'), $this->listValue($tags, 'non_goals'));
        $sideEffects = array_merge($this->listValue($tags, 'side_effect'), $this->listValue($tags, 'side_effects'));
        $signature = $this->scalarValue($tags, 'signature', $signature);
        $contracts = $this->listValue($tags, 'contract');
        $contract = $contracts[0] ?? '';

        $doc = (string) ($method->getDocComment()?->getText() ?? '');
        $methodExamples = $doc !== '' ? $this->annotations->extractExamples($doc) : [];
        $tagExamples = $this->listValue($tags, 'example');
        $formattedExamples = [];
        foreach ($tagExamples as $ex) {
            if (is_array($ex) && isset($ex['code'])) {
                $formattedExamples[] = [
                    'label' => $ex['label'] ?? 'Basic usage',
                    'code' => $ex['code']
                ];
            } else {
                $formattedExamples[] = [
                    'label' => 'Basic usage',
                    'code' => (string) $ex
                ];
            }
        }
        $examples = array_merge($methodExamples, $formattedExamples);

        return new \Skim\Dev\Docs\Value\ExtractedMethod(
            name:         (string) $method->name,
            signature:    $signature,
            owner:        $owner,
            group:        $this->scalarValue($tags, 'group'),
            frequency:    $this->scalarValue($tags, 'frequency'),
            contracts:    $contracts,
            invariants:   $invariants,
            nonGoals:    $nonGoals,
            sideEffects: $sideEffects,
            inputs:       $this->listValue($tags, 'input'),
            returns:      $this->scalarValue($tags, 'returns'),
            reads:        $this->listValue($tags, 'reads'),
            mutates:      $this->listValue($tags, 'mutates'),
            calls:        $this->listValue($tags, 'calls'),
            throws:       $this->listValue($tags, 'throws'),
            warnings:     array_merge($this->listValue($tags, 'warning'), $this->listValue($tags, 'warnings')),
            examples:     $examples,
            lifecycle:    $this->scalarValue($tags, 'lifecycle'),
            perf:         $this->scalarValue($tags, 'perf'),
            contract:     $contract,
            paramDetails: $this->listValue($tags, 'param_details'),
            returnDetail: $this->recordValue($tags, 'return_detail'),
            throwsDetails: $this->listValue($tags, 'throws_details'),
            notes:        $this->listValue($tags, 'notes'),
            seeAlso:     $this->listValue($tags, 'see_also'),
            aliases:      $this->listValue($tags, 'aliases'),
        );
    }

    /**
     * Parses detached #AI blocks at the bottom of the file into class and method sections. #AI:parseDetachedBlocks
     */
    private function parseDetachedBlocks(): array {
        $source = file_exists($this->file) ? (string) file_get_contents($this->file) : '';
        $blocks = ['class' => [], 'methods' => []];
        $current = null;

        foreach (explode("\n", $source) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '#AI')) {
                continue;
            }

            $parsed = $this->annotations->parseHashAi($trimmed);
            if (isset($parsed['__target'])) {
                $current = (string) $parsed['__target'];
                if ($current === 'class') {
                    $blocks['class'] = [];
                } else {
                    $blocks['methods'][$current] = [];
                }
                continue;
            }

            if ($current === null) {
                continue;
            }

            if ($current === 'class') {
                $blocks['class'] = array_merge($blocks['class'], $parsed);
            } else {
                $blocks['methods'][$current] = array_merge($blocks['methods'][$current] ?? [], $parsed);
            }
        }

        return $blocks;
    }

    /**
     * Returns the raw tag value, unwrapping single-element arrays. #AI:rawValue
     */
    private function rawValue(array $tags, string $key): mixed {
        if (!array_key_exists($key, $tags)) {
            return null;
        }
        $value = $tags[$key];
        if (is_array($value) && array_is_list($value) && count($value) === 1) {
            return $value[0];
        }
        return $value;
    }

    /**
     * Returns a scalar string value from tags, with optional default. #AI:scalarValue
     */
    private function scalarValue(array $tags, string $key, string $default = ''): string {
        $value = $this->rawValue($tags, $key);
        if ($value === null) {
            return $default;
        }
        if (is_array($value)) {
            $first = reset($value);
            return is_scalar($first) ? (string) $first : $default;
        }
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Returns a list value from tags, parsing bracket lists when needed. #AI:listValue
     */
    private function listValue(array $tags, string $key): array {
        $value = $this->rawValue($tags, $key);
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            if ($value === []) {
                return [];
            }
            if (array_is_list($value)) {
                if (count($value) === 1 && is_string($value[0]) && str_starts_with(trim($value[0]), '[')) {
                    return $this->annotations->parseBracketList($value[0]);
                }
                return $value;
            }
            return [$value];
        }
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            return $this->annotations->parseBracketList($value);
        }
        return [(string) $value];
    }

    /**
     * Returns a record value from tags, parsing pipe-delimited records when needed. #AI:recordValue
     */
    private function recordValue(array $tags, string $key): array {
        $value = $this->rawValue($tags, $key);
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value) && !array_is_list($value)) {
            return $value;
        }
        if (is_array($value) && array_is_list($value) && isset($value[0]) && is_array($value[0])) {
            return $value[0];
        }
        if (is_string($value)) {
            return $this->annotations->parseRecord($value);
        }
        return [];
    }

    /**
     * Converts a PhpParser type node to its string representation. #AI:typeToString
     */
    private function typeToString(Node $type): string {
        return match (true) {
            $type instanceof Node\Identifier       => $type->name,
            $type instanceof Node\Name             => (string) $type,
            $type instanceof Node\NullableType     => '?' . $this->typeToString($type->type),
            $type instanceof Node\UnionType        => implode('|', array_map($this->typeToString(...), $type->types)),
            $type instanceof Node\IntersectionType => implode('&', array_map($this->typeToString(...), $type->types)),
            default                                => '',
        };
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Extractor\ClassVisitor
#AI source_path: src/Dev/Docs/Extractor/ClassVisitor.php
#AI title: ClassVisitor
#AI description: Internal AST visitor that captures class-level and method-level @ai.* annotations from PHPDoc, inline comments, and detached #AI blocks.
#AI role: internal AST visitor
#AI layer: dev
#AI badges: [extractor; ast; visitor; internal]
#AI intro: `ClassVisitor` is the AST traversal engine used by `ClassExtractor`. It visits namespace and class nodes, merges annotations from three sources (PHPDoc, inline //, detached #AI blocks), and builds `ExtractedClass` and `ExtractedMethod` value objects.
#AI lifecycle: created per-file by ClassExtractor, single-use
#AI fallback: none — captures first non-anonymous class only
#AI test_seam: use via ClassExtractor; not designed for direct instantiation
#AI invariants: [captures only the first non-anonymous class; anonymous classes skipped; private methods included only when they carry annotations or detached blocks; detached blocks override inline tags]
#AI core_behaviors: [Merges tags from PHPDoc, inline comments, and detached #AI blocks; Builds method signatures from AST type nodes; Falls back to role tag when summary is empty; Parses detached #AI blocks from file bottom]
#AI owns: result (ExtractedClass), fileLines cache
#AI entry_points: [enterNode]
#AI config_reads: []
#AI non_goals: [Does not parse files directly; Does not validate annotations; Does not handle multiple classes per file]
#AI side_effects: [sets $result property on class capture]
#AI flow: enterNode() -> detect Class_ -> parse PHPDoc/inline/detached -> merge tags -> build ExtractedClass + ExtractedMethod[]
#AI lifecycle_steps: [enterNode(); -> namespace tracking; -> Class_ detection; -> PHPDoc or inline parse; -> detached block merge; -> method iteration; -> extractMethod(); -> build ExtractedClass]
#AI section_order: [Traversal; Extraction; Value Helpers; Architecture]
#AI architectural_notes: Not part of the public API — only instantiated by ClassExtractor.

#AI:enterNode
#AI group: Traversal
#AI frequency: high
#AI signature: public function enterNode(Node $node): null
#AI contract: Visits each AST node. Tracks namespace context and captures the first non-anonymous class with all its annotated methods. Merges tags from PHPDoc, inline comments, trailing comment #AI blocks, and detached #AI blocks.

#AI:extractMethod
#AI group: Extraction
#AI frequency: internal
#AI signature: private function extractMethod(ClassMethod $method, string $owner, array $tags): ExtractedMethod
#AI contract: Builds an ExtractedMethod from a ClassMethod AST node and its merged annotation tags. Constructs the method signature string from AST type nodes.

#AI:parseDetachedBlocks
#AI group: Extraction
#AI frequency: internal
#AI signature: private function parseDetachedBlocks(): array
#AI contract: Reads the source file and parses all detached #AI blocks at the bottom into class-level and method-level tag arrays. Uses #AI:{target} headers to determine section boundaries.

#AI:rawValue
#AI group: Value Helpers
#AI frequency: internal
#AI signature: private function rawValue(array $tags, string $key): mixed
#AI contract: Returns the raw tag value for a key, unwrapping single-element list arrays to their scalar value.

#AI:scalarValue
#AI group: Value Helpers
#AI frequency: internal
#AI signature: private function scalarValue(array $tags, string $key, string $default = ''): string
#AI contract: Returns a scalar string from tags with optional default fallback.

#AI:listValue
#AI group: Value Helpers
#AI frequency: internal
#AI signature: private function listValue(array $tags, string $key): array
#AI contract: Returns a list value from tags, parsing bracket-delimited strings when needed.

#AI:recordValue
#AI group: Value Helpers
#AI frequency: internal
#AI signature: private function recordValue(array $tags, string $key): array
#AI contract: Returns a record value from tags, parsing pipe-delimited record strings when needed.

#AI:typeToString
#AI group: Architecture
#AI frequency: internal
#AI signature: private function typeToString(Node $type): string
#AI contract: Converts a PhpParser type node (Identifier, Name, Nullable, Union, Intersection) to its PHP string representation.
