<?php declare(strict_types=1);

namespace skim\dev\docs\extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use skim\dev\docs\value\extracted_class;
use skim\dev\docs\value\extracted_method;

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
class class_visitor extends NodeVisitorAbstract {
    public ?extracted_class $result = null;
    private string $current_namespace = '';
    private ?array $file_lines = null;

    public function __construct(
        private readonly annotation_parser $annotations,
        private readonly string $file,
    ) {}

    /**
     * Returns cached file lines, reading from disk on first call. #AI:get_file_lines
     */
    private function get_file_lines(): array {
        if ($this->file_lines === null) {
            $content = file_exists($this->file) ? file_get_contents($this->file) : '';
            $this->file_lines = array_merge([''], explode("\n", $content));
        }
        return $this->file_lines;
    }

    /**
     * Collects consecutive // comment lines immediately preceding a node. #AI:get_preceding_inline_comments
     */
    private function get_preceding_inline_comments(Node $node): string {
        $lines = $this->get_file_lines();
        $start_line = $node->getStartLine();
        
        $comment_lines = [];
        for ($i = $start_line - 1; $i >= 1; $i--) {
            $line = $lines[$i] ?? '';
            if (preg_match('/^\s*\/\//', $line)) {
                $comment_lines[] = $line;
            } else {
                break;
            }
        }
        
        $comment_lines = array_reverse($comment_lines);
        return implode("\n", $comment_lines);
    }

    /**
     * Visits AST nodes, capturing the first non-anonymous class. #AI:enterNode
     *
     * Merges tags from PHPDoc, inline comments, detached #AI blocks, and
     * comment-trailing #AI blocks. Builds extracted_class with all methods.
     */
    public function enterNode(Node $node): null {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->current_namespace = $node->name !== null ? (string) $node->name : '';
            return null;
        }

        if (!$node instanceof Class_ || $node->isAnonymous() || $this->result !== null) {
            return null;
        }

        $class_doc = (string) ($node->getDocComment()?->getText() ?? '');
        $tags = [];
        $summary = '';
        if ($class_doc !== '') {
            $tags    = $this->annotations->parse($class_doc);
            $summary = $this->annotations->extract_summary($class_doc);
        } else {
            $inline  = $this->get_preceding_inline_comments($node);
            $tags    = $this->annotations->parse_inline($inline);
            $summary = $tags['summary'] ?? '';
            unset($tags['summary']);
        }

        foreach ($node->getComments() as $comment) {
            $comment_text = $comment->getText();
            if (str_contains($comment_text, '#AI')) {
                $hash_tags = $this->annotations->parse_hash_ai($comment_text);
                foreach ($hash_tags as $k => $v) {
                    $tags[$k][] = $v;
                }
            }
        }

        if ($summary === '' && !empty($tags['role'])) {
            $summary = $tags['role'][0];
        }

        $owner = $this->current_namespace . '\\' . (string) $node->name;

        $detached = $this->parse_detached_blocks();
        if (($detached['class'] ?? []) !== []) {
            $tags = array_merge($tags, $detached['class']);
        }

        $methods = [];
        $method_nodes = [];
        foreach ($node->getMethods() as $method) {
            $method_nodes[(string) $method->name] = $method;
            $doc = (string) ($method->getDocComment()?->getText() ?? '');
            if ($doc !== '') {
                $tags_m = $this->annotations->parse($doc);
                $summary_m = $this->annotations->extract_summary($doc);
            } else {
                $inline_m = $this->get_preceding_inline_comments($method);
                $tags_m = $this->annotations->parse_inline($inline_m);
                $summary_m = $tags_m['summary'] ?? '';
                unset($tags_m['summary']);
            }

            if (!$method->isPublic()) {
                $has_owner = !empty($tags_m['owner']);
                $has_lifecycle = !empty($tags_m['lifecycle']);
                $has_detached = isset($detached['methods'][(string) $method->name]);
                if (!$has_owner && !$has_lifecycle && !$has_detached) {
                    continue;
                }
            }

            if (isset($detached['methods'][(string) $method->name])) {
                $tags_m = array_merge($tags_m, $detached['methods'][(string) $method->name]);
            }

            if ($summary_m !== '' && !isset($detached['methods'][(string) $method->name])) {
                if (!isset($tags_m['contract'])) {
                    $tags_m['contract'] = [];
                }
                if (!is_array($tags_m['contract'])) {
                    $tags_m['contract'] = [$tags_m['contract']];
                }
                array_unshift($tags_m['contract'], $summary_m);
            }

            $methods[] = $this->extract_method($method, $owner, $tags_m);
        }

