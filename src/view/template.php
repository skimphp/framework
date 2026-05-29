<?php declare(strict_types=1);

namespace skim\view;

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
class template {
    private string  $views_path;
    private array   $data;
    private ?string $layout_name  = null;
    private array   $slots        = [];   // name → captured HTML
    private ?string $active_slot  = null;

    public function __construct(string $views_path, array $data = [], private readonly ?string $default_layout = null) {
        $this->views_path = rtrim($views_path, '/');
        $this->data       = $data;
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
        return (new self($this->views_path, array_merge($this->data, $extra), null))->render_file($template);
    }

    /**
     * Declares the layout to wrap this template. #AI:layout
     *
     * Must be called before any output. The layout file uses $this->slot()
     * to inject child content. If not called, the default_layout is used.
     *
     * @param string $name Layout template path relative to views root.
     */
    public function layout(string $name): void {
        $this->layout_name = $name;
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
        $this->active_slot = $name;
        ob_start();
    }

    /**
     * Ends the slot capture started by start(). #AI:end
     *
     * @throws \LogicException If called without a matching start().
     */
    public function end(): void {
        if ($this->active_slot === null) {
            throw new \LogicException('end() called without matching start()');
        }
        $this->slots[$this->active_slot] = (string) ob_get_clean();
        $this->active_slot               = null;
    }

    /**
     * Outputs captured slot content inside a layout file. #AI:slot
     *
     * Returns empty string if the slot was never captured (missing start/end pair).
     *
     * @param string $name Slot identifier matching a previous start() call.
     */
    public function slot(string $name): string {
        return $this->slots[$name] ?? '';
    }

    /**
     * Renders a template file and optionally wraps it in a layout. #AI:render_file
     *
     * Resolves .html or .php extension, extracts data as local variables,
     * and applies the layout system. Non-slot output becomes the 'content' slot.
     *
     * @param string $template Template path relative to views root.
     * @throws exceptions\view_exception If the template file is not found.
     */
    public function render_file(string $template): string {
        $base = $this->views_path . '/' . ltrim($template, '/');
        $file = is_file($base . '.html') ? $base . '.html' : $base . '.php';

        if (!is_file($file)) {
            throw new exceptions\view_exception("View template not found: {$template}");
        }

        // Extract data as local variables inside the template
        extract($this->data, \EXTR_SKIP);

        ob_start();
        include $file;
        $content = (string) ob_get_clean();

        if ($this->layout_name === null && $this->default_layout !== null) {
            $this->layout_name = $this->default_layout;
        }

        // If a layout was declared, wrap content and render layout
        if ($this->layout_name !== null) {
            // Capture any non-slot output as 'content' slot if not already set
            if (!isset($this->slots['content'])) {
                $this->slots['content'] = $content;
            }
            $layout = new self($this->views_path, $this->data, null);
            $layout->slots = $this->slots;
            return $layout->render_file($this->layout_name);
        }

        return $content;
    }
}

#AI:class
#AI symbol: skim\view\template
#AI source_path: src/view/template.php
#AI title: template
#AI description: Template context object providing include, layout, and slot system for PHP views.
#AI role: template context and layout engine
#AI layer: view
#AI badges: [template; layout; slots; partials]
#AI intro: `template` is the context object available as `$this` inside every PHP view file. It provides the layout system (layout/start/end/slot) and partial inclusion (include). Layout execution runs the child template first to capture slots, then renders the layout.
#AI lifecycle: instantiated by view::render() per render call, used as $this inside templates
#AI fallback: default_layout applied when template does not call layout()
#AI test_seam: instantiate directly with a views path and data array
#AI invariants: [start() must be paired with end(); Non-slot output becomes the 'content' slot automatically; include() does not inherit the parent's layout; .html extension is tried before .php]
#AI core_behaviors: [Layout system: child renders first, captures slots, then layout renders with slot() calls; Partial inclusion via include() with isolated data context; Data extracted as local variables via extract()]
#AI warnings: [end() without matching start() throws LogicException; extract() with EXTR_SKIP means data keys cannot override existing local variables]
#AI notes: The layout receives all captured slots from the child template. The 'content' slot is auto-populated with any non-slot output from the child.
#AI owns: slots array, layout_name, active_slot state
#AI entry_points: [include; layout; start; end; slot; render_file]
#AI config_reads: []
#AI non_goals: [Does not handle fragment extraction (done by view class); Does not compile or cache templates; Does not escape output (use e() in templates)]
#AI side_effects: [Uses ob_start/ob_get_clean for slot capture and template rendering; extract() creates local variables in render_file scope]
#AI flow: view::render() -> new template() -> render_file() -> include $file -> layout? -> render layout -> return HTML
#AI lifecycle_steps: [view::render() -> new template(path, data, default_layout); -> render_file($template); -> resolve .html/.php; -> extract data; -> ob_start + include; -> layout declared? -> capture content slot; -> render layout file; -> return HTML]
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

#AI:slot
#AI group: Layout System
#AI frequency: medium
#AI signature: public function slot(string $name): string
#AI contract: Returns captured slot content. Returns empty string if the slot was never captured.
#AI param_details: [{name: $name | type: string | required: true | desc: Slot identifier matching a previous start() call.}]
#AI return_detail: {type: string | desc: Captured slot HTML or empty string.}

#AI:render_file
#AI group: Rendering
#AI frequency: internal
#AI signature: public function render_file(string $template): string
#AI contract: Renders a template file, resolves .html/.php extension, extracts data as local variables, and applies the layout system.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views root.}]
#AI return_detail: {type: string | desc: Fully rendered HTML with layout applied.}
#AI throws_details: [{type: view_exception | desc: If the template file is not found.}]
#AI side_effects: [Uses extract() to create local variables; Uses output buffering for rendering]
