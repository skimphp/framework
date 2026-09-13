<?php declare(strict_types=1);

namespace Skim\View;

/**
 * Template context object — `$this` inside every .php view file. #AI:class
 *
 * Use inside templates to include partials, declare layouts, and define slots.
 * Layout execution order: child template runs first (capturing slots), then
 * the layout file renders and calls $this->slot() to inject captured content.
 *
 * Example:
 *   // Inside a template file:
 *   $this->layout('layouts/main');
 *   $this->start('sidebar');
 *   echo '<nav>...</nav>';
 *   $this->end();
 *   <h1><?= e($title) ?></h1>
 *
 * Testing: Instantiate directly with a views path and data array.
 *
 * #AI:class
 */
class Template {
    private string  $viewsPath;
    private array   $data;
    private ?string $layoutName  = null;
    private array   $slots        = [];   // name → captured HTML
    private ?string $activeSlot  = null;
    private bool    $fragmentMode = false; // true = ignore layout() calls

    public function __construct(string $viewsPath, array $data = [], private readonly ?string $defaultLayout = null, bool $fragmentMode = false) {
        $this->viewsPath = rtrim($viewsPath, '/');
        $this->data       = $data;
        $this->fragmentMode = $fragmentMode;
    }

    /**
     * Renders a partial template within the current data context. #AI:include
     *
     * The included template does NOT inherit the parent's layout declaration.
     * Extra data is merged with the current context for the partial only.
     *
     * @param string $template Template path relative to views root, no extension needed.
     * @param array  $extra    Additional data merged for this partial only.
     */
    public function include(string $template, array $extra = []): string {
        return (new self($this->viewsPath, array_merge($this->data, $extra), null))->renderFile($template);
    }

    /**
     * Declares the layout to wrap this template. #AI:layout
     *
     * Must be called before any output. The layout file uses $this->slot()
     * to inject child content. If not called, the default_layout is used.
     * Ignored when fragment_mode is true (HTMX optimization).
     *
     * @param string $name Layout template path relative to views root.
     */
    public function layout(string $name): void {
        if ($this->fragmentMode) {
            return;
        }
        $this->layoutName = $name;
    }

    /**
     * Starts capturing output into a named slot. #AI:start
     *
     * Must be paired with end(). Content between start() and end() is
     * captured and made available to the layout via slot($name).
     *
     * @param string $name Slot identifier used by the layout to retrieve content.
     */
    public function start(string $name): void {
        if ($this->fragmentMode) {
            return;
        }
        $this->activeSlot = $name;
        ob_start();
    }

    /**
     * Ends the slot capture started by start(). #AI:end
     *
     * @throws \LogicException If called without a matching start().
     */
    public function end(): void {
        if ($this->fragmentMode) {
            return;
        }
        if ($this->activeSlot === null) {
            throw new \LogicException('end() called without matching start()');
        }
        $this->slots[$this->activeSlot] = (string) ob_get_clean();
        $this->activeSlot               = null;
    }

    /**
     * Alias for start() — familiar section API. #AI:section
     *
     * @param string $name Slot identifier used by the layout to retrieve content.
     */
    public function section(string $name): void {
        $this->start($name);
    }

    /**
     * Alias for end() — familiar section API. #AI:endSection
     *
     * @throws \LogicException If called without a matching section().
     */
    public function endSection(): void {
        $this->end();
    }

    /**
     * Outputs captured slot content inside a layout file. #AI:block
     *
     * Returns empty string if the slot was never captured (missing start/end pair).
     * Use hasSection() to check existence before calling.
     *
     * @param string $name Slot identifier matching a previous start() call.
     */
    public function block(string $name, string $default = ''): string {
        return $this->slots[$name] ?? $default;
    }

    /**
     * Checks if a named slot was captured. #AI:hasSection
     *
     * @param string $name Slot identifier to check.
     */
    public function hasSection(string $name): bool {
        return isset($this->slots[$name]);
    }

