<?php declare(strict_types=1);

namespace Skim\View;

use Skim\Dev\Profiler;
use Skim\Worker\Resettable;

/**
 * Static facade for rendering PHP templates with fragment extraction. #AI:class
 *
 * Use when controllers need to render full pages or htmx/datastar fragments.
 * Templates are native PHP files in app/views/ — no Twig, no Blade.
 * Fragment extraction uses HTML comment markers (<!-- @fragment name -->...<!-- @end -->).
 *
 * Example:
 *   $html = view::render('users/show', ['user' => $user]);
 *   $fragment = view::render('users/show', ['user' => $user], 'user-card');
 *
 * Testing: Use set_path() to point to test fixtures, reset() in tearDown().
 *
 * #AI:class
 */
final class View implements \Skim\Worker\Resettable {
    private static string  $views_path     = '';
    private static array   $shared_data    = [];
    private static ?string $default_layout = null;

    /**
     * Clears shared data between requests in worker mode. #AI:resetRequest
     */
    public static function resetRequest(): void {
        self::$shared_data = [];
    }

    /**
     * Renders a template, optionally extracting a named fragment. #AI:render
     *
     * Merges $data with shared data, renders through the template/layout system,
     * and records timing in the profiler. When $fragment is set, layout is bypassed
     * and only the named <!-- @fragment name -->...<!-- @end --> block is returned.
     *
     * Example:
     *   view::render('users/show', ['user' => $user]);
     *   view::render('users/show', ['user' => $user], 'user-card');
     *
     * @param string      $template Template path relative to views root.
     * @param array       $data     Data merged with shared data for this render.
     * @param string|null $fragment Named fragment to extract, or null for full page.
     * @throws \Skim\View\Exceptions\ViewException If template or fragment is not found.
     */
    public static function render(string $template, array $data = [], ?string $fragment = null): string {
        $t = microtime(true);

        // CRITICAL: Disable layout if only a fragment is needed for HTMX performance
        $layout = $fragment ? null : self::$default_layout;

        $ctx = new \Skim\View\Template(self::viewsPath(), array_merge(self::$shared_data, $data), $layout, $fragment !== null);
        $html = $ctx->renderFile($template);

        if ($fragment !== null) {
            $html = self::extractFragment($html, $fragment, $template);
        }

        \Skim\Dev\Profiler::view($template, $fragment, (microtime(true) - $t) * 1000);

        return $html;
    }

    /**
     * Renders only the named fragment — shorthand for render() with $fragment. #AI:renderFragment
     *
     * @param string $template Template path relative to views root.
     * @param array  $data     Data for this render.
     * @param string $fragment Named fragment to extract.
     */
    public static function renderFragment(string $template, array $data, string $fragment): string {
        return self::render($template, $data, $fragment);
    }

    /**
     * Injects data into every subsequent render for this request. #AI:share
     *
     * Use for cross-cutting data like current_user or app_name.
     *
     * @param string $key   Shared variable name available in all templates.
     * @param mixed  $value Shared variable value.
     */
    public static function share(string $key, mixed $value): void {
        self::$shared_data[$key] = $value;
    }
    /**
     * Returns a shared data value, or null if not set. #AI:get_shared
     *
     * @param string $key Shared variable name.
     */
    public static function getShared(string $key): mixed {
        return self::$shared_data[$key] ?? null;
    }

    /**
     * Sets the root directory for template resolution. #AI:setPath
     *
     * Called during app boot. Defaults to SKIM_ROOT/app/views.
     *
     * @param string $path Absolute path to the views directory.
     */
    public static function setPath(string $path): void {
        self::$views_path = rtrim($path, '/');
    }

    /**
     * Sets a default layout applied to every root render. #AI:setDefaultLayout
     *
     * Templates that call $this->layout() override this. Pass null to disable.
     *
     * @param string|null $name Layout template path, or null to disable.
     */
    public static function setDefaultLayout(?string $name): void {
        self::$default_layout = $name;
    }

    /**
     * Clears shared data, path, and default layout (testing only). #AI:reset
     *
     * Call in tearDown() to restore defaults between tests.
     */
    public static function reset(): void {
        self::$views_path     = '';
        self::$shared_data    = [];
        self::$default_layout = null;
    }

    /**
     * Renders an isolated component with strict props. #AI:component
     *
     * Delegates to component_renderer::render(). Supports both array props
     * (legacy) and *_props readonly objects (new). Components render in clean
     * scope with no parent data leakage.
     *
     * Example:
     *   view::component('alert', ['message' => 'test']);
     *   view::component('alert', new alert_props(message: 'test'));
     *
     * @param string $name Component name (maps to views/components/{$name}.php).
     * @param array|object $props Props array or *_props readonly object.
     * @throws \Skim\View\Exceptions\ViewException If props object is not a *_props class or component is missing.
     */
    public static function component(string $name, array|object $props = []): string {
        return \Skim\View\ComponentRenderer::render($name, $props);
    }

    // --- internals ---

    public static function viewsPath(): string {
        if (self::$views_path !== '') {
            return self::$views_path;
        }
        $root = defined('SKIM_ROOT') ? \SKIM_ROOT : dirname(__DIR__, 3);
        return $root . '/app/views';
    }

    private static function extractFragment(string $html, string $name, string $template): string {
        try {
            return \Skim\View\FragmentExtractor::extract($html, $name);
        } catch (\Skim\View\Exceptions\ViewException $e) {
            throw new \Skim\View\Exceptions\ViewException(
                "Fragment extraction failed in {$template}: {$e->getMessage()}"
            );
        }
    }
}

