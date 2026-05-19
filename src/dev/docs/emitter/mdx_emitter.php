<?php declare(strict_types=1);

namespace skim\dev\docs\emitter;

// Reads decoded llm.json data and generates one MDX file per class
// under docs/src/content/docs/api/ for the Starlight (Astro) docs site.
// No framework dependencies — plain PHP only.
class mdx_emitter {
    /**
     * @ai-contract accepts decoded llm.json array and output directory path
     * @ai-contract creates one MDX file per class: {class_name}.mdx
     * @ai-contract creates output directory if it does not exist
     * @ai-contract returns count of files written
     * @ai-contract throws \RuntimeException on write failure
     */
    public function emit(array $data, string $output_dir): int {
        $classes = $data['classes'] ?? [];

        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, recursive: true);
        }

        $count = 0;
        foreach ($classes as $class) {
            $content = $this->render_class($class);
            $path    = $output_dir . '/' . $class['class_name'] . '.mdx';
            if (file_put_contents($path, $content) === false) {
                throw new \RuntimeException("mdx_emitter: cannot write {$path}");
            }
            $count++;
        }
        return $count;
    }

    /**
     * @ai-contract renders a single class entry to Starlight-compatible MDX string
     * @ai-contract includes: title, summary, lifecycle, methods with signature+contracts+invariants,
     *              non-goals section, side effects section, owner badge
     */
    private function render_class(array $class): string {
        $name      = $class['class_name'] ?? '';
        $namespace = $class['namespace']  ?? '';
        $summary   = $class['summary']    ?? '';
        $lifecycle = $class['lifecycle']  ?? '';
        $owner     = $class['owner']      ?? '';

        $lines   = [];
        $lines[] = '---';
        $lines[] = "title: {$name}";
        $lines[] = "description: \"" . addslashes($summary) . '"';
        $lines[] = '---';
        $lines[] = '';

        if ($summary !== '') {
            $lines[] = $summary;
            $lines[] = '';
        }

        $file = $class['file'] ?? '';

        $lines[] = '## Details';
        $lines[] = '';
        $lines[] = "| Field | Value |";
        $lines[] = "|-------|-------|";
        $lines[] = "| **Namespace** | `{$namespace}` |";
        if ($file !== '') {
            $rel = preg_replace('#^.*/src/#', 'src/', $file) ?? $file;
            $lines[] = "| **File** | `{$rel}` |";
        }
        if ($lifecycle !== '') {
            $lines[] = "| **Lifecycle** | {$lifecycle} |";
        }
        if ($owner !== '') {
            $lines[] = "| **Owner** | {$owner} |";
        }
        $lines[] = '';

        $methods = $class['methods'] ?? [];
        if ($methods !== []) {
            $lines[] = '## Methods';
            $lines[] = '';
            foreach ($methods as $method) {
                $lines = array_merge($lines, $this->render_method($method));
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function render_method(array $method): array {
        $lines   = [];
        $lines[] = "### `{$method['name']}`";
        $lines[] = '';
        $lines[] = '```php';
        $lines[] = $method['signature'] ?? '';
        $lines[] = '```';
        $lines[] = '';

        if (($method['contracts'] ?? []) !== []) {
            $lines[] = '**Contracts**';
            $lines[] = '';
            foreach ($method['contracts'] as $c) {
                $lines[] = "- {$c}";
            }
            $lines[] = '';
        }

        if (($method['invariants'] ?? []) !== []) {
            $lines[] = '**Invariants**';
            $lines[] = '';
            foreach ($method['invariants'] as $inv) {
                $lines[] = "- {$inv}";
            }
            $lines[] = '';
        }

        if (($method['non_goals'] ?? []) !== []) {
            $lines[] = '**Non-goals**';
            $lines[] = '';
            foreach ($method['non_goals'] as $ng) {
                $lines[] = "- {$ng}";
            }
            $lines[] = '';
        }

        if (($method['side_effects'] ?? []) !== []) {
            $lines[] = '**Side effects**';
            $lines[] = '';
            foreach ($method['side_effects'] as $se) {
                $lines[] = "- {$se}";
            }
            $lines[] = '';
        }

        if (($method['throws'] ?? []) !== []) {
            $lines[] = '**Throws**';
            $lines[] = '';
            foreach ($method['throws'] as $t) {
                $lines[] = "- `{$t}`";
            }
            $lines[] = '';
        }

        if (($method['lifecycle'] ?? '') !== '') {
            $lines[] = "**Lifecycle:** {$method['lifecycle']}";
            $lines[] = '';
        }

        if (($method['perf'] ?? '') !== '') {
            $lines[] = "**Performance:** {$method['perf']}";
            $lines[] = '';
        }

        return $lines;
    }
}
