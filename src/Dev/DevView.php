<?php declare(strict_types=1);

namespace Skim\Dev;

/**
 * Standalone template renderer for dev tools with zero framework dependencies. #AI:class
 *
 * Use when rendering error pages, toolbar panels, or any dev tool HTML that
 * must work even when the framework's view system is broken. Supports layouts,
 * slots, and partial includes — same API patterns as Skim\View\Template but
 * completely self-contained (no config, no profiler, no DI).
 *
 * Templates are .php files in src/dev/views/. Data is extracted as local
 * variables. The $this variable inside templates is the dev_view instance.
 *
 * Example:
 *   echo DevView::render('error_page', [
 *       'exception' => $e,
 *       'context'   => $code_lines,
 *   ]);
 *
 * Testing: Pure rendering — pass data arrays, assert HTML output.
 *
 * #AI:class
 */
final class DevView {
    // layout name declared by child template via layout()
    private ?string $layoutName = null;

    // slot_name → captured HTML content
    private array $slots = [];

    // currently open slot name, null when no capture active
    private ?string $activeSlot = null;

    private function __construct(
        private readonly string $viewsPath,
        private readonly array  $data,
    ) {}

    /**
     * Renders a template file with data, optionally wrapping in a layout. #AI:render
     *
     * Resolves .php extension automatically. If the template calls layout(),
     * the layout file renders with access to all captured slots. Non-slot
     * output becomes the 'content' slot automatically.
     *
     * @param string $template Template path relative to src/dev/views/, no extension.
     * @param array  $data     Variables extracted as locals inside the template.
     * @throws \RuntimeException If the template file is not found.
     */
    public static function render(string $template, array $data = []): string {
        $path = __DIR__ . '/Views';
        $ctx  = new self($path, $data);

        $html = $ctx->renderFile($template);

        if ($ctx->layoutName !== null) {
            if (!isset($ctx->slots['content'])) {
                $ctx->slots['content'] = $html;
            }
            $layout       = new self($path, $ctx->data);
            $layout->slots = $ctx->slots;
            return $layout->renderFile($ctx->layoutName);
        }

        return $html;
    }

    /**
     * Renders a partial template within the current data context. #AI:include
     *
     * Partials do NOT inherit the parent's layout. Extra data is merged
     * with the current context for this partial only.
     *
     * @param string $template Template path relative to src/dev/views/, no extension.
     * @param array  $extra    Additional data merged for this partial only.
     */
    public function include(string $template, array $extra = []): string {
        return (new self($this->viewsPath, array_merge($this->data, $extra)))->renderFile($template);
    }

    /**
     * Declares the layout to wrap this template's output. #AI:layout
     *
     * Must be called before any output in the template file. The layout
     * uses $this->slot() to inject captured content.
     *
     * @param string $name Layout template path relative to src/dev/views/, no extension.
     */
    public function layout(string $name): void {
        $this->layoutName = $name;
    }

    /**
     * Starts capturing output into a named slot. #AI:start
     *
     * Must be paired with end(). Content between start() and end() is
     * captured and available to the layout via slot($name).
     *
     * @param string $name Slot identifier used by the layout to retrieve content.
     */
    public function start(string $name): void {
        $this->activeSlot = $name;
        ob_start();
    }

    /**
     * Ends the slot capture started by start(). #AI:end
     *
     * @throws \LogicException If called without a matching start().
     */
    public function end(): void {
        if ($this->activeSlot === null) {
            throw new \LogicException('end() called without matching start()');
        }
        $this->slots[$this->activeSlot] = (string) ob_get_clean();
        $this->activeSlot               = null;
    }

    /**
     * Outputs captured slot content inside a layout file. #AI:slot
     *
     * Returns empty string if the slot was never captured.
     *
     * @param string $name Slot identifier matching a previous start()/end() pair.
     */
    public function slot(string $name): string {
        return $this->slots[$name] ?? '';
    }

    /**
     * Strips the project root from a file path, falling back to basename. #AI:short_path
     *
     * Used by dev tool templates to display readable file locations.
     */
    public static function shortPath(string $file): string {
        $root = defined('SKIM_ROOT') ? \SKIM_ROOT : dirname(__DIR__, 2);
        $normalized = str_replace('\\', '/', $file);
        $rootNorm  = str_replace('\\', '/', rtrim($root, '/'));
        if (str_starts_with($normalized, $rootNorm)) {
            return ltrim(substr($normalized, strlen($rootNorm)), '/');
        }
        return basename($file);
    }

    /**
     * Returns a CSS class name representing a PHP value type. #AI:value_class
     *
     * Used by dev tool templates to color-code argument values in stack frames.
     */
    public static function valueClass(string $type): string {
        return match ($type) {
            'string'        => 'string',
            'int', 'float'  => 'number',
            'bool'          => 'bool',
            'null'          => 'null',
            'array'         => 'array',
            default         => 'object',
        };
    }

    /**
     * Renders a template file, resolving .php extension and extracting data. #AI:renderFile
     *
     * @param string $template Template path relative to views root, no extension.
     * @throws \RuntimeException If the template file does not exist.
     */
    public function renderFile(string $template): string {
        $templatePath = $this->viewsPath . '/' . ltrim($template, '/') . '.php';

        if (!is_file($templatePath)) {
            throw new \RuntimeException("Dev view template not found: {$template} ({$templatePath})");
        }

        $localData = (array) $this->data;
        extract($localData, \EXTR_SKIP);

        ob_start();
        include $templatePath;
        return (string) ob_get_clean();
    }
}

