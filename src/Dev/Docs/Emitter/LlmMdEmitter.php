<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Emitter;

/**
 * Reads decoded llm.json data and writes compact grouped Markdown for LLM context windows. #AI:class
 *
 * Use when producing a single Markdown file that can be pasted into any LLM.
 * Groups methods by section_order, renders compact signatures, and optionally
 * prepends framework-level llm.md for full API context.
 *
 * Example:
 *   $data = (new JsonEmitter())->load('llm.json');
 *   (new LlmMdEmitter())->emit($data, 'llm.md', frameworkLlmMd: 'vendor/skim/framework/llm.md');
 *
 * Testing: Instantiate directly; operates on filesystem paths.
 *
 * #AI:class
 */
class LlmMdEmitter {
    /**
     * Writes compact class sections grouped by section_order to a Markdown file. #AI:emit
     *
     * Prepends framework llm.md content when provided and the file exists.
     *
     * @param array       $data              Decoded llm.json array.
     * @param string      $outputPath        Filesystem path for the output Markdown file.
     * @param string|null $frameworkLlmMd  Optional path to framework-level llm.md to prepend.
     *
     * @throws \RuntimeException If the file cannot be written.
     */
    public function emit(array $data, string $outputPath, ?string $frameworkLlmMd = null): void {
        $classes = $data['classes'] ?? [];

        // No timestamp — output must be deterministic so CI can verify freshness by hash.
        $lines = ['# LLM context', '', '> Auto-generated from source annotations. Do not edit manually.', ''];

        if ($frameworkLlmMd !== null && file_exists($frameworkLlmMd)) {
            $frameworkContent = file_get_contents($frameworkLlmMd);
            if ($frameworkContent !== false) {
                $lines[] = '## Framework (skim)';
                $lines[] = '';
                $lines[] = $frameworkContent;
                $lines[] = '';
                $lines[] = '---';
                $lines[] = '';
            }
        }

        foreach ($classes as $class) {
            $lines = array_merge($lines, $this->renderClass($class));
        }

        $this->write($outputPath, implode("\n", $lines) . "\n");
    }

    private function renderClass(array $class): array {
        $symbol = $class['symbol'] ?? trim(($class['namespace'] ?? '') . '\\' . ($class['class_name'] ?? ''), '\\');
        $title = $class['title'] ?? ($class['class_name'] ?? 'class');
        $description = $class['description'] ?? ($class['summary'] ?? '');
        $lines = ["## `{$symbol}` — {$title}", ''];

        if ($description !== '') {
            $lines[] = "> {$this->clean($description)}";
            $lines[] = '';
        }
        if (($class['badges'] ?? []) !== []) {
            $lines[] = implode(' ', array_map(fn(string $badge): string => "`{$badge}`", $class['badges']));
            $lines[] = '';
        }

        $meta = [];
        foreach ([['Source', $class['source_path'] ?? $class['file'] ?? ''], ['Layer', $class['layer'] ?? ''], ['Lifecycle', $class['lifecycle'] ?? '']] as [$label, $value]) {
            if ($value !== '') {
                $meta[] = "**{$label}:** `{$value}`";
            }
        }
        if ($meta !== []) {
            $lines[] = implode(' · ', $meta);
            $lines[] = '';
        }
        if (($class['intro'] ?? '') !== '') {
            $lines[] = $this->clean($class['intro']);
            $lines[] = '';
        }

        $this->appendList($lines, 'Core Behavior', $class['core_behaviors'] ?? $class['invariants'] ?? []);
        $this->appendScopeTable($lines, $class['scope_items'] ?? []);
        $this->appendList($lines, 'Warnings', $class['warnings'] ?? [], prefix: '⚠ ');

        $methods = $class['methods'] ?? [];
        $groups = $this->groupMethods($methods, $class['section_order'] ?? []);
        foreach ($groups as $group => $groupMethods) {
            $lines[] = "### {$group}";
            $lines[] = '';
            foreach ($groupMethods as $method) {
                $lines = array_merge($lines, $this->renderMethod($method));
            }
        }

        return $lines;
    }

    private function renderMethod(array $method): array {
        $lines = ["#### `{$this->compactSignature($method['signature'] ?? $method['name'] ?? '')}`"];
        if (($method['contract'] ?? '') !== '') {
            $lines[] = $this->clean($method['contract']);
        } elseif (($method['contracts'] ?? []) !== []) {
            $lines[] = $this->clean($method['contracts'][0]);
        }
        foreach ($method['invariants'] ?? [] as $invariant) {
            $lines[] = '- **Invariant:** ' . $this->clean((string) $invariant);
        }
        foreach ($method['non_goals'] ?? [] as $nonGoal) {
            $lines[] = '- **Non-goal:** ' . $this->clean((string) $nonGoal);
        }

        foreach ($method['param_details'] ?? [] as $param) {
            $required = ($param['required'] ?? false) === true ? 'required' : 'optional';
            $name = ltrim((string) ($param['name'] ?? ''), '$');
            $type = $param['type'] ?? 'mixed';
            $desc = $this->clean((string) ($param['desc'] ?? ''));
            $lines[] = "- `\${$name}: {$type}` ({$required}) — {$desc}";
        }
        if (($method['return_detail'] ?? []) !== []) {
            $return = $method['return_detail'];
            $lines[] = '- **Returns** `' . ($return['type'] ?? 'mixed') . '` — ' . $this->clean((string) ($return['desc'] ?? ''));
        }
        foreach ($method['throws_details'] ?? [] as $throw) {
            $lines[] = '- **Throws** `' . ($throw['type'] ?? '') . '` — ' . $this->clean((string) ($throw['desc'] ?? ''));
        }
        foreach ($method['warnings'] ?? [] as $warning) {
            $lines[] = '- ⚠ ' . $this->clean((string) $warning);
        }
        foreach ($method['side_effects'] ?? [] as $effect) {
            $lines[] = '- **Side effect:** ' . $this->clean((string) $effect);
        }
        foreach ($method['notes'] ?? [] as $note) {
            $lines[] = '- **Note:** ' . $this->clean((string) $note);
        }
        $lines[] = '';
        return $lines;
    }

