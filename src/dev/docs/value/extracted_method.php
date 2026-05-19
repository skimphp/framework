<?php declare(strict_types=1);

namespace skim\dev\docs\value;

// Readonly value object representing a single extracted public method.
// No framework dependencies — plain PHP only.
// Populated by class_extractor from @ai.* docblock tags.
readonly class extracted_method {
    /**
     * @ai-contract immutable value object, created once by class_extractor, never mutated
     * @ai-invariant all array fields default to empty array when no tags present
     */
    public function __construct(
        public string $name,
        public string $signature,
        public string $owner,
        public array  $contracts    = [],
        public array  $invariants   = [],
        public array  $non_goals    = [],
        public array  $side_effects = [],
        public string $lifecycle    = '',
        public string $perf         = '',
        public array  $throws       = [],
    ) {}

    /**
     * @ai-contract serializes to plain associative array suitable for json_encode
     */
    public function to_array(): array {
        return [
            'name'         => $this->name,
            'signature'    => $this->signature,
            'owner'        => $this->owner,
            'contracts'    => $this->contracts,
            'invariants'   => $this->invariants,
            'non_goals'    => $this->non_goals,
            'side_effects' => $this->side_effects,
            'lifecycle'    => $this->lifecycle,
            'perf'         => $this->perf,
            'throws'       => $this->throws,
        ];
    }
}
