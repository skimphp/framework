<?php declare(strict_types=1);

namespace skim\dev\docs\emitter;

/**
 * Generates component-style MDX from llm.json class records for the Starlight docs site. #AI:class
 *
 * Use after docs:extract to produce one .mdx file per class with ApiBadge,
 * ApiMethod, ApiParam, WarningBox, and AiContext components. Duplicate class
 * names receive a source-file suffix to avoid overwrites.
 *
 * Example:
 *   $data = (new json_emitter())->load('llm.json');
 *   $count = (new mdx_emitter())->emit($data, 'docs/src/content/docs/api');
 *
 * Testing: Instantiate directly; operates on filesystem paths.
 *
 * #AI:class
 */
class mdx_emitter {
    /**
     * Writes one MDX file per class and returns the count of files written. #AI:emit
     *
     * Creates the output directory if it does not exist. Duplicate class names
     * include a source-file suffix. Colliding filenames get a numeric suffix.
     *
     * @param array  $data       Decoded llm.json array.
     * @param string $output_dir Directory to write .mdx files into.
     * @return int Number of MDX files written.
     *
     * @throws \RuntimeException On write failure.
     */
    public function emit(array $data, string $output_dir): int {
        $classes = $data['classes'] ?? [];
        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, recursive: true);
        }

        $this->clean_output_dir($output_dir);

        $count = 0;
        $duplicates = $this->duplicate_class_names($classes);
        $filenames = [];
        foreach ($classes as $class) {
            $file = $this->unique_mdx_file_name($this->mdx_file_name($class, $duplicates), $filenames);
            $subdir = $this->namespace_to_dir($class['namespace'] ?? '');
            if ($subdir === '') {
                $subdir = 'other';
            }
            $target_dir = $output_dir . '/' . $subdir;
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, recursive: true);
            }
            $path = $target_dir . '/' . $file;
            if (file_put_contents($path, $this->render_class($class)) === false) {
                throw new \RuntimeException("mdx_emitter: cannot write {$path}");
            }
            $count++;
        }

        $index_path = $output_dir . '/index.mdx';
        if (file_put_contents($index_path, $this->render_index($classes)) === false) {
            throw new \RuntimeException("mdx_emitter: cannot write {$index_path}");
        }
        $count++;

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

    private function namespace_to_dir(string $namespace): string {
        if (!str_starts_with($namespace, 'skim\\')) {
            return '';
        }
        $parts = explode('\\', substr($namespace, 5));
        return $parts[0] ?? '';
    }

    private function clean_output_dir(string $dir): void {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        $dirs = [];
        foreach ($files as $fileinfo) {
            if ($fileinfo->isFile() && $fileinfo->getExtension() === 'mdx') {
                unlink($fileinfo->getPathname());
            } elseif ($fileinfo->isDir()) {
                $dirs[] = $fileinfo->getPathname();
            }
        }
        foreach (array_reverse($dirs) as $d) {
            if (count(glob($d . '/*')) === 0) {
                rmdir($d);
            }
        }
    }

    private function render_index(array $classes): string {
        $groups = [];
        foreach ($classes as $class) {
            $dir = $this->namespace_to_dir($class['namespace'] ?? '');
            if ($dir === '') {
                $dir = 'other';
            }
            $groups[$dir][] = $class['title'] ?? $class['class_name'] ?? 'class';
        }
        ksort($groups);

        $lines = [
            '---',
            'title: API Reference',
            'description: "Complete API reference for the SKIM PHP framework, organized by module."',
            '---',
            '',
            '# API Reference',
            '',
            'Browse the framework by module:',
            '',
        ];
        foreach ($groups as $dir => $items) {
            $label = ucfirst($dir);
            $count = count($items);
            $lines[] = "- [**{$label}**](./{$dir}/) — {$count} classes";
        }
        $lines[] = '';
        return implode("\n", $lines) . "\n";
    }

    private function render_class(array $class): string {
        $title = str_replace('`', '&#96;', (string) ($class['title'] ?? $class['class_name'] ?? 'class'));
        $description = str_replace('`', '&#96;', $this->one_line((string) ($class['description'] ?? $class['summary'] ?? "Class {$title}.")));
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
        $text = str_replace('`', '&#96;', $text);
        return str_replace(['<', '>', '{', '}'], ['&lt;', '&gt;', '&#123;', '&#125;'], $text);
    }
}

#AI:class
#AI symbol: skim\dev\docs\emitter\mdx_emitter
#AI source_path: src/dev/docs/emitter/mdx_emitter.php
#AI title: mdx_emitter
#AI description: Generates component-style MDX files from llm.json class records for the Starlight documentation site.
#AI role: MDX documentation generator
#AI layer: dev
#AI badges: [emitter; mdx; starlight; docs]
#AI intro: `mdx_emitter` transforms decoded llm.json data into one .mdx file per class using Starlight-compatible components (ApiBadge, ApiMethod, ApiParam, ApiThrows, WarningBox, NoteBox, ScopeBox, AiContext, LifecycleFlow). Handles duplicate class names and filename collisions.
#AI lifecycle: instantiated per-use by docs_site_command, no state retained
#AI fallback: none — throws on write failure
#AI test_seam: instantiate directly with temp directory paths
#AI invariants: [one MDX file per class; duplicate class names get source-file suffix; colliding filenames get numeric suffix; Architecture group methods excluded from method sections; writes classes into namespace subdirectories; cleans stale .mdx before writing; generates api/index.mdx]
#AI core_behaviors: [Renders frontmatter with title and description; Emits ApiBadge, WarningBox, ScopeBox, and AiContext components; Groups methods by section_order; Escapes MDX special characters outside code spans; Organizes output into namespace subdirectories; Generates index.mdx landing page]
#AI owns: none — stateless
#AI entry_points: [emit]
#AI config_reads: []
#AI non_goals: [Does not generate Markdown; Does not extract or load llm.json]
#AI side_effects: [writes .mdx files to output directory; creates directory if needed; cleans stale .mdx files recursively]
#AI flow: emit(data, dir) -> clean_output_dir -> duplicate_class_names -> namespace_to_dir -> mdx_file_name -> render_class -> write; render_index -> write index.mdx
#AI lifecycle_steps: [emit(); -> clean stale .mdx files; -> detect duplicate class names; -> derive namespace subdirectory; -> generate unique filenames; -> render_class() per class; -> write .mdx files; -> render_index(); -> write api/index.mdx]
#AI section_order: [Emit; Architecture]
#AI architectural_notes: MDX output uses Starlight-specific components; the emitter is coupled to the Starlight/Astro component API.

#AI:emit
#AI group: Emit
#AI frequency: high
#AI signature: public function emit(array $data, string $output_dir): int
#AI contract: Accepts decoded llm.json array and writes one MDX file per class into namespace subdirectories under the output directory. Also writes an index.mdx landing page. Returns the count of files written. Handles duplicate class names and filename collisions. Cleans stale .mdx files before writing.
#AI param_details: [{name: $data | type: array | required: true | desc: Decoded llm.json array with 'classes' key.}; {name: $output_dir | type: string | required: true | desc: Directory to write .mdx files into. Created if it does not exist.}]
#AI return_detail: {type: int | desc: Number of MDX files written (including index.mdx).}
#AI throws_details: [{type: \RuntimeException | desc: When a file cannot be written.}]
#AI side_effects: [writes .mdx files to disk; creates output directory; cleans stale .mdx files recursively; creates namespace subdirectories]
