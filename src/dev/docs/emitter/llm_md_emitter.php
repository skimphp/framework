<?php declare(strict_types=1);

namespace skim\dev\docs\emitter;

// Reads decoded llm.json data and writes llm.md to the repo root.
// llm.md is the file developers paste into LLMs when no tooling is available.
// No framework dependencies — plain PHP only.
class llm_md_emitter {
    /**
     * @ai-contract accepts decoded llm.json array and output path
     * @ai-contract writes structured Markdown: architecture summary, class index,
     *              key invariants, non-goals, lifecycle map
     * @ai-contract when framework_llm_md is set and the file exists, prepends its content
     *              as a ## Framework section before the ## Application section
     * @ai-contract throws \RuntimeException if the file cannot be written
     */
    public function emit(array $data, string $output_path, ?string $framework_llm_md = null): void {
        $classes   = $data['classes'] ?? [];
        $generated = $data['generated_at'] ?? date('c');

        $lines   = [];
        $lines[] = '# LLM context';
        $lines[] = '';
        $lines[] = '> Auto-generated from source annotations. Do not edit manually.  ';
        $lines[] = "> Generated: {$generated}";
        $lines[] = '';

        if ($framework_llm_md !== null && file_exists($framework_llm_md)) {
            $framework_content = file_get_contents($framework_llm_md);
            if ($framework_content !== false) {
                $lines[] = '## Framework (skim)';
                $lines[] = '';
                $lines[] = '> sourced from vendor/skim/framework/llm.md';
                $lines[] = '';
                $lines[] = $framework_content;
                $lines[] = '';
                $lines[] = '---';
                $lines[] = '';
                $lines[] = '## Application';
                $lines[] = '';
                $lines[] = '> sourced from scan_paths';
                $lines[] = '';
            }
        }

        $lines[] = '## Architecture';
        $lines[] = '';
        $lines[] = 'SKIM is a PHP 8.5+ micro-framework. Zero external dependencies except `nikic/fast-route` (routing) and `pestphp/pest` (testing). Docker-ready with MySQL, PostgreSQL, Redis. Snake_case everywhere, no template engine, no YAML, no APCu. Static facades (`db::`, `cache::`, `session::`) are testable via `set_driver()` / `reset()`.';
        $lines[] = '';
        $lines[] = 'Request lifecycle: `app::run()` → `request::from_globals()` → `router::dispatch()` → middleware pipeline → controller → `response::send()`';
        $lines[] = '';

        $lines[] = '## Class index';
        $lines[] = '';
        $lines[] = '| Class | Namespace | File | Summary |';
        $lines[] = '|-------|-----------|------|---------|';
        foreach ($classes as $class) {
            $short_file = preg_replace('#^.+/src/#', 'src/', $class['file'] ?? '') ?? ($class['file'] ?? '');
            $summary    = str_replace(['|', "\n"], [' ', ' '], $class['summary'] ?? '');
            $lines[]    = "| `{$class['class_name']}` | `{$class['namespace']}` | `{$short_file}` | {$summary} |";
        }
        $lines[] = '';

        $lines[] = '## Key invariants';
        $lines[] = '';
        foreach ($classes as $class) {
            $class_invariants = [];
            foreach ($class['methods'] ?? [] as $method) {
                foreach ($method['invariants'] ?? [] as $inv) {
                    $class_invariants[] = "- `{$method['name']}`: {$inv}";
                }
            }
            if ($class_invariants !== []) {
                $lines[] = "### `{$class['class_name']}`";
                $lines   = array_merge($lines, $class_invariants);
                $lines[] = '';
            }
        }

        $lines[] = '## Non-goals';
        $lines[] = '';
        foreach ($classes as $class) {
            $class_non_goals = [];
            foreach ($class['methods'] ?? [] as $method) {
                foreach ($method['non_goals'] ?? [] as $ng) {
                    $class_non_goals[] = "- `{$method['name']}`: {$ng}";
                }
            }
            if ($class_non_goals !== []) {
                $lines[] = "### `{$class['class_name']}`";
                $lines   = array_merge($lines, $class_non_goals);
                $lines[] = '';
            }
        }

        $lines[] = '## Lifecycle map';
        $lines[] = '';
        foreach ($classes as $class) {
            if (($class['lifecycle'] ?? '') !== '') {
                $lines[] = "- **`{$class['class_name']}`**: {$class['lifecycle']}";
            }
            foreach ($class['methods'] ?? [] as $method) {
                if (($method['lifecycle'] ?? '') !== '') {
                    $lines[] = "  - `{$method['name']}`: {$method['lifecycle']}";
                }
            }
        }
        $lines[] = '';

        $lines[] = '## Method contracts';
        $lines[] = '';
        foreach ($classes as $class) {
            $method_lines = [];
            foreach ($class['methods'] ?? [] as $method) {
                if (($method['contracts'] ?? []) === []) {
                    continue;
                }
                $method_lines[] = "#### `{$method['name']}`";
                $method_lines[] = '';
                $method_lines[] = "```php";
                $method_lines[] = $method['signature'] ?? '';
                $method_lines[] = "```";
                foreach ($method['contracts'] as $contract) {
                    $method_lines[] = "- {$contract}";
                }
                if (($method['throws'] ?? []) !== []) {
                    $method_lines[] = '';
                    foreach ($method['throws'] as $throws) {
                        $method_lines[] = "- throws: {$throws}";
                    }
                }
                $method_lines[] = '';
            }
            if ($method_lines !== []) {
                $lines[] = "### `{$class['class_name']}`";
                $lines[] = '';
                $lines   = array_merge($lines, $method_lines);
            }
        }

        $content = implode("\n", $lines) . "\n";

        $dir = dirname($output_path);
        $ancestor = $dir;
        while ($ancestor !== '/' && $ancestor !== '.' && !file_exists($ancestor)) {
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                break;
            }
            $ancestor = $parent;
        }
        if (file_exists($ancestor) && !is_dir($ancestor)) {
            throw new \RuntimeException("llm_md_emitter: cannot create directory {$dir} because {$ancestor} is a file");
        }

        if (!is_dir($dir) && !@mkdir($dir, 0755, recursive: true)) {
            throw new \RuntimeException("llm_md_emitter: cannot create directory {$dir}");
        }

        if (file_put_contents($output_path, $content) === false) {
            throw new \RuntimeException("llm_md_emitter: cannot write to {$output_path}");
        }
    }
}
