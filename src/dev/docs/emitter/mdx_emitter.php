<?php declare(strict_types=1);

namespace skim\dev\docs\emitter;

// Generates component-style MDX from llm.json class records.
class mdx_emitter {
    /**
     * @ai-contract accepts decoded llm.json array and output directory path
     * @ai-contract creates one MDX file per class; duplicate class names include a source-file suffix
     * @ai-contract returns count of files written
     * @ai-contract throws \RuntimeException on write failure
     */
    public function emit(array $data, string $output_dir): int {
        $classes = $data['classes'] ?? [];
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, recursive: true);
        }

        $count = 0;
        $duplicates = $this->duplicate_class_names($classes);
        $filenames = [];
        foreach ($classes as $class) {
            $file = $this->unique_mdx_file_name($this->mdx_file_name($class, $duplicates), $filenames);
            $path = $output_dir . '/' . $file;
            if (file_put_contents($path, $this->render_class($class)) === false) {
                throw new \RuntimeException("mdx_emitter: cannot write {$path}");
            }
            $count++;
        }
        return $count;
    }

    private function duplicate_class_names(array $classes): array {
        $counts = [];
        foreach ($classes as $class) {
            $name = (string) ($class['title'] ?? $class['class_name'] ?? 'class');
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        return array_filter($counts, fn(int $count): bool => $count > 1);
    }

    private function mdx_file_name(array $class, array $duplicates): string {
        $class_name = (string) ($class['title'] ?? $class['class_name'] ?? 'class');
        if (!isset($duplicates[$class_name])) {
            return $this->slug($class_name) . '.mdx';
        }
        $file = basename((string) ($class['source_path'] ?? $class['file'] ?? 'class'));
        $source = preg_replace('/(?:\.md)?\.php$/', '', $file) ?? $file;
        return $this->slug($class_name . '-' . $source) . '.mdx';
    }

    private function unique_mdx_file_name(string $file, array &$filenames): string {
        if (!isset($filenames[$file])) {
            $filenames[$file] = 1;
            return $file;
        }
        $filenames[$file]++;
        return substr($file, 0, -4) . '-' . $filenames[$file] . '.mdx';
    }

    private function slug(string $value): string {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value) ?? $value);
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'class';
    }

    private function render_class(array $class): string {
        $title = (string) ($class['title'] ?? $class['class_name'] ?? 'class');
        $description = $this->one_line((string) ($class['description'] ?? $class['summary'] ?? "Class {$title}."));
        $lines = ['---', "title: {$title}", 'description: "' . str_replace('"', '\\"', $description) . '"', '---', ''];

        foreach ($class['badges'] ?? [] as $badge) {
            $lines[] = '<ApiBadge type="' . $this->escape_attr((string) $badge) . '" />';
        }
        if (($class['badges'] ?? []) !== []) {
            $lines[] = '';
        }

        $intro = (string) ($class['intro'] ?? $class['summary'] ?? '');
        if ($intro !== '') {
            $lines[] = $this->escape_mdx($intro);
            $lines[] = '';
        }
        $this->append_info_block($lines, $class);
        $this->append_warning_boxes($lines, $class['warnings'] ?? []);
        $this->append_architecture($lines, $class);
        $this->append_scope_boxes($lines, $class['scope_items'] ?? []);
        $this->append_method_groups($lines, $class);
        $this->append_ai_context($lines, $class);

        return implode("\n", $lines) . "\n";
    }

    private function append_info_block(array &$lines, array $class): void {
        $items = [
            'Symbol' => $class['symbol'] ?? trim(($class['namespace'] ?? '') . '\\' . ($class['class_name'] ?? ''), '\\'),
            'Source' => $class['source_path'] ?? $class['file'] ?? '',
            'Lifecycle' => $class['lifecycle'] ?? '',
            'Drivers' => implode(', ', $class['drivers'] ?? []),
            'Fallback' => $class['fallback'] ?? '',
            'Test seam' => $class['test_seam'] ?? '',
        ];
        $lines[] = '```txt';
        foreach ($items as $label => $value) {
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }
        $lines[] = '```';
        $lines[] = '';
    }

    private function append_warning_boxes(array &$lines, array $warnings): void {
        foreach ($warnings as $warning) {
            $lines[] = '<WarningBox>';
            $lines[] = '';
            $lines[] = $this->escape_mdx((string) $warning);
            $lines[] = '';
            $lines[] = '</WarningBox>';
            $lines[] = '';
        }
    }

    private function append_architecture(array &$lines, array $class): void {
        if (($class['lifecycle_steps'] ?? []) !== [] || ($class['architectural_notes'] ?? '') !== '') {
            $lines[] = '## Architecture';
            $lines[] = '';
        }
        if (($class['lifecycle_steps'] ?? []) !== []) {
            $lines[] = '<LifecycleFlow>';
            $lines[] = '';
            $lines[] = '```txt';
            foreach ($class['lifecycle_steps'] as $step) {
                $lines[] = (string) $step;
            }
            $lines[] = '```';
            $lines[] = '';
            $lines[] = '</LifecycleFlow>';
            $lines[] = '';
        }
        if (($class['architectural_notes'] ?? '') !== '') {
            $lines[] = $this->escape_mdx((string) $class['architectural_notes']);
            $lines[] = '';
        }
        foreach ($class['notes'] ?? [] as $note) {
            $lines[] = '<NoteBox>';
            $lines[] = '';
            $lines[] = $this->escape_mdx((string) $note);
            $lines[] = '';
            $lines[] = '</NoteBox>';
            $lines[] = '';
        }
    }

    private function append_scope_boxes(array &$lines, array $items): void {
        if ($items === []) {
            return;
        }
        $lines[] = '## Driver Model';
        $lines[] = '';
        $lines[] = '<div class="driver-grid">';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mutable = ($item['mutable'] ?? false) === true ? ' mutable' : '';
            $lines[] = '  <ScopeBox name="' . $this->escape_attr((string) ($item['name'] ?? '')) . '"' . $mutable . '>';
            $lines[] = '    ' . $this->escape_mdx((string) ($item['desc'] ?? ''));
            $lines[] = '  </ScopeBox>';
        }
        $lines[] = '</div>';
        $lines[] = '';
    }

    private function append_method_groups(array &$lines, array $class): void {
        foreach ($this->group_methods($class['methods'] ?? [], $class['section_order'] ?? []) as $group => $methods) {
            if ($group === 'Architecture') {
                continue;
            }
            $lines[] = '## ' . $group;
            $lines[] = '';
            foreach ($methods as $method) {
                $lines = array_merge($lines, $this->render_method($method));
            }
        }
    }

    private function render_method(array $method): array {
        $lines = ['<ApiMethod name="' . $this->escape_attr((string) ($method['name'] ?? '')) . '">', '', '<ApiSignature>', '', '```php', (string) ($method['signature'] ?? ''), '```', '', '</ApiSignature>', ''];
        if (($method['contract'] ?? '') !== '') {
            $lines[] = $this->escape_mdx((string) $method['contract']);
            $lines[] = '';
        } elseif (($method['contracts'] ?? []) !== []) {
            $lines[] = $this->escape_mdx((string) $method['contracts'][0]);
            $lines[] = '';
        }
        foreach ($method['param_details'] ?? [] as $param) {
            $required = ($param['required'] ?? false) === true ? ' required' : '';
            $lines[] = '<ApiParam name="' . $this->escape_attr((string) ($param['name'] ?? '')) . '" type="' . $this->escape_attr((string) ($param['type'] ?? 'mixed')) . '"' . $required . '>';
            $lines[] = '';
            $lines[] = $this->escape_mdx((string) ($param['desc'] ?? ''));
            $lines[] = '';
            $lines[] = '</ApiParam>';
            $lines[] = '';
        }
        foreach ($method['throws_details'] ?? [] as $throw) {
            $lines[] = '<ApiThrows type="' . $this->escape_attr((string) ($throw['type'] ?? '')) . '">';
            $lines[] = '';
            $lines[] = $this->escape_mdx((string) ($throw['desc'] ?? ''));
            $lines[] = '';
            $lines[] = '</ApiThrows>';
            $lines[] = '';
        }
        $this->append_warning_boxes($lines, $method['warnings'] ?? []);
        foreach ($method['notes'] ?? [] as $note) {
            $lines[] = '<NoteBox>';
            $lines[] = '';
            $lines[] = $this->escape_mdx((string) $note);
            $lines[] = '';
            $lines[] = '</NoteBox>';
            $lines[] = '';
        }
        $lines[] = '</ApiMethod>';
        $lines[] = '';
        return $lines;
    }

    private function append_ai_context(array &$lines, array $class): void {
        $lines[] = '<AiContext>';
        $lines[] = '```txt';
        foreach (['symbol', 'role', 'layer', 'lifecycle', 'owns', 'flow'] as $key) {
            $value = $class[$key] ?? '';
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            if ($value !== '') {
                $lines[] = $key . ': ' . $value;
            }
        }
        foreach (['entry_points', 'config_reads', 'invariants', 'side_effects', 'non_goals'] as $key) {
            if (($class[$key] ?? []) === []) {
                continue;
            }
            $lines[] = $key . ':';
            foreach ($class[$key] as $item) {
                $lines[] = '  - ' . (string) $item;
            }
        }
        $lines[] = '```';
        $lines[] = '</AiContext>';
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

    private function one_line(string $value): string {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function escape_attr(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escape_mdx(string $text): string {
        $parts = preg_split('/(`[^`]*`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        foreach ($parts as $i => &$part) {
            if ($i % 2 === 0) {
                $part = str_replace(['<', '>', '{', '}'], ['&lt;', '&gt;', '&#123;', '&#125;'], $part);
            }
        }
        return implode('', $parts);
    }
}