    /**
     * Backward compatibility alias for block(). #AI:slot
     *
     * @deprecated Use block() instead. Slot() will be removed in a future version.
     * @param string $name Slot identifier matching a previous start() call.
     */
    public function slot(string $name, string $default = ''): string {
        return $this->block($name, $default);
    }

    /**
     * Renders a template file and optionally wraps it in a layout. #AI:renderFile
     *
     * Resolves .html or .php extension, extracts data as local variables,
     * and applies the layout system. Non-slot output becomes the 'content' slot.
     *
     * @param string $template Template path relative to views root.
     * @throws \Skim\View\Exceptions\ViewException If the template file is not found.
     */
    public function renderFile(string $template): string {
        $base = $this->viewsPath . '/' . ltrim($template, '/');
        $file = is_file($base . '.html') ? $base . '.html' : $base . '.php';

        if (!is_file($file)) {
            throw new \Skim\View\Exceptions\ViewException("View template not found: {$template}");
        }

        // Extract data as local variables inside the template
        extract($this->data, \EXTR_SKIP);

        ob_start();
        include $file;
        $content = (string) ob_get_clean();

        if ($this->layoutName === null && $this->defaultLayout !== null) {
            $this->layoutName = $this->defaultLayout;
        }

        // If a layout was declared, wrap content and render layout
        if ($this->layoutName !== null) {
            // Capture any non-slot output as 'content' slot if not already set
            if (!isset($this->slots['content'])) {
                $this->slots['content'] = $content;
            }
            $layout = new self($this->viewsPath, $this->data, null);
            $layout->slots = $this->slots;
            return $layout->renderFile($this->layoutName);
        }

        return $content;
    }
}

#AI:class
#AI symbol: Skim\View\Template
#AI source_path: src/View/Template.php
#AI title: template
#AI description: Template context object providing include, layout, and slot system for PHP views.
#AI role: template context and layout engine
#AI layer: view
#AI badges: [template; layout; slots; partials]
#AI intro: `template` is the context object available as `$this` inside every PHP view file. It provides the layout system (layout/start/end/slot) and partial inclusion (include). Layout execution runs the child template first to capture slots, then renders the layout.
#AI lifecycle: instantiated by View::render() per render call, used as $this inside templates
#AI fallback: defaultLayout applied when template does not call layout()
#AI test_seam: instantiate directly with a views path and data array
#AI invariants: [start() must be paired with end(); Non-slot output becomes the 'content' slot automatically; include() does not inherit the parent's layout; .html extension is tried before .php]
#AI core_behaviors: [Layout system: child renders first, captures slots, then layout renders with slot() calls; Partial inclusion via include() with isolated data context; Data extracted as local variables via extract()]
#AI warnings: [end() without matching start() throws LogicException; extract() with EXTR_SKIP means data keys cannot override existing local variables]
#AI notes: The layout receives all captured slots from the child template. The 'content' slot is auto-populated with any non-slot output from the child.
#AI owns: slots array, layoutName, activeSlot state
#AI entry_points: [include; layout; start; end; section; endSection; block; hasSection; slot; renderFile]
#AI config_reads: []
#AI non_goals: [Does not handle fragment extraction (done by view class); Does not compile or cache templates; Does not escape output (use e() in templates)]
#AI side_effects: [Uses ob_start/ob_get_clean for slot capture and template rendering; extract() creates local variables in renderFile scope]
#AI flow: View::render() -> new Template() -> renderFile() -> include $file -> layout? -> render layout -> return HTML
#AI lifecycle_steps: [View::render() -> new Template(path, data, defaultLayout); -> renderFile($template); -> resolve .html/.php; -> extract data; -> ob_start + include; -> layout declared? -> capture content slot; -> render layout file; -> return HTML]
#AI section_order: [Template API; Layout System; Rendering; Architecture]
#AI architectural_notes: The layout system uses a two-pass approach: child template runs first to capture slots, then the layout renders with access to those slots. This avoids the need for output buffering the entire page.

