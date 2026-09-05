<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Value;

/**
 * Readonly value object representing a single extracted class with all its annotation metadata. #AI:class
 *
 * Use as the data carrier between the extraction pipeline and emitters.
 * Created once by class_extractor, never mutated. No framework dependencies.
 *
 * Example:
 *   $class = $extractor->extract('/path/to/file.php');
 *   $array = $class->to_array();  // for json_encode
 *
 * Testing: Instantiate directly with test data.
 *
 * #AI:class
 */
readonly class ExtractedClass {
    /**
     * Immutable value object — created once by class_extractor, never mutated. #AI:__construct
     *
     * @param string             $class_name          Short class name.
     * @param string             $namespace            Full namespace.
     * @param string             $file                 Absolute file path.
     * @param \Skim\Dev\Docs\Value\ExtractedMethod[] $methods Public methods with annotations.
     */
    public function __construct(
        public string $class_name,
        public string $namespace,
        public string $file,
        public string $summary      = '',
        public string $lifecycle    = '',
        public string $owner        = '',
        public string $layer        = '',
        public array  $owns         = [],
        public array  $entry_points = [],
        public array  $config_reads = [],
        public array  $invariants   = [],
        public array  $side_effects = [],
        public array  $non_goals    = [],
        public string $symbol = '',
        public string $title = '',
        public string $description = '',
        public string $source_path = '',
        public array $badges = [],
        public string $intro = '',
        public string $fallback = '',
        public string $test_seam = '',
        public array $drivers = [],
        public array $core_behaviors = [],
        public array $warnings = [],
        public array $notes = [],
        public array $scope_items = [],
        public string $flow = '',
        public array $lifecycle_steps = [],
        public array $section_order = [],
        public string $architectural_notes = '',
        public array $examples = [],
        public array $see_also = [],
        /** @var extracted_method[] */
        public array  $methods      = [],
    ) {}

    /**
     * Serializes to a plain associative array suitable for json_encode. #AI:to_array
     *
     * Falls back to namespace\class_name for symbol, class_name for title,
     * and derives source_path from the file path when explicit values are empty.
     */
    public function to_array(): array {
        $symbol = $this->symbol !== '' ? $this->symbol : trim($this->namespace . '\\' . $this->class_name, '\\');
        $title = $this->title !== '' ? $this->title : $this->class_name;
        $description = $this->description !== '' ? $this->description : $this->summary;
        $source_path = $this->source_path !== '' ? $this->source_path : $this->source_path_from_file();

        return [
            'symbol'       => $symbol,
            'title'        => $title,
            'description'  => $description,
            'role'         => $this->owner,
            'layer'        => $this->layer,
            'source_path'  => $source_path,
            'badges'       => $this->badges,
            'intro'        => $this->intro !== '' ? $this->intro : $this->summary,
            'lifecycle'    => $this->lifecycle,
            'fallback'     => $this->fallback,
            'test_seam'    => $this->test_seam,
            'drivers'      => $this->drivers,
            'invariants'   => $this->invariants,
            'core_behaviors' => $this->core_behaviors,
            'warnings'     => $this->warnings,
            'notes'        => $this->notes,
            'scope_items'  => $this->scope_items,
            'owns'         => $this->owns,
            'entry_points' => $this->entry_points,
            'config_reads' => $this->config_reads,
            'non_goals'    => $this->non_goals,
            'side_effects' => $this->side_effects,
            'flow'         => $this->flow,
            'lifecycle_steps' => $this->lifecycle_steps,
            'section_order' => $this->section_order,
            'architectural_notes' => $this->architectural_notes,
            'examples'     => $this->examples,
            'see_also'     => $this->see_also,
            'methods'      => array_map(fn(\Skim\Dev\Docs\Value\ExtractedMethod $m) => $m->to_array(), $this->methods),
            'class_name'   => $this->class_name,
            'namespace'    => $this->namespace,
            'file'         => $this->file,
            'summary'      => $this->summary,
            'lifecycle'    => $this->lifecycle,
            'owner'        => $this->owner,
            'layer'        => $this->layer,
            'owns'         => $this->owns,
            'entry_points' => $this->entry_points,
            'config_reads' => $this->config_reads,
            'invariants'   => $this->invariants,
            'side_effects' => $this->side_effects,
            'non_goals'    => $this->non_goals,
        ];
    }

    private function source_path_from_file(): string {
        $root = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR;
        if (str_starts_with($this->file, $root)) {
            return str_replace('\\', '/', substr($this->file, strlen($root)));
        }

        return str_replace('\\', '/', ltrim($this->file, '/'));
    }

    /**
     * Returns count of public methods that have at least one @ai.* tag. #AI:annotated_method_count
     */
    public function annotated_method_count(): int {
        return count(array_filter(
            $this->methods,
            fn(\Skim\Dev\Docs\Value\ExtractedMethod $m) => $m->contracts !== []
                || $m->contract !== ''
                || $m->param_details !== []
                || $m->return_detail !== []
                || $m->throws_details !== []
                || $m->notes !== []
                || $m->invariants !== []
                || $m->non_goals  !== []
                || $m->side_effects !== []
                || $m->inputs       !== []
                || $m->returns      !== ''
                || $m->reads        !== []
                || $m->mutates      !== []
                || $m->calls        !== []
                || $m->throws       !== []
                || $m->warnings     !== []
                || $m->examples     !== []
                || $m->lifecycle    !== ''
                || $m->perf         !== ''
                || $m->group        !== ''
                || $m->frequency    !== '',
        ));
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Value\ExtractedClass
#AI source_path: src/dev/docs/value/extracted_class.php
#AI title: extracted_class
#AI description: Readonly value object representing a single extracted class with all annotation metadata from @ai.* tags.
#AI role: data carrier
#AI layer: dev
#AI badges: [value; readonly; docs; no-framework-deps]
#AI intro: `extracted_class` is the immutable data carrier between the AST extraction pipeline and all downstream emitters. It holds every annotation field extracted from a single PHP class file.
#AI lifecycle: created once by class_extractor, never mutated, serialized by emitters
#AI fallback: to_array() falls back to namespace\class_name for symbol, class_name for title
#AI test_seam: instantiate directly with test data
#AI invariants: [readonly — never mutated after construction; methods array contains extracted_method instances; to_array() is JSON-safe]
#AI core_behaviors: [Holds all annotation fields from class-level and method-level tags; Serializes to JSON-safe array via to_array(); Derives source_path from file path when not explicitly set]
#AI owns: methods (extracted_method[])
#AI entry_points: [to_array; annotated_method_count]
#AI config_reads: []
#AI non_goals: [Does not extract itself; Does not validate annotations; Does not write output]
#AI side_effects: []
#AI flow: class_extractor -> new extracted_class(...) -> to_array() -> json_emitter
#AI lifecycle_steps: [constructed by class_extractor; -> held by project_scanner; -> serialized by json_emitter.to_array()]
#AI section_order: [Construction; Serialization; Architecture]
#AI architectural_notes: No framework dependencies — plain PHP only.

#AI:__construct
#AI group: Construction
#AI frequency: high
#AI signature: public function __construct(string $class_name, string $namespace, string $file, ...)
#AI contract: Creates an immutable value object with all extracted annotation fields. All fields except class_name, namespace, and file default to empty strings or empty arrays.

#AI:to_array
#AI group: Serialization
#AI frequency: high
#AI signature: public function to_array(): array
#AI contract: Serializes to a plain associative array suitable for json_encode. Falls back to derived values for symbol, title, description, intro, and source_path when explicit values are empty.
#AI return_detail: {type: array | desc: JSON-safe associative array with all class and method data.}

#AI:annotated_method_count
#AI group: Serialization
#AI frequency: low
#AI signature: public function annotated_method_count(): int
#AI contract: Returns the count of public methods that have at least one @ai.* tag across any annotation field.
#AI return_detail: {type: int | desc: Number of annotated methods.}
