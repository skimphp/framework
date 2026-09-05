<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Value;

/**
 * Resolves CLI flag overrides against config/docs.php defaults for docs generation paths. #AI:class
 *
 * Use when any docs command needs to resolve --source and --output flags
 * against config defaults. Null values preserve config/docs.php defaults.
 *
 * Example:
 *   $paths = docs_generation_paths::from_flags($this->flags);
 *   $json  = $paths->json_path();
 *   $scan  = $paths->scan_paths();
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
final class DocsGenerationPaths {
    /**
     * Stores optional CLI source/output overrides. #AI:__construct
     *
     * @param string|null $source_dir Override for docs.scan_paths; null preserves config default.
     * @param string|null $output_dir Override for all output paths; null preserves config defaults.
     */
    public function __construct(
        private readonly ?string $source_dir = null,
        private readonly ?string $output_dir = null,
    ) {}

    /**
     * Builds docs generation paths from CLI flags. #AI:from_flags
     *
     * @param array $flags CLI flags array; reads 'source' and 'output' keys.
     */
    public static function from_flags(array $flags): self {
        return new self(
            source_dir: self::flag_string($flags, 'source'),
            output_dir: self::flag_string($flags, 'output'),
        );
    }

    /**
     * Returns explicit source override or config scan_paths. #AI:scan_paths
     *
     * Relative source paths resolve from base_path().
     *
     * @return string[] Directories to scan.
     */
    public function scan_paths(): array {
        if ($this->source_dir !== null) {
            return [$this->resolve_path($this->source_dir)];
        }

        return config('docs.scan_paths', []);
    }

    /**
     * Returns true when --source was supplied. #AI:has_source_override
     */
    public function has_source_override(): bool {
        return $this->source_dir !== null;
    }

    /**
     * Returns llm.json output path, respecting --output override. #AI:json_path
     */
    public function json_path(): string {
        if ($this->output_dir !== null) {
            return $this->output_path('llm.json');
        }

        return config('docs.output.json', base_path('llm.json'));
    }

    /**
     * Returns llm.md output path, respecting --output override. #AI:llm_md_path
     */
    public function llm_md_path(): string {
        if ($this->output_dir !== null) {
            return $this->output_path('llm.md');
        }

        return config('docs.output.llm_md', base_path('llm.md'));
    }

    /**
     * Returns MDX output directory, respecting --output override. #AI:mdx_dir
     */
    public function mdx_dir(): string {
        if ($this->output_dir !== null) {
            return $this->resolve_path($this->output_dir);
        }

        return config('docs.output.mdx_dir', base_path('docs/src/content/docs/api'));
    }

    private static function flag_string(array $flags, string $name): ?string {
        $value = $flags[$name] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private function output_path(string $file): string {
        return rtrim($this->resolve_path((string) $this->output_dir), '/\\') . '/' . $file;
    }

    private function resolve_path(string $path): string {
        if ($this->is_absolute_path($path)) {
            return rtrim($path, '/\\');
        }

        return base_path($path);
    }

    private function is_absolute_path(string $path): bool {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Value\DocsGenerationPaths
#AI source_path: src/dev/docs/value/docs_generation_paths.php
#AI title: docs_generation_paths
#AI description: Resolves CLI --source and --output flag overrides against config/docs.php defaults for docs generation paths.
#AI role: path resolver value object
#AI layer: dev
#AI badges: [value; docs; paths; cli]
#AI intro: `docs_generation_paths` encapsulates the resolution logic for docs generation file paths. It merges CLI flag overrides with config/docs.php defaults, handling relative-to-absolute path resolution.
#AI lifecycle: instantiated per-command via from_flags(), immutable after construction
#AI fallback: falls back to config/docs.php values when flags are absent
#AI test_seam: instantiate directly with constructor args
#AI invariants: [null flags preserve config defaults; relative paths resolve from base_path(); empty string flags treated as null]
#AI core_behaviors: [Resolves scan_paths from --source or config; Resolves json_path, llm_md_path, mdx_dir from --output or config; Detects absolute vs relative paths]
#AI owns: source_dir, output_dir overrides
#AI entry_points: [from_flags; scan_paths; has_source_override; json_path; llm_md_path; mdx_dir]
#AI config_reads: [docs.scan_paths; docs.output.json; docs.output.llm_md; docs.output.mdx_dir]
#AI non_goals: [Does not create directories; Does not validate that paths exist on disk]
#AI side_effects: []
#AI flow: from_flags(flags) -> new self(source, output) -> scan_paths/json_path/llm_md_path/mdx_dir -> config fallback
#AI lifecycle_steps: [from_flags(); -> extract source/output from flags; -> construct; -> resolve paths on demand with config fallback]
#AI section_order: [Construction; Path Resolution; Architecture]
#AI architectural_notes: Immutable value object — all resolution happens on access, not at construction time.

#AI:__construct
#AI group: Construction
#AI frequency: high
#AI signature: public function __construct(?string $source_dir = null, ?string $output_dir = null)
#AI contract: Stores optional CLI source/output overrides. Null values preserve config/docs.php defaults.
#AI param_details: [{name: $source_dir | type: ?string | required: false | desc: Override for docs.scan_paths; null preserves config default.}; {name: $output_dir | type: ?string | required: false | desc: Override for all output paths; null preserves config defaults.}]

#AI:from_flags
#AI group: Construction
#AI frequency: high
#AI signature: public static function from_flags(array $flags): self
#AI contract: Builds a docs_generation_paths from CLI flags, reading 'source' and 'output' keys. Empty strings are treated as null.
#AI param_details: [{name: $flags | type: array | required: true | desc: CLI flags array from command.}]

#AI:scan_paths
#AI group: Path Resolution
#AI frequency: high
#AI signature: public function scan_paths(): array
#AI contract: Returns the explicit source override as a single-element array, or falls back to config('docs.scan_paths'). Relative paths resolve from base_path().
#AI return_detail: {type: string[] | desc: Directories to scan for .php files.}

#AI:has_source_override
#AI group: Path Resolution
#AI frequency: medium
#AI signature: public function has_source_override(): bool
#AI contract: Returns true when --source was supplied, indicating the caller should use scan_paths() instead of the default config scan.
#AI return_detail: {type: bool | desc: True if --source flag was provided.}

#AI:json_path
#AI group: Path Resolution
#AI frequency: high
#AI signature: public function json_path(): string
#AI contract: Returns the llm.json output path. When --output is set, returns DIR/llm.json; otherwise falls back to config.
#AI return_detail: {type: string | desc: Absolute or relative path to llm.json.}

#AI:llm_md_path
#AI group: Path Resolution
#AI frequency: medium
#AI signature: public function llm_md_path(): string
#AI contract: Returns the llm.md output path. When --output is set, returns DIR/llm.md; otherwise falls back to config.
#AI return_detail: {type: string | desc: Absolute or relative path to llm.md.}

#AI:mdx_dir
#AI group: Path Resolution
#AI frequency: medium
#AI signature: public function mdx_dir(): string
#AI contract: Returns the MDX output directory. When --output is set, returns the resolved output dir; otherwise falls back to config.
#AI return_detail: {type: string | desc: Directory path for MDX file output.}