#AI:include
#AI group: Template API
#AI frequency: high
#AI signature: public function include(string $template, array $extra = []): string
#AI contract: Renders a partial template within the current data context. The partial does not inherit the parent's layout.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views root, no extension needed.}; {name: $extra | type: array | required: false | desc: Additional data merged for this partial only.}]
#AI return_detail: {type: string | desc: Rendered partial HTML.}

#AI:layout
#AI group: Layout System
#AI frequency: medium
#AI signature: public function layout(string $name): void
#AI contract: Declares the layout to wrap this template. Must be called before any output.
#AI param_details: [{name: $name | type: string | required: true | desc: Layout template path relative to views root.}]

#AI:start
#AI group: Layout System
#AI frequency: medium
#AI signature: public function start(string $name): void
#AI contract: Starts capturing output into a named slot. Must be paired with end().
#AI param_details: [{name: $name | type: string | required: true | desc: Slot identifier used by the layout to retrieve content.}]
#AI side_effects: [Starts output buffering via ob_start()]

#AI:end
#AI group: Layout System
#AI frequency: medium
#AI signature: public function end(): void
#AI contract: Ends the slot capture started by start(). Stores captured output in the named slot.
#AI throws_details: [{type: \LogicException | desc: If called without a matching start().}]
#AI side_effects: [Ends output buffering via ob_get_clean()]

#AI:section
#AI group: Layout System
#AI frequency: medium
#AI signature: public function section(string $name): void
#AI contract: Alias for start() — provides a familiar section() API for Laravel/Symfony developers.
#AI param_details: [{name: $name | type: string | required: true | desc: Slot identifier used by the layout to retrieve content.}]
#AI side_effects: [Starts output buffering via ob_start()]

#AI:endSection
#AI group: Layout System
#AI frequency: medium
#AI signature: public function endSection(): void
#AI contract: Alias for end() — provides a familiar endSection() API for Laravel/Symfony developers.
#AI throws_details: [{type: \LogicException | desc: If called without a matching section().}]
#AI side_effects: [Ends output buffering via ob_get_clean()]

#AI:block
#AI group: Layout System
#AI frequency: medium
#AI signature: public function block(string $name, string $default = ''): string
#AI contract: Returns captured slot content. Returns $default if the slot was never captured. Use hasSection() to check existence.
#AI param_details: [{name: $name | type: string | required: true | desc: Slot identifier matching a previous start() call.}; {name: $default | type: string | required: false | desc: Default value returned when slot is not captured.}]
#AI return_detail: {type: string | desc: Captured slot HTML or default value.}

#AI:hasSection
#AI group: Layout System
#AI frequency: low
#AI signature: public function hasSection(string $name): bool
#AI contract: Checks if a named slot was captured by a previous start()/end() pair.
#AI param_details: [{name: $name | type: string | required: true | desc: Slot identifier to check.}]
#AI return_detail: {type: bool | desc: True if the slot exists, false otherwise.}

#AI:slot
#AI group: Layout System
#AI frequency: low
#AI signature: public function slot(string $name, string $default = ''): string
#AI contract: Backward compatibility alias for block(). Deprecated — use block() instead.
#AI param_details: [{name: $name | type: string | required: true | desc: Slot identifier matching a previous start() call.}; {name: $default | type: string | required: false | desc: Default value returned when slot is not captured.}]
#AI return_detail: {type: string | desc: Captured slot HTML or default value.}

#AI:renderFile
#AI group: Rendering
#AI frequency: internal
#AI signature: public function renderFile(string $template): string
#AI contract: Renders a template file, resolves .html/.php extension, extracts data as local variables, and applies the layout system.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views root.}]
#AI return_detail: {type: string | desc: Fully rendered HTML with layout applied.}
#AI throws_details: [{type: ViewException | desc: If the template file is not found.}]
#AI side_effects: [Uses extract() to create local variables; Uses output buffering for rendering]