        foreach ($detached['methods'] ?? [] as $name => $detached_tags) {
            if (isset($method_nodes[$name])) {
                continue;
            }
        }

        $invariants = array_merge($this->list_value($tags, 'invariant'), $this->list_value($tags, 'invariants'));
        $non_goals = array_merge($this->list_value($tags, 'non_goal'), $this->list_value($tags, 'non_goals'));
        $side_effects = array_merge($this->list_value($tags, 'side_effect'), $this->list_value($tags, 'side_effects'));

        $this->result = new extracted_class(
            class_name:   (string) $node->name,
            namespace:    $this->current_namespace,
            file:         $this->file,
            summary:      $summary,
            lifecycle:    $this->scalar_value($tags, 'lifecycle'),
            owner:        $this->scalar_value($tags, 'role', $this->scalar_value($tags, 'owner')),
            layer:        $this->scalar_value($tags, 'layer'),
            owns:         $this->list_value($tags, 'owns'),
            entry_points: $this->list_value($tags, 'entry_points'),
            config_reads: $this->list_value($tags, 'config_reads'),
            invariants:   $invariants,
            side_effects: $side_effects,
            non_goals:    $non_goals,
            symbol:       $this->scalar_value($tags, 'symbol', $owner),
            title:        $this->scalar_value($tags, 'title', (string) $node->name),
            description:  $this->scalar_value($tags, 'description', $summary),
            source_path:  $this->scalar_value($tags, 'source_path'),
            badges:       $this->list_value($tags, 'badges'),
            intro:        $this->scalar_value($tags, 'intro'),
            fallback:     $this->scalar_value($tags, 'fallback'),
            test_seam:    $this->scalar_value($tags, 'test_seam'),
            drivers:      $this->list_value($tags, 'drivers'),
            core_behaviors: $this->list_value($tags, 'core_behaviors'),
            warnings:     array_merge($this->list_value($tags, 'warning'), $this->list_value($tags, 'warnings')),
            notes:        $this->list_value($tags, 'notes'),
            scope_items:  $this->list_value($tags, 'scope_items'),
            flow:         $this->scalar_value($tags, 'flow'),
            lifecycle_steps: $this->list_value($tags, 'lifecycle_steps'),
            section_order: $this->list_value($tags, 'section_order'),
            architectural_notes: $this->scalar_value($tags, 'architectural_notes'),
            methods:      $methods,
        );
        return null;
    }

    /**
     * Builds an extracted_method from a ClassMethod node and its merged tags. #AI:extract_method
     */
    private function extract_method(ClassMethod $method, string $owner, array $tags): extracted_method {
        $params = [];
        foreach ($method->params as $param) {
            $type     = $param->type !== null ? $this->type_to_string($param->type) : '';
            $variadic = $param->variadic ? '...' : '';
            $name     = $variadic . '$' . (string) $param->var->name;
            $params[] = ($type !== '' ? $type . ' ' : '') . $name;
        }
        $return_type = $method->returnType !== null
            ? ': ' . $this->type_to_string($method->returnType)
            : '';
            
        $vis_name = match(true) {
            $method->isPrivate() => 'private',
            $method->isProtected() => 'protected',
            default => 'public',
        };
        $visibility = $method->isStatic() ? $vis_name . ' static' : $vis_name;
        $signature  = "{$visibility} function {$method->name}("
            . implode(', ', $params) . "){$return_type}";

        $invariants = array_merge($this->list_value($tags, 'invariant'), $this->list_value($tags, 'invariants'));
        $non_goals = array_merge($this->list_value($tags, 'non_goal'), $this->list_value($tags, 'non_goals'));
        $side_effects = array_merge($this->list_value($tags, 'side_effect'), $this->list_value($tags, 'side_effects'));
        $signature = $this->scalar_value($tags, 'signature', $signature);
        $contracts = $this->list_value($tags, 'contract');
        $contract = $contracts[0] ?? '';

        return new extracted_method(
            name:         (string) $method->name,
            signature:    $signature,
            owner:        $owner,
            group:        $this->scalar_value($tags, 'group'),
            frequency:    $this->scalar_value($tags, 'frequency'),
            contracts:    $contracts,
            invariants:   $invariants,
            non_goals:    $non_goals,
            side_effects: $side_effects,
            inputs:       $this->list_value($tags, 'input'),
            returns:      $this->scalar_value($tags, 'returns'),
            reads:        $this->list_value($tags, 'reads'),
            mutates:      $this->list_value($tags, 'mutates'),
            calls:        $this->list_value($tags, 'calls'),
            throws:       $this->list_value($tags, 'throws'),
            warnings:     array_merge($this->list_value($tags, 'warning'), $this->list_value($tags, 'warnings')),
            examples:     $this->list_value($tags, 'example'),
            lifecycle:    $this->scalar_value($tags, 'lifecycle'),
            perf:         $this->scalar_value($tags, 'perf'),
            contract:     $contract,
            param_details: $this->list_value($tags, 'param_details'),
            return_detail: $this->record_value($tags, 'return_detail'),
            throws_details: $this->list_value($tags, 'throws_details'),
            notes:        $this->list_value($tags, 'notes'),
        );
    }

    /**
     * Parses detached #AI blocks at the bottom of the file into class and method sections. #AI:parse_detached_blocks
     */
    private function parse_detached_blocks(): array {
        $source = file_exists($this->file) ? (string) file_get_contents($this->file) : '';
        $blocks = ['class' => [], 'methods' => []];
        $current = null;

        foreach (explode("\n", $source) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '#AI')) {
                continue;
            }

            $parsed = $this->annotations->parse_hash_ai($trimmed);
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
     * Returns the raw tag value, unwrapping single-element arrays. #AI:raw_value
     */
    private function raw_value(array $tags, string $key): mixed {
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
     * Returns a scalar string value from tags, with optional default. #AI:scalar_value
     */
    private function scalar_value(array $tags, string $key, string $default = ''): string {
        $value = $this->raw_value($tags, $key);
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
     * Returns a list value from tags, parsing bracket lists when needed. #AI:list_value
     */
    private function list_value(array $tags, string $key): array {
        $value = $this->raw_value($tags, $key);
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            if ($value === []) {
                return [];
            }
            if (array_is_list($value)) {
                if (count($value) === 1 && is_string($value[0]) && str_starts_with(trim($value[0]), '[')) {
                    return $this->annotations->parse_bracket_list($value[0]);
                }
                return $value;
            }
            return [$value];
        }
        if (is_string($value) && str_starts_with(trim($value), '[')) {
            return $this->annotations->parse_bracket_list($value);
        }
        return [(string) $value];
    }

    /**
     * Returns a record value from tags, parsing pipe-delimited records when needed. #AI:record_value
     */
    private function record_value(array $tags, string $key): array {
        $value = $this->raw_value($tags, $key);
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
            return $this->annotations->parse_record($value);
        }
        return [];
    }

    /**
     * Converts a PhpParser type node to its string representation. #AI:type_to_string
     */
    private function type_to_string(Node $type): string {
        return match (true) {
            $type instanceof Node\Identifier       => $type->name,
            $type instanceof Node\Name             => (string) $type,
            $type instanceof Node\NullableType     => '?' . $this->type_to_string($type->type),
            $type instanceof Node\UnionType        => implode('|', array_map($this->type_to_string(...), $type->types)),
            $type instanceof Node\IntersectionType => implode('&', array_map($this->type_to_string(...), $type->types)),
            default                                => '',
        };
    }
}

