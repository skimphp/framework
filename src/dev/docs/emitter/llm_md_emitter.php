<?php declare(strict_types=1);

namespace skim\dev\docs\emitter;

// Reads decoded llm.json data and writes compact grouped Markdown for LLM use.
class llm_md_emitter {
    /**
     * @ai-contract accepts decoded llm.json array and output path
     * @ai-contract writes compact class sections grouped by section_order
     * @ai-contract throws \RuntimeException if the file cannot be written
     */
    public function emit(array $data, string $output_path, ?string $framework_llm_md = null): void {
        $classes = $data['classes'] ?? [];
        $generated = $data['generated_at'] ?? date('c');

        $lines = ['# LLM context', '', '> Auto-generated from source annotations. Do not edit manually.  ', "> Generated: {$generated}", ''];

        if ($framework_llm_md !== null && file_exists($framework_llm_md)) {
            $framework_content = file_get_contents($framework_llm_md);
            if ($framework_content !== false) {
                $lines[] = '## Framework (skim)';
                $lines[] = '';
                $lines[] = $framework_content;
                $lines[] = '';
                $lines[] = '---';
                $lines[] = '';
            }
        }

        foreach ($classes as $class) {
            $lines = array_merge($lines, $this->render_class($class));
        }

        $this->write($output_path, implode("\n", $lines) . "\n");
    }

    private function render_class(array $class): array {
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

        $this->append_list($lines, 'Core Behavior', $class['core_behaviors'] ?? $class['invariants'] ?? []);
        $this->append_scope_table($lines, $class['scope_items'] ?? []);
        $this->append_list($lines, 'Warnings', $class['warnings'] ?? [], prefix: '⚠ ');

        $methods = $class['methods'] ?? [];
        $groups = $this->group_methods($methods, $class['section_order'] ?? []);
        foreach ($groups as $group => $group_methods) {
            $lines[] = "### {$group}";
            $lines[] = '';
            foreach ($group_methods as $method) {
                $lines = array_merge($lines, $this->render_method($method));
            }
        }

        return $lines;
    }

    private function render_method(array $method): array {
        $lines = ["#### `{$this->compact_signature($method['signature'] ?? $method['name'] ?? '')}`"];
        if (($method['contract'] ?? '') !== '') {
            $lines[] = $this->clean($method['contract']);
        } elseif (($method['contracts'] ?? []) !== []) {
            $lines[] = $this->clean($method['contracts'][0]);
        }
        foreach ($method['invariants'] ?? [] as $invariant) {
            $lines[] = '- **Invariant:** ' . $this->clean((string) $invariant);
        }
        foreach ($method['non_goals'] ?? [] as $non_goal) {
            $lines[] = '- **Non-goal:** ' . $this->clean((string) $non_goal);
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

    private function append_list(array &$lines, string $title, array $items, string $prefix = ''): void {
        if ($items === []) {
            return;
        }
        $lines[] = "### {$title}";
        foreach ($items as $item) {
            $lines[] = '- ' . $prefix . $this->clean((string) $item);
        }
        $lines[] = '';
    }

    private function append_scope_table(array &$lines, array $items): void {
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

    private function group_methods(array $methods, array $order): array {
        $groups = [];
        foreach ($order as $group) {
            $groups[(string) $group] = [];
        }
        foreach ($methods as $method) {
            $group = (string) ($method['group'] ?? 'Methods');
            $groups[$group] ??= [];
            $groups[$group][] = $method;
        }
        return array_filter($groups, fn(array $items): bool => $items !== []);
    }

    private function compact_signature(string $signature): string {
        if (!preg_match('/function\s+(\w+)\s*\((.*?)\)\s*(?::\s*([^\s]+))?/', $signature, $matches)) {
            return $signature;
        }
        $params = [];
        foreach ($this->split_params($matches[2]) as $param) {
            if (preg_match('/\$(\w+)(\s*=\s*.+)?$/', trim($param), $param_match)) {
                $params[] = $param_match[1] . ($param_match[2] ?? '');
            }
        }
        $return = isset($matches[3]) ? ': ' . $matches[3] : '';
        return $matches[1] . '(' . implode(', ', $params) . ')' . $return;
    }

    private function split_params(string $params): array {
        return trim($params) === '' ? [] : array_map('trim', explode(',', $params));
    }

    private function clean(string $value): string {
        $value = str_replace('#AI', '', $value);
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function write(string $output_path, string $content): void {
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
