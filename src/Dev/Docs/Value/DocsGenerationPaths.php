<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Value;

/**
 * Resolves CLI flag overrides against config/docs.php defaults for docs generation paths. #AI:class
 *
 * Use when any docs command needs to resolve --source and --output flags
 * against config defaults. Null values preserve config/docs.php defaults.
 *
 * Example:
 *   $paths = DocsGenerationPaths::fromFlags($this->flags);
 *   $json  = $paths->jsonPath();
 *   $scan  = $paths->scanPaths();
 *
 * Testing: Instantiate directly; no static state.
 *
 * #AI:class
 */
final class DocsGenerationPaths {
    /**
     * Stores optional CLI source/output overrides. #AI:__construct
     *
     * @param string|null $sourceDir Override for docs.scan_paths; null preserves config default.
     * @param string|null $outputDir Override for all output paths; null preserves config defaults.
     */
    public function __construct(
        private readonly ?string $sourceDir = null,
        private readonly ?string $outputDir = null,
    ) {}

    /**
     * Builds docs generation paths from CLI flags. #AI:fromFlags
     *
     * @param array $flags CLI flags array; reads 'source' and 'output' keys.
     */
    public static function fromFlags(array $flags): self {
        return new self(
            sourceDir: self::flagString($flags, 'source'),
            outputDir: self::flagString($flags, 'output'),
        );
    }

    /**
     * Returns explicit source override or config scan_paths. #AI:scanPaths
     *
     * Relative source paths resolve from basePath().
     *
     * @return string[] Directories to scan.
     */
    public function scanPaths(): array {
        if ($this->sourceDir !== null) {
            return [$this->resolvePath($this->sourceDir)];
        }

        return config('docs.scan_paths', []);
    }

    /**
     * Returns true when --source was supplied. #AI:hasSourceOverride
     */
    public function hasSourceOverride(): bool {
        return $this->sourceDir !== null;
    }

    /**
     * Returns llm.json output path, respecting --output override. #AI:jsonPath
     */
    public function jsonPath(): string {
        if ($this->outputDir !== null) {
            return $this->outputPath('llm.json');
        }

        return config('docs.output.json', basePath('llm.json'));
    }

    /**
     * Returns llm.md output path, respecting --output override. #AI:llmMdPath
     */
    public function llmMdPath(): string {
        if ($this->outputDir !== null) {
            return $this->outputPath('llm.md');
        }

        return config('docs.output.llm_md', basePath('llm.md'));
    }

    /**
     * Returns MDX output directory, respecting --output override. #AI:mdxDir
     */
    public function mdxDir(): string {
        if ($this->outputDir !== null) {
            return $this->resolvePath($this->outputDir);
        }

        return config('docs.output.mdx_dir', basePath('docs/src/content/docs/api'));
    }

    private static function flagString(array $flags, string $name): ?string {
        $value = $flags[$name] ?? null;
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    private function outputPath(string $file): string {
        return rtrim($this->resolvePath((string) $this->outputDir), '/\\') . '/' . $file;
    }

    private function resolvePath(string $path): string {
        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '/\\');
        }

        return basePath($path);
    }

    private function isAbsolutePath(string $path): bool {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Value\DocsGenerationPaths
#AI source_path: src/Dev/Docs/Value/DocsGenerationPaths.php
#AI title: DocsGenerationPaths
#AI description: Resolves CLI --source and --output flag overrides against config/docs.php defaults for docs generation paths.
#AI role: path resolver value object
#AI layer: dev
#AI badges: [value; docs; paths; cli]
#AI intro: `DocsGenerationPaths` encapsulates the resolution logic for docs generation file paths. It merges CLI flag overrides with config/docs.php defaults, handling relative-to-absolute path resolution.
#AI lifecycle: instantiated per-command via fromFlags(), immutable after construction
#AI fallback: falls back to config/docs.php values when flags are absent
#AI test_seam: instantiate directly with constructor args
#AI invariants: [null flags preserve config defaults; relative paths resolve from basePath(); empty string flags treated as null]
#AI core_behaviors: [Resolves scanPaths from --source or config; Resolves json_path, llmMdPath, mdx_dir from --output or config; Detects absolute vs relative paths]
#AI owns: source_dir, outputDir overrides
#AI entry_points: [fromFlags; scanPaths; hasSourceOverride; json_path; llmMdPath; mdx_dir]
#AI config_reads: [docs.scan_paths; docs.output.json; docs.output.llm_md; docs.output.mdx_dir]
#AI non_goals: [Does not create directories; Does not validate that paths exist on disk]
#AI side_effects: []
#AI flow: fromFlags(flags) -> new self(source, output) -> scanPaths/json_path/llmMdPath/mdx_dir -> config fallback
#AI lifecycle_steps: [fromFlags(); -> extract source/output from flags; -> construct; -> resolve paths on demand with config fallback]
#AI section_order: [Construction; Path Resolution; Architecture]
#AI architectural_notes: Immutable value object — all resolution happens on access, not at construction time.

#AI:__construct
#AI group: Construction
#AI frequency: high
#AI signature: public function __construct(?string $sourceDir = null, ?string $outputDir = null)
#AI contract: Stores optional CLI source/output overrides. Null values preserve config/docs.php defaults.
#AI param_details: [{name: $sourceDir | type: ?string | required: false | desc: Override for docs.scan_paths; null preserves config default.}; {name: $outputDir | type: ?string | required: false | desc: Override for all output paths; null preserves config defaults.}]

#AI:fromFlags
#AI group: Construction
#AI frequency: high
#AI signature: public static function fromFlags(array $flags): self
#AI contract: Builds a DocsGenerationPaths from CLI flags, reading 'source' and 'output' keys. Empty strings are treated as null.
#AI param_details: [{name: $flags | type: array | required: true | desc: CLI flags array from command.}]

#AI:scanPaths
#AI group: Path Resolution
#AI frequency: high
#AI signature: public function scanPaths(): array
#AI contract: Returns the explicit source override as a single-element array, or falls back to config('docs.scan_paths'). Relative paths resolve from basePath().
#AI return_detail: {type: string[] | desc: Directories to scan for .php files.}

#AI:hasSourceOverride
#AI group: Path Resolution
#AI frequency: medium
#AI signature: public function hasSourceOverride(): bool
#AI contract: Returns true when --source was supplied, indicating the caller should use scanPaths() instead of the default config scan.
#AI return_detail: {type: bool | desc: True if --source flag was provided.}

#AI:jsonPath
#AI group: Path Resolution
#AI frequency: high
#AI signature: public function jsonPath(): string
#AI contract: Returns the llm.json output path. When --output is set, returns DIR/llm.json; otherwise falls back to config.
#AI return_detail: {type: string | desc: Absolute or relative path to llm.json.}

#AI:llmMdPath
#AI group: Path Resolution
#AI frequency: medium
#AI signature: public function llmMdPath(): string
#AI contract: Returns the llm.md output path. When --output is set, returns DIR/llm.md; otherwise falls back to config.
#AI return_detail: {type: string | desc: Absolute or relative path to llm.md.}

#AI:mdxDir
#AI group: Path Resolution
#AI frequency: medium
#AI signature: public function mdxDir(): string
#AI contract: Returns the MDX output directory. When --output is set, returns the resolved output dir; otherwise falls back to config.
#AI return_detail: {type: string | desc: Directory path for MDX file output.}