#AI:class
#AI symbol: skim\dev\docs\extractor\class_visitor
#AI source_path: src/dev/docs/extractor/class_visitor.php
#AI title: class_visitor
#AI description: Internal AST visitor that captures class-level and method-level @ai.* annotations from PHPDoc, inline comments, and detached #AI blocks.
#AI role: internal AST visitor
#AI layer: dev
#AI badges: [extractor; ast; visitor; internal]
#AI intro: `class_visitor` is the AST traversal engine used by `class_extractor`. It visits namespace and class nodes, merges annotations from three sources (PHPDoc, inline //, detached #AI blocks), and builds `extracted_class` and `extracted_method` value objects.
#AI lifecycle: created per-file by class_extractor, single-use
#AI fallback: none — captures first non-anonymous class only
#AI test_seam: use via class_extractor; not designed for direct instantiation
#AI invariants: [captures only the first non-anonymous class; anonymous classes skipped; private methods included only when they carry annotations or detached blocks; detached blocks override inline tags]
#AI core_behaviors: [Merges tags from PHPDoc, inline comments, and detached #AI blocks; Builds method signatures from AST type nodes; Falls back to role tag when summary is empty; Parses detached #AI blocks from file bottom]
#AI owns: result (extracted_class), file_lines cache
#AI entry_points: [enterNode]
#AI config_reads: []
#AI non_goals: [Does not parse files directly; Does not validate annotations; Does not handle multiple classes per file]
#AI side_effects: [sets $result property on class capture]
#AI flow: enterNode() -> detect Class_ -> parse PHPDoc/inline/detached -> merge tags -> build extracted_class + extracted_method[]
#AI lifecycle_steps: [enterNode(); -> namespace tracking; -> Class_ detection; -> PHPDoc or inline parse; -> detached block merge; -> method iteration; -> extract_method(); -> build extracted_class]
#AI section_order: [Traversal; Extraction; Value Helpers; Architecture]
#AI architectural_notes: Not part of the public API — only instantiated by class_extractor.

