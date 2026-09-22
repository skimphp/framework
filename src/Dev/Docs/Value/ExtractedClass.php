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
 *   $array = $class->toArray();  // for json_encode
 *
 * Testing: Instantiate directly with test data.
 *
 * #AI:class
 */
readonly class ExtractedClass {
    /**
     * Immutable value object — created once by class_extractor, never mutated. #AI:__construct
     *
     * @param string             $className          Short class name.
     * @param string             $namespace            Full namespace.
     * @param string             $file                 Absolute file path.
     * @param \Skim\Dev\Docs\Value\ExtractedMethod[] $methods Public methods with annotations.
     */
    public function __construct(
        public string $className,
        public string $namespace,
        public string $file,
        public string $summary      = '',
        public string $lifecycle    = '',
        public string $owner        = '',
        public string $layer        = '',
        public array  $owns         = [],
        public array  $entryPoints = [],
        public array  $configReads = [],
        public array  $invariants   = [],
        public array  $sideEffects = [],
        public array  $nonGoals    = [],
        public string $symbol = '',
        public string $title = '',
        public string $description = '',
        public string $sourcePath = '',
        public array $badges = [],
        public string $intro = '',
        public string $fallback = '',
        public string $testSeam = '',
        public array $drivers = [],
        public array $coreBehaviors = [],
        public array $warnings = [],
        public array $notes = [],
        public array $scopeItems = [],
        public string $flow = '',
        public array $lifecycleSteps = [],
        public array $sectionOrder = [],
        public string $architecturalNotes = '',
        public array $examples = [],
        public array $seeAlso = [],
        /** @var extracted_method[] */
        public array  $methods      = [],
    ) {}

    /**
     * Serializes to a plain associative array suitable for json_encode. #AI:toArray
     *
     * Falls back to namespace\class_name for symbol, class_name for title,
     * and derives source_path from the file path when explicit values are empty.
     */
    public function toArray(): array {
        $symbol = $this->symbol !== '' ? $this->symbol : trim($this->namespace . '\\' . $this->className, '\\');
        $title = $this->title !== '' ? $this->title : $this->className;
        $description = $this->description !== '' ? $this->description : $this->summary;
        $sourcePath = $this->sourcePath !== '' ? $this->sourcePath : $this->sourcePathFromFile();

        return [
            'symbol'       => $symbol,
            'title'        => $title,
            'description'  => $description,
            'role'         => $this->owner,
            'layer'        => $this->layer,
            'source_path'  => $sourcePath,
            'badges'       => $this->badges,
            'intro'        => $this->intro !== '' ? $this->intro : $this->summary,
            'lifecycle'    => $this->lifecycle,
            'fallback'     => $this->fallback,
            'test_seam'    => $this->testSeam,
            'drivers'      => $this->drivers,
            'invariants'   => $this->invariants,
            'core_behaviors' => $this->coreBehaviors,
            'warnings'     => $this->warnings,
            'notes'        => $this->notes,
            'scope_items'  => $this->scopeItems,
            'owns'         => $this->owns,
            'entry_points' => $this->entryPoints,
            'config_reads' => $this->configReads,
            'non_goals'    => $this->nonGoals,
            'side_effects' => $this->sideEffects,
            'flow'         => $this->flow,
            'lifecycle_steps' => $this->lifecycleSteps,
            'section_order' => $this->sectionOrder,
            'architectural_notes' => $this->architecturalNotes,
            'examples'     => $this->examples,
            'see_also'     => $this->seeAlso,
            'methods'      => array_map(fn(\Skim\Dev\Docs\Value\ExtractedMethod $m) => $m->toArray(), $this->methods),
            'class_name'   => $this->className,
            'namespace'    => $this->namespace,
            'file'         => $this->file,
            'summary'      => $this->summary,
            'lifecycle'    => $this->lifecycle,
            'owner'        => $this->owner,
            'layer'        => $this->layer,
            'owns'         => $this->owns,
            'entry_points' => $this->entryPoints,
            'config_reads' => $this->configReads,
            'invariants'   => $this->invariants,
            'side_effects' => $this->sideEffects,
            'non_goals'    => $this->nonGoals,
        ];
    }

    private function sourcePathFromFile(): string {
        $root = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR;
        if (str_starts_with($this->file, $root)) {
            return str_replace('\\', '/', substr($this->file, strlen($root)));
        }

        return str_replace('\\', '/', ltrim($this->file, '/'));
    }

    /**
     * Returns count of public methods that have at least one @ai.* tag. #AI:annotatedMethodCount
     */
    public function annotatedMethodCount(): int {
        return count(array_filter(
            $this->methods,
            fn(\Skim\Dev\Docs\Value\ExtractedMethod $m) => $m->contracts !== []
                || $m->contract !== ''
                || $m->paramDetails !== []
                || $m->returnDetail !== []
                || $m->throwsDetails !== []
                || $m->notes !== []
                || $m->invariants !== []
                || $m->nonGoals  !== []
                || $m->sideEffects !== []
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
#AI source_path: src/Dev/Docs/Value/ExtractedClass.php
#AI title: ExtractedClass
#AI description: Readonly value object representing a single extracted class with all annotation metadata from @ai.* tags.
#AI role: data carrier
#AI layer: dev
#AI badges: [value; readonly; docs; no-framework-deps]
#AI intro: `ExtractedClass` is the immutable data carrier between the AST extraction pipeline and all downstream emitters. It holds every annotation field extracted from a single PHP class file.
#AI lifecycle: created once by ClassExtractor, never mutated, serialized by emitters
#AI fallback: toArray() falls back to namespace\className for symbol, className for title
#AI test_seam: instantiate directly with test data
#AI invariants: [readonly — never mutated after construction; methods array contains ExtractedMethod instances; toArray() is JSON-safe]
#AI core_behaviors: [Holds all annotation fields from class-level and method-level tags; Serializes to JSON-safe array via toArray(); Derives source_path from file path when not explicitly set]
#AI owns: methods (ExtractedMethod[])
#AI entry_points: [toArray; annotatedMethodCount]
#AI config_reads: []
#AI non_goals: [Does not extract itself; Does not validate annotations; Does not write output]
#AI side_effects: []
#AI flow: ClassExtractor -> new ExtractedClass(...) -> toArray() -> JsonEmitter
#AI lifecycle_steps: [constructed by ClassExtractor; -> held by ProjectScanner; -> serialized by JsonEmitter.to_array()]
#AI section_order: [Construction; Serialization; Architecture]
#AI architectural_notes: No framework dependencies — plain PHP only.

#AI:__construct
#AI group: Construction
#AI frequency: high
#AI signature: public function __construct(string $className, string $namespace, string $file, ...)
#AI contract: Creates an immutable value object with all extracted annotation fields. All fields except className, namespace, and file default to empty strings or empty arrays.

#AI:toArray
#AI group: Serialization
#AI frequency: high
#AI signature: public function toArray(): array
#AI contract: Serializes to a plain associative array suitable for json_encode. Falls back to derived values for symbol, title, description, intro, and source_path when explicit values are empty.
#AI return_detail: {type: array | desc: JSON-safe associative array with all class and method data.}

#AI:annotatedMethodCount
#AI group: Serialization
#AI frequency: low
#AI signature: public function annotatedMethodCount(): int
#AI contract: Returns the count of public methods that have at least one @ai.* tag across any annotation field.
#AI return_detail: {type: int | desc: Number of annotated methods.}
