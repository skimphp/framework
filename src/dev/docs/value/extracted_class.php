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
        /** @var extracted_method[] */
        public array  $methods      = [],
    ) {}

    /**
     * @ai-contract serializes to plain associative array suitable for json_encode
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
            'methods'      => array_map(fn(extracted_method $m) => $m->to_array(), $this->methods),
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
     * @ai-contract returns count of public methods that have at least one @ai.* tag
     */
    public function annotated_method_count(): int {
        return count(array_filter(
            $this->methods,
            fn(extracted_method $m) => $m->contracts !== []
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
