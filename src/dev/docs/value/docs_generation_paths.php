<?php declare(strict_types=1);

namespace skim\dev\docs\value;

final class docs_generation_paths {
    /**
     * @ai-contract stores optional CLI source/output overrides for docs generation
     * @ai-contract null values preserve config/docs.php defaults
     */
    public function __construct(
        private readonly ?string $source_dir = null,
        private readonly ?string $output_dir = null,
    ) {}

    /**
     * @ai-contract builds docs generation paths from CLI flags
     * @ai-contract supports --source=DIR and --output=DIR overrides
     */
    public static function from_flags(array $flags): self {
        return new self(
            source_dir: self::flag_string($flags, 'source'),
            output_dir: self::flag_string($flags, 'output'),
        );
    }

    /**
     * @ai-contract returns explicit source override or docs.scan_paths config
     * @ai-contract relative source paths resolve from base_path()
     *
     * @return string[]
     */
    public function scan_paths(): array {
        if ($this->source_dir !== null) {
            return [$this->resolve_path($this->source_dir)];
        }

        return config('docs.scan_paths', []);
    }

    /**
     * @ai-contract returns true when --source was supplied
     */
    public function has_source_override(): bool {
        return $this->source_dir !== null;
    }

    /**
     * @ai-contract returns llm.json output path
     * @ai-contract when --output=DIR is supplied, returns DIR/llm.json
     */
    public function json_path(): string {
        if ($this->output_dir !== null) {
            return $this->output_path('llm.json');
        }

        return config('docs.output.json', base_path('llm.json'));
    }

    /**
     * @ai-contract returns llm.md output path
     * @ai-contract when --output=DIR is supplied, returns DIR/llm.md
     */
    public function llm_md_path(): string {
        if ($this->output_dir !== null) {
            return $this->output_path('llm.md');
        }

        return config('docs.output.llm_md', base_path('llm.md'));
    }

    /**
     * @ai-contract returns MDX output directory
     * @ai-contract when --output=DIR is supplied, writes MDX files directly into DIR
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
