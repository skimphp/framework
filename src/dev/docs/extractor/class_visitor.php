<?php declare(strict_types=1);

namespace skim\dev\docs\extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeVisitorAbstract;
use skim\dev\docs\value\extracted_class;
use skim\dev\docs\value\extracted_method;

// Internal AST visitor used by class_extractor.
// Captures the first non-anonymous class found in the traversed file.
// Not part of the public API — only instantiated by class_extractor.
class class_visitor extends NodeVisitorAbstract {
    public ?extracted_class $result = null;
    private string $current_namespace = '';
    private ?array $file_lines = null;

    public function __construct(
        private readonly annotation_parser $annotations,
        private readonly string $file,
    ) {}

    private function get_file_lines(): array {
        if ($this->file_lines === null) {
            $content = file_exists($this->file) ? file_get_contents($this->file) : '';
            $this->file_lines = array_merge([''], explode("\n", $content));
        }
        return $this->file_lines;
    }

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

    public function enterNode(Node $node): null {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->current_namespace = $node->name !== null ? (string) $node->name : '';
            return null;
        }

        if (!$node instanceof Class_ || $node->isAnonymous() || $this->result !== null) {
            return null;
        }

        $class_doc = (string) ($node->getDocComment()?->getText() ?? '');
        if ($class_doc !== '') {
            $tags    = $this->annotations->parse($class_doc);
            $summary = $this->annotations->extract_summary($class_doc);
        } else {
            $inline  = $this->get_preceding_inline_comments($node);
            $tags    = $this->annotations->parse_inline($inline);
            $summary = $tags['summary'] ?? '';
            unset($tags['summary']);
        }

        $owner = $this->current_namespace . '\\' . (string) $node->name;

        $methods = [];
        foreach ($node->getMethods() as $method) {
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
                if (!$has_owner && !$has_lifecycle) {
                    continue;
                }
            }

            if ($summary_m !== '') {
                if (!isset($tags_m['contract'])) {
                    $tags_m['contract'] = [];
                }
                array_unshift($tags_m['contract'], $summary_m);
            }

            $methods[] = $this->extract_method($method, $owner, $tags_m);
        }

        $this->result = new extracted_class(
            class_name: (string) $node->name,
            namespace:  $this->current_namespace,
            file:       $this->file,
            summary:    $summary,
            lifecycle:  $tags['lifecycle'][0] ?? '',
            owner:      $tags['owner'][0] ?? '',
            methods:    $methods,
        );
        return null;
    }

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

        return new extracted_method(
            name:         (string) $method->name,
            signature:    $signature,
            owner:        $owner,
            contracts:    $tags['contract']    ?? [],
            invariants:   $tags['invariant']   ?? [],
            non_goals:    $tags['non_goal']    ?? [],
            side_effects: $tags['side_effect'] ?? [],
            lifecycle:    $tags['lifecycle'][0] ?? '',
            perf:         $tags['perf'][0]      ?? '',
            throws:       $tags['throws']       ?? [],
        );
    }
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
