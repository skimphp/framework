<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Value;

/**
 * Readonly value object representing a single extracted public method with all its annotation metadata. #AI:class
 *
 * Use as the method-level data carrier within extracted_class.
 * Created once by class_visitor, never mutated. No framework dependencies.
 *
 * Example:
 *   // Accessed via extracted_class->methods
 *   foreach ($class->methods as $method) {
 *       $array = $method->toArray();
 *   }
 *
 * Testing: Instantiate directly with test data.
 *
 * #AI:class
 */
readonly class ExtractedMethod {
    /**
     * Immutable value object — created once by class_visitor, never mutated. #AI:__construct
     *
     * @param string $name      Method name.
     * @param string $signature Full PHP signature string.
     */
    public function __construct(
        public string $name,
        public string $signature,
        public string $owner = '',
        public string $group = '',
        public string $frequency = '',
        public array $contracts = [],
        public array $invariants = [],
        public array $nonGoals = [],
        public array $sideEffects = [],
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
        public array $paramDetails = [],
        public array $returnDetail = [],
        public array $throwsDetails = [],
        public array $notes = [],
        public array $seeAlso = [],
        public array $aliases = [],
    ) {}

    /**
     * Serializes to a plain associative array suitable for json_encode. #AI:toArray
     *
     * Falls back to contracts[0] for contract, and converts legacy throws
     * to throws_details format when throws_details is empty.
     */
    public function toArray(): array {
        $contract = $this->contract !== '' ? $this->contract : ($this->contracts[0] ?? '');
        $return_detail = $this->returnDetail !== [] ? $this->returnDetail : ($this->returns !== '' ? ['type' => '', 'desc' => $this->returns] : []);
        $throws_details = $this->throwsDetails;
        if ($throws_details === [] && $this->throws !== []) {
            $throws_details = array_map(fn(string $throw): array => ['type' => $throw, 'desc' => ''], $this->throws);
        }

        return [
            'name'          => $this->name,
            'group'         => $this->group,
            'frequency'     => $this->frequency,
            'signature'     => $this->signature,
            'contract'      => $contract,
            'param_details' => $this->paramDetails,
            'return_detail' => $return_detail,
            'throws_details' => $throws_details,
            'contracts'    => $this->contracts,
            'invariants'   => $this->invariants,
            'non_goals'    => $this->nonGoals,
            'side_effects' => $this->sideEffects,
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
            'see_also'     => $this->seeAlso,
            'aliases'      => $this->aliases,
        ];
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Value\ExtractedMethod
#AI source_path: src/dev/docs/value/extracted_method.php
#AI title: extracted_method
#AI description: Readonly value object representing a single extracted public method with all annotation metadata.
#AI role: data carrier
#AI layer: dev
#AI badges: [value; readonly; docs; no-framework-deps]
#AI intro: `extracted_method` is the method-level data carrier within `extracted_class`. It holds every annotation field extracted from a single method's PHPDoc, inline comments, and detached #AI blocks.
#AI lifecycle: created once by class_visitor, held inside extracted_class, serialized by emitters
#AI fallback: to_array() falls back to contracts[0] for contract; converts legacy throws to throws_details
#AI test_seam: instantiate directly with test data
#AI invariants: [readonly — never mutated after construction; all array fields default to empty; to_array() is JSON-safe]
#AI core_behaviors: [Holds all annotation fields from method-level tags; Serializes to JSON-safe array via to_array(); Backward-compatible with legacy contracts/throws fields]
#AI owns: none — pure value object
#AI entry_points: [to_array]
#AI config_reads: []
#AI non_goals: [Does not extract itself; Does not validate annotations]
#AI side_effects: []
#AI flow: class_visitor -> new extracted_method(...) -> extracted_class.methods -> to_array()
#AI lifecycle_steps: [constructed by class_visitor; -> held in extracted_class.methods; -> serialized by to_array()]
#AI section_order: [Construction; Serialization; Architecture]
#AI architectural_notes: No framework dependencies — plain PHP only.

#AI:__construct
#AI group: Construction
#AI frequency: high
#AI signature: public function __construct(string $name, string $signature, ...)
#AI contract: Creates an immutable value object with all extracted method annotation fields. Only name and signature are required; all other fields default to empty.

#AI:toArray
#AI group: Serialization
#AI frequency: high
#AI signature: public function toArray(): array
#AI contract: Serializes to a plain associative array suitable for json_encode. Falls back to contracts[0] for contract and converts legacy throws to throws_details format.
#AI return_detail: {type: array | desc: JSON-safe associative array with all method annotation data.}
