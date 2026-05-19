<?php declare(strict_types=1);

namespace skim\dev\docs\value;

// Readonly value object representing a single extracted class.
// No framework dependencies — plain PHP only.
// Populated by class_extractor from file path + @ai.* docblock tags.
readonly class extracted_class {
    /**
     * @ai-contract immutable value object, created once by class_extractor, never mutated
     * @ai-invariant methods is an array of extracted_method; empty when class has no public methods
     */
    public function __construct(
        public string $class_name,
        public string $namespace,
        public string $file,
        public string $summary   = '',
        public string $lifecycle = '',
        public string $owner     = '',
        /** @var extracted_method[] */
        public array  $methods   = [],
    ) {}

    /**
     * @ai-contract serializes to plain associative array suitable for json_encode
     */
    public function to_array(): array {
        return [
            'class_name' => $this->class_name,
            'namespace'  => $this->namespace,
            'file'       => $this->file,
            'summary'    => $this->summary,
            'lifecycle'  => $this->lifecycle,
            'owner'      => $this->owner,
            'methods'    => array_map(fn(extracted_method $m) => $m->to_array(), $this->methods),
        ];
    }

    /**
     * @ai-contract returns count of public methods that have at least one @ai.* tag
     */
    public function annotated_method_count(): int {
        return count(array_filter(
            $this->methods,
            fn(extracted_method $m) => $m->contracts !== []
                || $m->invariants !== []
                || $m->non_goals  !== [],
        ));
    }
}
