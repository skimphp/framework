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
        public string $owner = '',
        public string $group = '',
        public string $frequency = '',
        public array $contracts = [],
        public array $invariants = [],
        public array $non_goals = [],
        public array $side_effects = [],
        public array $inputs = [],
        public string $returns = '',
        public array $reads = [],
        public array $mutates = [],
        public array $calls = [],
        public array $throws = [],
        public array $warnings = [],
        public array $examples = [],
        public string $lifecycle = '',
        public string $perf = '',
        public string $contract = '',
        public array $param_details = [],
        public array $return_detail = [],
        public array $throws_details = [],
        public array $notes = [],
    ) {}

    /**
     * @ai-contract serializes to plain associative array suitable for json_encode
     */
    public function to_array(): array {
        $contract = $this->contract !== '' ? $this->contract : ($this->contracts[0] ?? '');
        $return_detail = $this->return_detail !== [] ? $this->return_detail : ($this->returns !== '' ? ['type' => '', 'desc' => $this->returns] : []);
        $throws_details = $this->throws_details;
        if ($throws_details === [] && $this->throws !== []) {
            $throws_details = array_map(fn(string $throw): array => ['type' => $throw, 'desc' => ''], $this->throws);
        }

        return [
            'name'          => $this->name,
            'group'         => $this->group,
            'frequency'     => $this->frequency,
            'signature'     => $this->signature,
            'contract'      => $contract,
            'param_details' => $this->param_details,
            'return_detail' => $return_detail,
            'throws_details' => $throws_details,
            'contracts'    => $this->contracts,
            'invariants'   => $this->invariants,
            'non_goals'    => $this->non_goals,
            'side_effects' => $this->side_effects,
            'inputs'       => $this->inputs,
            'returns'      => $this->returns,
            'reads'        => $this->reads,
            'mutates'      => $this->mutates,
            'calls'        => $this->calls,
            'throws'       => $this->throws,
            'warnings'     => $this->warnings,
            'notes'        => $this->notes,
            'examples'     => $this->examples,
            'lifecycle'    => $this->lifecycle,
            'perf'         => $this->perf,
        ];
    }
}
