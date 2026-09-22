<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Emitter;

/**
 * Generates component-style MDX from llm.json class records for the Starlight docs site. #AI:class
 *
 * Use after docs:extract to produce one .mdx file per class with ApiBadge,
 * ApiMethod, ApiParam, WarningBox, and AiContext components. Duplicate class
 * names receive a source-file suffix to avoid overwrites.
 *
 * Example:
 *   $data = (new JsonEmitter())->load('llm.json');
 *   $count = (new MdxEmitter())->emit($data, 'docs/src/content/docs/api');
 *
 * Testing: Instantiate directly; operates on filesystem paths.
 *
 * #AI:class
 */
class MdxEmitter {
    /**
     * Writes one MDX file per class and returns the count of files written. #AI:emit
     *
     * Creates the output directory if it does not exist. Duplicate class names
     * include a source-file suffix. Colliding filenames get a numeric suffix.
     *
     * @param array  $data       Decoded llm.json array.
     * @param string $outputDir Directory to write .mdx files into.
     * @return int Number of MDX files written.
     *
     * @throws \RuntimeException On write failure.
     */
    public function emit(array $data, string $outputDir): int {
        $classes = $data['classes'] ?? [];
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, recursive: true);
        }

        $this->cleanOutputDir($outputDir);

        $count = 0;
        $duplicates = $this->duplicateClassNames($classes);
        $filenames = [];
        foreach ($classes as $class) {
            $file = $this->uniqueMdxFileName($this->mdxFileName($class, $duplicates), $filenames);
            $subdir = $this->namespaceToDir($class['namespace'] ?? '');
            if ($subdir === '') {
                $subdir = 'other';
            }
            $targetDir = $outputDir . '/' . $subdir;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, recursive: true);
            }
            $path = $targetDir . '/' . $file;
            if (file_put_contents($path, $this->renderClass($class)) === false) {
                throw new \RuntimeException("mdx_emitter: cannot write {$path}");
            }
            $count++;
        }

        if ($classes !== []) {
            $indexPath = $outputDir . '/index.mdx';
            if (file_put_contents($indexPath, $this->renderIndex($classes)) === false) {
                throw new \RuntimeException("mdx_emitter: cannot write {$indexPath}");
            }
            $count++;
        }

        return $count;
    }

    private function duplicateClassNames(array $classes): array {
        $counts = [];
        foreach ($classes as $class) {
            $name = (string) ($class['title'] ?? $class['class_name'] ?? 'class');
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        return array_filter($counts, fn(int $count): bool => $count > 1);
    }

    private function mdxFileName(array $class, array $duplicates): string {
        $className = (string) ($class['title'] ?? $class['class_name'] ?? 'class');
        if (!isset($duplicates[$className])) {
            return $this->slug($className) . '.mdx';
        }
        $file = basename((string) ($class['source_path'] ?? $class['file'] ?? 'class'));
        $source = preg_replace('/(?:\.md)?\.php$/', '', $file) ?? $file;
        return $this->slug($className . '-' . $source) . '.mdx';
    }

    private function uniqueMdxFileName(string $file, array &$filenames): string {
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

    private function namespaceToDir(string $namespace): string {
        if (!str_starts_with(strtolower($namespace), 'skim\\')) {
            return '';
        }
        $parts = explode('\\', substr($namespace, 5));
        // Docs output dirs stay lowercase (stable site paths).
        return strtolower($parts[0] ?? '');
    }

    private function cleanOutputDir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
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
                @rmdir($d);
            }
        }
    }

    private function getCoverageIndicator(array $class): string {
        $total = count($class['methods'] ?? []);
        if ($total === 0) {
            return "No methods";
        }
        $annotated = 0;
        foreach ($class['methods'] ?? [] as $m) {
            $isAnnotated = ($m['contract'] ?? '') !== ''
                || ($m['contracts'] ?? []) !== []
                || ($m['param_details'] ?? []) !== []
                || ($m['return_detail'] ?? []) !== []
                || ($m['throws_details'] ?? []) !== []
                || ($m['notes'] ?? []) !== []
                || ($m['invariants'] ?? []) !== []
                || ($m['non_goals'] ?? []) !== []
                || ($m['side_effects'] ?? []) !== []
                || ($m['inputs'] ?? []) !== []
                || ($m['returns'] ?? '') !== ''
                || ($m['reads'] ?? []) !== []
                || ($m['mutates'] ?? []) !== []
                || ($m['calls'] ?? []) !== []
                || ($m['throws'] ?? []) !== []
                || ($m['warnings'] ?? []) !== []
                || ($m['examples'] ?? []) !== []
                || ($m['lifecycle'] ?? '') !== ''
                || ($m['perf'] ?? '') !== ''
                || ($m['group'] ?? '') !== ''
                || ($m['frequency'] ?? '') !== '';
            if ($isAnnotated) {
                $annotated++;
            }
        }
        $pct = (int) round(($annotated / $total) * 100);
        return "{$annotated}/{$total} ({$pct}%)";
    }

    private function renderIndex(array $classes): string {
        $groups = [];
        $duplicates = $this->duplicateClassNames($classes);
        $filenames = [];
        
        foreach ($classes as $class) {
            $dir = $this->namespaceToDir($class['namespace'] ?? '');
            if ($dir === '') {
                $dir = 'other';
            }
            $file = $this->uniqueMdxFileName($this->mdxFileName($class, $duplicates), $filenames);
            $class['_mdx_file'] = substr($file, 0, -4);
            $groups[$dir][] = $class;
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
            'Welcome to the SKIM Framework API Reference. Browse classes, facades, and helpers below grouped by module.',
            '',
        ];
        
        foreach ($groups as $dir => $groupClasses) {
            $label = ucfirst($dir);
            $lines[] = "## {$label} Module";
            $lines[] = '';
            $lines[] = '| Class / Facade | Description | Coverage | Quick Links |';
            $lines[] = '| :--- | :--- | :--- | :--- |';
            
            foreach ($groupClasses as $class) {
                $slugClass = $class['_mdx_file'];
                $classTitle = $class['title'] ?? $class['class_name'] ?? 'class';
                $classLink = "[`{$classTitle}`](./{$dir}/{$slugClass})";
                
                $desc = $this->oneLine((string) ($class['description'] ?? $class['summary'] ?? ''));
                if ($desc === '') {
                    $desc = 'No description available.';
                } else {
                    $desc = $this->escapeMdx($desc);
                }
                
                $coverage = $this->getCoverageIndicator($class);
                
                $sectionLinks = [];
                foreach ($class['section_order'] ?? [] as $section) {
                    if ($section === 'Architecture' || $section === 'Driver Model') {
                        continue;
                    }
                    $secSlug = $this->slug($section);
                    $sectionLinks[] = "[{$section}](./{$dir}/{$slugClass}#{$secSlug})";
                }
                $quickLinks = count($sectionLinks) > 0 ? implode(' • ', $sectionLinks) : '—';
                
                $lines[] = "| {$classLink} | {$desc} | `{$coverage}` | {$quickLinks} |";
            }
            $lines[] = '';
        }
        
        return implode("\n", $lines) . "\n";
    }

    private function renderClass(array $class): string {
        $title = (string) ($class['title'] ?? $class['class_name'] ?? 'class');
        $description = $this->oneLine((string) ($class['description'] ?? $class['summary'] ?? "Class {$title}."));
        $lines = ['---', "title: {$title}", 'description: "' . $this->escapeYamlDouble($description) . '"', '---', ''];

        foreach ($class['badges'] ?? [] as $badge) {
            $lines[] = '<ApiBadge type="' . $this->escapeAttr((string) $badge) . '" />';
        }
        if (($class['badges'] ?? []) !== []) {
            $lines[] = '';
        }

        $intro = (string) ($class['intro'] ?? $class['summary'] ?? '');
        if ($intro !== '') {
            $lines[] = $this->escapeMdx($intro);
            $lines[] = '';
        }
        $this->appendInfoBlock($lines, $class);
        $this->appendClassExamples($lines, $class['examples'] ?? []);
        $this->appendWarningBoxes($lines, $class['warnings'] ?? []);
        $this->appendArchitecture($lines, $class);
        $this->appendScopeBoxes($lines, $class['scope_items'] ?? []);
        $this->appendMethodGroups($lines, $class);
        $this->appendAiContext($lines, $class);

        return implode("\n", $lines) . "\n";
    }

    private function appendInfoBlock(array &$lines, array $class): void {
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

    private function appendClassExamples(array &$lines, array $examples): void {
        foreach ($examples as $ex) {
            $label = (string) ($ex['label'] ?? 'Basic usage');
            $code = (string) ($ex['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $collapsed = count(explode("\n", $code)) > 10 ? ' collapsed' : '';
            $lines[] = '<CodeExample label="' . $this->escapeAttr($label) . '" source="llm-generated"' . $collapsed . '>';
            $lines[] = '```php';
            $lines[] = $code;
            $lines[] = '```';
            $lines[] = '</CodeExample>';
            $lines[] = '';
        }
    }

    private function appendWarningBoxes(array &$lines, array $warnings): void {
        foreach ($warnings as $warning) {
            $lines[] = '<WarningBox>';
            $lines[] = '';
            $lines[] = $this->escapeMdx((string) $warning);
            $lines[] = '';
            $lines[] = '</WarningBox>';
            $lines[] = '';
        }
    }

    private function appendArchitecture(array &$lines, array $class): void {
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
            $lines[] = $this->escapeMdx((string) $class['architectural_notes']);
            $lines[] = '';
        }
        foreach ($class['notes'] ?? [] as $note) {
            $lines[] = '<NoteBox>';
            $lines[] = '';
            $lines[] = $this->escapeMdx((string) $note);
            $lines[] = '';
            $lines[] = '</NoteBox>';
            $lines[] = '';
        }
    }

    private function appendScopeBoxes(array &$lines, array $items): void {
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
            $lines[] = '  <ScopeBox name="' . $this->escapeAttr((string) ($item['name'] ?? '')) . '"' . $mutable . '>';
            $lines[] = '    ' . $this->escapeMdx((string) ($item['desc'] ?? ''));
            $lines[] = '  </ScopeBox>';
        }
        $lines[] = '</div>';
        $lines[] = '';
    }

    private function appendMethodGroups(array &$lines, array $class): void {
        foreach ($this->groupMethods($class['methods'] ?? [], $class['section_order'] ?? []) as $group => $methods) {
            if ($group === 'Architecture') {
                continue;
            }
            $lines[] = '## ' . $group;
            $lines[] = '';
            foreach ($methods as $method) {
                $lines = array_merge($lines, $this->renderMethod($method));
            }
        }
    }

    private function renderMethod(array $method): array {
        $lines = ['<ApiMethod name="' . $this->escapeAttr((string) ($method['name'] ?? '')) . '">', '', '<ApiSignature>', '', '```php', (string) ($method['signature'] ?? ''), '```', '', '</ApiSignature>', ''];
        if (($method['contract'] ?? '') !== '') {
            $lines[] = $this->escapeMdx((string) $method['contract']);
            $lines[] = '';
        } elseif (($method['contracts'] ?? []) !== []) {
            $lines[] = $this->escapeMdx((string) $method['contracts'][0]);
            $lines[] = '';
        }
        foreach ($method['param_details'] ?? [] as $param) {
            $required = ($param['required'] ?? false) === true ? ' required' : '';
            $lines[] = '<ApiParam name="' . $this->escapeAttr((string) ($param['name'] ?? '')) . '" type="' . $this->escapeAttr((string) ($param['type'] ?? 'mixed')) . '"' . $required . '>';
            $lines[] = '';
            $lines[] = $this->escapeMdx((string) ($param['desc'] ?? ''));
            $lines[] = '';
            $lines[] = '</ApiParam>';
            $lines[] = '';
        }
        foreach ($method['examples'] ?? [] as $ex) {
            $label = (string) ($ex['label'] ?? 'Basic usage');
            $code = (string) ($ex['code'] ?? '');
            if ($code === '') {
                continue;
            }
            $collapsed = count(explode("\n", $code)) > 10 ? ' collapsed' : '';
            $lines[] = '<CodeExample label="' . $this->escapeAttr($label) . '" source="llm-generated"' . $collapsed . '>';
            $lines[] = '```php';
            $lines[] = $code;
            $lines[] = '```';
            $lines[] = '</CodeExample>';
            $lines[] = '';
        }
        foreach ($method['throws_details'] ?? [] as $throw) {
            $lines[] = '<ApiThrows type="' . $this->escapeAttr((string) ($throw['type'] ?? '')) . '">';
            $lines[] = '';
            $lines[] = $this->escapeMdx((string) ($throw['desc'] ?? ''));
            $lines[] = '';
            $lines[] = '</ApiThrows>';
            $lines[] = '';
        }
        $this->appendWarningBoxes($lines, $method['warnings'] ?? []);
        foreach ($method['notes'] ?? [] as $note) {
            $lines[] = '<NoteBox>';
            $lines[] = '';
            $lines[] = $this->escapeMdx((string) $note);
            $lines[] = '';
            $lines[] = '</NoteBox>';
            $lines[] = '';
        }
        $lines[] = '</ApiMethod>';
        $lines[] = '';
        return $lines;
    }

    private function appendAiContext(array &$lines, array $class): void {
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

    private function groupMethods(array $methods, array $order): array {
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

    private function oneLine(string $value): string {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * Escapes a value for a double-quoted YAML scalar (MDX frontmatter).
     * Backslashes first — a lone `\T` (e.g. from `\Throwable`) is an
     * invalid YAML escape and breaks frontmatter parsers. #AI:escapeYamlDouble
     */
    private function escapeYamlDouble(string $value): string {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function escapeAttr(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeMdx(string $text): string {
        $parts = preg_split('/(`[^`]+`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        foreach ($parts as $part) {
            if (str_starts_with($part, '`') && str_ends_with($part, '`')) {
                $result .= $part;
            } else {
                $result .= str_replace(['<', '>', '{', '}'], ['&lt;', '&gt;', '&#123;', '&#125;'], $part);
            }
        }
        return $result;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Emitter\MdxEmitter
#AI source_path: src/Dev/Docs/Emitter/MdxEmitter.php
#AI title: MdxEmitter
#AI description: Generates component-style MDX files from llm.json class records for the Starlight documentation site.
#AI role: MDX documentation generator
#AI layer: dev
#AI badges: [emitter; mdx; starlight; docs]
#AI intro: `MdxEmitter` transforms decoded llm.json data into one .mdx file per class using Starlight-compatible components (ApiBadge, ApiMethod, ApiParam, ApiThrows, WarningBox, NoteBox, ScopeBox, AiContext, LifecycleFlow). Handles duplicate class names and filename collisions.
#AI lifecycle: instantiated per-use by DocsSiteCommand, no state retained
#AI fallback: none — throws on write failure
#AI test_seam: instantiate directly with temp directory paths
#AI invariants: [one MDX file per class; duplicate class names get source-file suffix; colliding filenames get numeric suffix; Architecture group methods excluded from method sections; writes classes into namespace subdirectories; cleans stale .mdx before writing; generates api/index.mdx]
#AI core_behaviors: [Renders frontmatter with title and description; Emits ApiBadge, WarningBox, ScopeBox, and AiContext components; Groups methods by section_order; Escapes MDX special characters outside code spans; Organizes output into namespace subdirectories; Generates index.mdx landing page]
#AI owns: none — stateless
#AI entry_points: [emit]
#AI config_reads: []
#AI non_goals: [Does not generate Markdown; Does not extract or load llm.json]
#AI side_effects: [writes .mdx files to output directory; creates directory if needed; cleans stale .mdx files recursively]
#AI flow: emit(data, dir) -> cleanOutputDir -> duplicateClassNames -> namespaceToDir -> mdxFileName -> renderClass -> write; renderIndex -> write index.mdx
#AI lifecycle_steps: [emit(); -> clean stale .mdx files; -> detect duplicate class names; -> derive namespace subdirectory; -> generate unique filenames; -> renderClass() per class; -> write .mdx files; -> renderIndex(); -> write api/index.mdx]
#AI section_order: [Emit; Architecture]
#AI architectural_notes: MDX output uses Starlight-specific components; the emitter is coupled to the Starlight/Astro component API.

#AI:emit
#AI group: Emit
#AI frequency: high
#AI signature: public function emit(array $data, string $outputDir): int
#AI contract: Accepts decoded llm.json array and writes one MDX file per class into namespace subdirectories under the output directory. Also writes an index.mdx landing page. Returns the count of files written. Handles duplicate class names and filename collisions. Cleans stale .mdx files before writing.
#AI param_details: [{name: $data | type: array | required: true | desc: Decoded llm.json array with 'classes' key.}; {name: $outputDir | type: string | required: true | desc: Directory to write .mdx files into. Created if it does not exist.}]
#AI return_detail: {type: int | desc: Number of MDX files written (including index.mdx).}
#AI throws_details: [{type: \RuntimeException | desc: When a file cannot be written.}]
#AI side_effects: [writes .mdx files to disk; creates output directory; cleans stale .mdx files recursively; creates namespace subdirectories]