#AI:class
#AI symbol: Skim\Dev\DevView
#AI source_path: src/Dev/DevView.php
#AI title: DevView
#AI description: Standalone template renderer for dev tools — zero framework dependencies, supports layouts, slots, and partial includes.
#AI role: standalone template renderer
#AI layer: dev
#AI badges: [dev; debug; template; renderer; standalone]
#AI intro: `DevView` is a minimal, self-contained template renderer for dev tool HTML output. It supports layouts, named slots, and partial includes with the same API patterns as `Skim\View\Template`, but has zero dependencies on any framework module — ensuring it works even when the view system, config, or profiler is broken.
#AI lifecycle: instantiated per render() call, stateless between calls
#AI fallback: throws RuntimeException when template file is missing
#AI test_seam: call render() with data arrays, assert HTML output
#AI invariants: [render() is the only static entry point; templates live in src/dev/views/; data is extracted as local variables; layout() must be called before output; start()/end() must be paired; include() does not inherit parent layout]
#AI core_behaviors: [Two-pass layout: child renders first capturing slots, then layout renders with slot() access; Non-slot output auto-captured as content slot; Partials via include() with isolated data context]
#AI warnings: [Throws RuntimeException if template file not found — ErrorPage::render() must catch this and fall back to inline HTML]
#AI owns: viewsPath, data, layoutName, slots, activeSlot
#AI entry_points: [render; include; layout; start; end; slot]
#AI config_reads: []
#AI non_goals: [Does not integrate with profiler; Does not support fragment extraction; Does not compile or cache templates; Does not escape output]
#AI side_effects: [uses ob_start/ob_get_clean for rendering and slot capture; extract() creates local variables]
#AI flow: DevView::render($template, $data) → new DevView() → renderFile() → layout? → render layout → return HTML
#AI lifecycle_steps: [render($template, $data); → new DevView(path, data); → renderFile($template); → resolve .php; → extract data; → ob_start + include; → layout declared? → capture content slot; → render layout file; → return HTML]
#AI section_order: [Rendering; Template API; Layout System]
#AI architectural_notes: Deliberately duplicates ~60 lines from Skim\View\Template to eliminate any dependency on the framework's view system. This ensures the error page renders even when the view system itself throws.

#AI:render
#AI group: Rendering
#AI frequency: high
#AI signature: public static function render(string $template, array $data = []): string
#AI contract: Renders a template file with data extracted as locals. When the template declares a layout, wraps output in the layout with slot access. Returns the final HTML string.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to src/dev/views/, no .php extension needed.}; {name: $data | type: array | required: false | desc: Key-value pairs extracted as local variables inside the template.}]
#AI return_detail: {type: string | desc: Rendered HTML string.}
#AI throws_details: [{type: \RuntimeException | desc: If the template .php file does not exist in src/dev/views/.}]
#AI side_effects: [uses output buffering; extract() creates local variables]

#AI:include
#AI group: Template API
#AI frequency: high
#AI signature: public function include(string $template, array $extra = []): string
#AI contract: Renders a partial template within the current data context. The partial does not inherit the parent's layout.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to src/dev/views/, no extension.}; {name: $extra | type: array | required: false | desc: Additional data merged for this partial only.}]
#AI return_detail: {type: string | desc: Rendered partial HTML.}

#AI:layout
#AI group: Layout System
#AI frequency: medium
#AI signature: public function layout(string $name): void
#AI contract: Declares the layout template to wrap this template's output. Must be called before any output.
#AI param_details: [{name: $name | type: string | required: true | desc: Layout template path relative to src/dev/views/, no extension.}]

#AI:start
#AI group: Layout System
#AI frequency: medium
#AI signature: public function start(string $name): void
#AI contract: Starts capturing output into a named slot. Must be paired with end().
#AI param_details: [{name: $name | type: string | required: true | desc: Slot identifier used by the layout to retrieve content.}]
#AI side_effects: [starts output buffering via ob_start()]

#AI:end
#AI group: Layout System
#AI frequency: medium
#AI signature: public function end(): void
#AI contract: Ends the slot capture started by start(). Stores captured output in the named slot.
#AI throws_details: [{type: \LogicException | desc: If called without a matching start().}]
#AI side_effects: [ends output buffering via ob_get_clean()]

#AI:slot
#AI group: Layout System
#AI frequency: medium
#AI signature: public function slot(string $name): string
#AI contract: Returns captured slot content for use inside layout files. Returns empty string if the slot was never captured.
#AI param_details: [{name: $name | type: string | required: true | desc: Slot identifier matching a previous start()/end() pair.}]
#AI return_detail: {type: string | desc: Captured slot HTML or empty string.}

#AI:renderFile
#AI group: Rendering
#AI frequency: internal
#AI signature: public function renderFile(string $template): string
#AI contract: Renders a single template file by resolving the .php extension, extracting data as local variables, and including the file within an output buffer.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views root, no extension.}]
#AI return_detail: {type: string | desc: Raw rendered HTML without layout wrapping.}
#AI throws_details: [{type: \RuntimeException | desc: If the template file does not exist.}]
#AI side_effects: [uses extract() and ob_start/ob_get_clean]