#AI:class
#AI symbol: Skim\View\View
#AI source_path: src/view/view.php
#AI title: view
#AI description: Static facade for rendering PHP templates with fragment extraction and profiler integration.
#AI role: static view facade
#AI layer: view
#AI badges: [facade; view; fragments; profiler]
#AI intro: `view` is the static entry point for template rendering. It supports full page rendering, named fragment extraction for htmx/datastar, shared data injection, and configurable layout defaults.
#AI lifecycle: static facade, state persists for the current request
#AI fallback: defaults to SKIM_ROOT/app/views when path is not set
#AI test_seam: set_path() for test fixtures, reset() in tearDown()
#AI invariants: [Shared data is merged with per-render data; Fragment extraction uses state-machine parser on rendered HTML; Profiler records every render call; Default layout is overridden by template-level layout() calls]
#AI core_behaviors: [Full page rendering via template context; Fragment extraction via HTML comment markers; Shared data injection for cross-cutting concerns; Profiler integration for render timing]
#AI warnings: [Fragment extraction renders the full template first, then extracts — layout bypass optimization reduces this cost for HTMX requests]
#AI notes: Fragment syntax uses HTML comments (<!-- @fragment name -->...<!-- @end -->) which produce zero bytes in browser output and no DOM changes.
#AI owns: views_path, shared_data, default_layout
#AI entry_points: [render; render_fragment; share; set_path; set_default_layout; reset; views_path; component]
#AI config_reads: []
#AI non_goals: [Does not compile or cache templates; Does not escape output; Does not handle asset bundling]
#AI side_effects: [Records render timing in profiler::view(); share() mutates static shared_data]
#AI flow: view::render() -> template::renderFile() -> layout system -> fragment extraction? -> profiler::view()
#AI lifecycle_steps: [view::render($template, $data, $fragment); -> resolve views_path; -> new template(path, merged_data, default_layout); -> template::renderFile(); -> fragment? -> extract_fragment(); -> profiler::view(); -> return HTML]
#AI section_order: [Rendering API; Configuration; Testing Hooks; Architecture]
#AI architectural_notes: Uses native PHP templates for real stack traces and opcache performance. Fragment extraction is a post-render state-machine pass — the full template always renders first.

#AI:render
#AI group: Rendering API
#AI frequency: high
#AI signature: public static function render(string $template, array $data = [], ?string $fragment = null): string
#AI contract: Renders a template with shared data merged in. When $fragment is set, bypasses layout and extracts only the named fragment block. Records timing in profiler.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views root.}; {name: $data | type: array | required: false | desc: Data merged with shared data for this render.}; {name: $fragment | type: ?string | required: false | desc: Named fragment to extract, or null for full page.}]
#AI return_detail: {type: string | desc: Rendered HTML or extracted fragment.}
#AI throws_details: [{type: view_exception | desc: If template file or fragment name is not found.}]
#AI side_effects: [Records render timing in profiler::view()]

#AI:renderFragment
#AI group: Rendering API
#AI frequency: medium
#AI signature: public static function renderFragment(string $template, array $data, string $fragment): string
#AI contract: Shorthand for render() with $fragment set. Renders only the named fragment block.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views root.}; {name: $data | type: array | required: true | desc: Data for this render.}; {name: $fragment | type: string | required: true | desc: Named fragment to extract.}]
#AI return_detail: {type: string | desc: Extracted fragment HTML.}

#AI:share
#AI group: Configuration
#AI frequency: medium
#AI signature: public static function share(string $key, mixed $value): void
#AI contract: Injects a key-value pair into every subsequent template render for this request.
#AI param_details: [{name: $key | type: string | required: true | desc: Shared variable name available in all templates.}; {name: $value | type: mixed | required: true | desc: Shared variable value.}]
#AI side_effects: [Mutates static shared_data array]

#AI:setPath
#AI group: Configuration
#AI frequency: low
#AI signature: public static function setPath(string $path): void
#AI contract: Sets the root directory for template resolution. Called during app boot.
#AI param_details: [{name: $path | type: string | required: true | desc: Absolute path to the views directory.}]

#AI:setDefaultLayout
#AI group: Configuration
#AI frequency: low
#AI signature: public static function setDefaultLayout(?string $name): void
#AI contract: Sets a default layout applied to every root render that does not call $this->layout(). Pass null to disable.
#AI param_details: [{name: $name | type: ?string | required: true | desc: Layout template path, or null to disable.}]

#AI:reset
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function reset(): void
#AI contract: Clears shared data, views path, and default layout. Use in test tearDown().
#AI side_effects: [Clears all static state]

#AI:resetRequest
#AI group: Testing Hooks
#AI frequency: internal
#AI signature: public static function resetRequest(): void
#AI contract: Clears shared data between requests in worker mode.
#AI side_effects: [Empties static $shared_data array]

#AI:component
#AI group: Rendering API
#AI frequency: high
#AI signature: public static function component(string $name, array|object $props = []): string
#AI contract: Renders an isolated component with strict props. Delegates to component_renderer::render(). Supports both array props (legacy) and *_props readonly objects (new).
#AI param_details: [{name: $name | type: string | required: true | desc: Component name (maps to views/components/{$name}.php).}; {name: $props | type: array|object | required: false | desc: Props array or *_props readonly object.}]
#AI return_detail: {type: string | desc: Rendered component HTML.}
#AI throws_details: [{type: view_exception | desc: If props object is not a *_props class or component file is not found.}]
