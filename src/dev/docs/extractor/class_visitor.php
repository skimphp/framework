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

    public function __construct(
        private readonly annotation_parser $annotations,
        private readonly string $file,
    ) {}

    public function enterNode(Node $node): null {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->current_namespace = $node->name !== null ? (string) $node->name : '';
            return null;
        }

        if (!$node instanceof Class_ || $node->isAnonymous() || $this->result !== null) {
            return null;
        }

        $class_doc = (string) ($node->getDocComment()?->getText() ?? '');
        $tags      = $this->annotations->parse($class_doc);
        $summary   = $this->annotations->extract_summary($class_doc);
        $owner     = $this->current_namespace . '\\' . (string) $node->name;

        $methods = [];
        foreach ($node->getMethods() as $method) {
            if (!$method->isPublic()) {
                continue;
            }
            $methods[] = $this->extract_method($method, $owner);
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

    private function extract_method(ClassMethod $method, string $owner): extracted_method {
        $doc  = (string) ($method->getDocComment()?->getText() ?? '');
        $tags = $this->annotations->parse($doc);

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
        $visibility = $method->isStatic() ? 'public static' : 'public';
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