    private function appendList(array &$lines, string $title, array $items, string $prefix = ''): void {
        if ($items === []) {
            return;
        }
        $lines[] = "### {$title}";
        foreach ($items as $item) {
            $lines[] = '- ' . $prefix . $this->clean((string) $item);
        }
        $lines[] = '';
    }

    private function appendScopeTable(array &$lines, array $items): void {
        if ($items === []) {
            return;
        }
        $lines[] = '### Drivers';
        $lines[] = '| Driver | Mutable | Description |';
        $lines[] = '|---|---|---|';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mutable = ($item['mutable'] ?? false) === true ? 'yes' : 'no';
            $lines[] = '| ' . ($item['name'] ?? '') . ' | ' . $mutable . ' | ' . $this->clean((string) ($item['desc'] ?? '')) . ' |';
        }
        $lines[] = '';
    }

    private function groupMethods(array $methods, array $order): array {
        $groups = [];
        foreach ($order as $group) {
            $groups[(string) $group] = [];
        }
        foreach ($methods as $method) {
            $group = (string) ($method['group'] ?? '');
            if ($group === '') {
                $group = 'Methods';
            }
            $groups[$group] ??= [];
            $groups[$group][] = $method;
        }
        return array_filter($groups, fn(array $items): bool => $items !== []);
    }

    private function compactSignature(string $signature): string {
        if (!preg_match('/function\s+(\w+)\s*\((.*?)\)\s*(?::\s*([^\s]+))?/', $signature, $matches)) {
            return $signature;
        }
        $params = [];
        foreach ($this->splitParams($matches[2]) as $param) {
            if (preg_match('/\$(\w+)(\s*=\s*.+)?$/', trim($param), $param_match)) {
                $params[] = $param_match[1] . ($param_match[2] ?? '');
            }
        }
        $return = isset($matches[3]) ? ': ' . $matches[3] : '';
        return $matches[1] . '(' . implode(', ', $params) . ')' . $return;
    }

    private function splitParams(string $params): array {
        return trim($params) === '' ? [] : array_map('trim', explode(',', $params));
    }

    private function clean(string $value): string {
        $value = str_replace('#AI', '', $value);
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function write(string $outputPath, string $content): void {
        $dir = dirname($outputPath);
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
        if (file_put_contents($outputPath, $content) === false) {
            throw new \RuntimeException("llm_md_emitter: cannot write to {$outputPath}");
        }
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Emitter\LlmMdEmitter
#AI source_path: src/Dev/Docs/Emitter/LlmMdEmitter.php
#AI title: LlmMdEmitter
#AI description: Converts decoded llm.json data into compact grouped Markdown optimized for LLM context windows.
#AI role: Markdown emitter for LLM consumption
#AI layer: dev
#AI badges: [emitter; markdown; llm; docs]
#AI intro: `LlmMdEmitter` transforms the structured llm.json array into a single Markdown file with class sections grouped by section_order. It renders compact signatures, param tables, and warning annotations in a format that fits within LLM context windows.
#AI lifecycle: instantiated per-use by DocsLlmCommand, no state retained
#AI fallback: none — throws on write failure
#AI test_seam: instantiate directly with temp file paths
#AI invariants: [methods grouped by section_order; compact signatures strip types from params; #AI markers stripped from output text]
#AI core_behaviors: [Renders class sections with symbol, badges, metadata, and intro; Groups methods by section_order; Optionally prepends framework llm.md content; Strips #AI markers from output]
#AI owns: none — stateless
#AI entry_points: [emit]
#AI config_reads: []
#AI non_goals: [Does not generate MDX; Does not extract or load llm.json]
#AI side_effects: [writes Markdown file to disk; creates parent directories]
#AI flow: emit(data, path) -> renderClass[] -> renderMethod[] -> groupMethods -> write
#AI lifecycle_steps: [emit(); -> build header lines; -> optionally prepend framework llm.md; -> iterate classes; -> renderClass(); -> groupMethods(); -> renderMethod(); -> write()]
#AI section_order: [Emit; Architecture]
#AI architectural_notes: Produces a single-file Markdown output designed for paste-into-LLM usage, not for human browsing.

#AI:emit
#AI group: Emit
#AI frequency: high
#AI signature: public function emit(array $data, string $outputPath, ?string $frameworkLlmMd = null): void
#AI contract: Accepts decoded llm.json array and writes compact class sections grouped by section_order. Prepends framework llm.md content when the optional path is provided and the file exists.
#AI param_details: [{name: $data | type: array | required: true | desc: Decoded llm.json array with 'classes' and 'generated_at' keys.}; {name: $outputPath | type: string | required: true | desc: Filesystem path for the output Markdown file.}; {name: $frameworkLlmMd | type: ?string | required: false | desc: Optional path to framework-level llm.md to prepend.}]
#AI throws_details: [{type: \RuntimeException | desc: When the output directory cannot be created or the file cannot be written.}]
#AI side_effects: [writes Markdown file to disk]