#AI:enterNode
#AI group: Traversal
#AI frequency: high
#AI signature: public function enterNode(Node $node): null
#AI contract: Visits each AST node. Tracks namespace context and captures the first non-anonymous class with all its annotated methods. Merges tags from PHPDoc, inline comments, trailing comment #AI blocks, and detached #AI blocks.

#AI:extract_method
#AI group: Extraction
#AI frequency: internal
#AI signature: private function extract_method(ClassMethod $method, string $owner, array $tags): extracted_method
#AI contract: Builds an extracted_method from a ClassMethod AST node and its merged annotation tags. Constructs the method signature string from AST type nodes.

#AI:parse_detached_blocks
#AI group: Extraction
#AI frequency: internal
#AI signature: private function parse_detached_blocks(): array
#AI contract: Reads the source file and parses all detached #AI blocks at the bottom into class-level and method-level tag arrays. Uses #AI:{target} headers to determine section boundaries.

#AI:raw_value
#AI group: Value Helpers
#AI frequency: internal
#AI signature: private function raw_value(array $tags, string $key): mixed
#AI contract: Returns the raw tag value for a key, unwrapping single-element list arrays to their scalar value.

#AI:scalar_value
#AI group: Value Helpers
#AI frequency: internal
#AI signature: private function scalar_value(array $tags, string $key, string $default = ''): string
#AI contract: Returns a scalar string from tags with optional default fallback.

#AI:list_value
#AI group: Value Helpers
#AI frequency: internal
#AI signature: private function list_value(array $tags, string $key): array
#AI contract: Returns a list value from tags, parsing bracket-delimited strings when needed.

#AI:record_value
#AI group: Value Helpers
#AI frequency: internal
#AI signature: private function record_value(array $tags, string $key): array
#AI contract: Returns a record value from tags, parsing pipe-delimited record strings when needed.

#AI:type_to_string
#AI group: Architecture
#AI frequency: internal
#AI signature: private function type_to_string(Node $type): string
#AI contract: Converts a PhpParser type node (Identifier, Name, Nullable, Union, Intersection) to its PHP string representation.
