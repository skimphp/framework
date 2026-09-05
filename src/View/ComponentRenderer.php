<?php declare(strict_types=1);

namespace Skim\View;

use Skim\Dev\Profiler;
use Skim\View\Exceptions\ViewException;

/**
 * Isolated component renderer with strict props validation and clean scope. #AI:class
 *
 * Use when templates need reusable UI pieces (alerts, cards, buttons) that must
 * not leak parent variables. Supports both legacy arrays and readonly *_props objects.
 *
 * Props are validated: array passes through, objects must end in '_props'.
 * The component template receives ONLY the props data — no shared context,
 * no layout wrapping, no parent slot contamination.
 *
 * Throws view_exception when props object naming is wrong or template is missing.
 *
 * Example:
 *   ComponentRenderer::render('alert', ['message' => 'Saved']);
 *   ComponentRenderer::render('alert', new AlertProps(message: 'Saved'));
 *
 * Testing: Point View::setPath() to fixture directory before calling.
 *
 * #AI:class
 */
class ComponentRenderer {

    /**
     * Renders a component with strict props isolation. #AI:render
     *
     * Validates *_props naming when an object is passed, converts props to an
     * array via get_object_vars(), and renders in a clean template scope.
     * Records timing in the profiler under the 'components/' prefix.
     *
     * @param string       $name  Component name (maps to views/components/{$name}.php).
     * @param array|object $props Props array or a readonly *_props object.
     * @throws \Skim\View\Exceptions\ViewException If props object is not a *_props class or file is missing.
     */
    public static function render(string $name, array|object $props = []): string {
        $t = microtime(true);

        if (is_object($props)) {
            self::validatePropsClass($props::class);
        }

        // NOTE: get_object_vars() only exposes public properties.
        $data = is_object($props) ? get_object_vars($props) : $props;

        $ctx  = new \Skim\View\Template(self::componentsPath(), $data, null);
        $html = $ctx->renderFile($name);

        \Skim\Dev\Profiler::view('components/' . $name, null, (microtime(true) - $t) * 1000);

        return $html;
    }

    /**
     * Ensures object props use the *Props naming convention. #AI:validatePropsClass
     *
     * @param string $class FQCN of the props object.
     * @throws \Skim\View\Exceptions\ViewException When the class name does not end in 'Props'.
     */
    private static function validatePropsClass(string $class): void {
        if (!str_ends_with($class, 'Props')) {
            throw new \Skim\View\Exceptions\ViewException(
                "Props object must be a *Props class, got: {$class}"
            );
        }
    }

    /**
     * Resolves the components subdirectory under the active views path. #AI:componentsPath
     */
    private static function componentsPath(): string {
        return \Skim\View\View::viewsPath() . '/components';
    }
}

#AI:class
#AI symbol: Skim\View\ComponentRenderer
#AI source_path: src/View/ComponentRenderer.php
#AI title: ComponentRenderer
#AI description: Isolated component renderer enforcing strict props validation and clean template scope.
#AI role: component renderer
#AI layer: view
#AI badges: [component; props; isolation; profiler]
#AI intro: `ComponentRenderer` renders reusable UI components in an isolated scope. It supports both legacy array props and readonly *_props objects, validating the latter to enforce naming conventions.
#AI lifecycle: stateless static class, invoked per component render
#AI fallback: n/a — stateless
#AI test_seam: use View::setPath() to redirect to test fixtures
#AI invariants: [Props objects must end in '_props'; Component templates receive ONLY props data, no shared context; No layout wrapping is applied; Profiler records every component render]
#AI core_behaviors: [Dual props: array (legacy) and readonly *_props object (new); Strict scope isolation via new Template() with null layout; Props class naming validation; Profiler integration for component timing]
#AI warnings: [get_object_vars() only sees public properties — declare DTO props as public readonly]
#AI notes: Components are rendered with a fresh template instance and null layout, guaranteeing no parent template leakage.
#AI owns: nothing
#AI entry_points: [render]
#AI config_reads: []
#AI non_goals: [Does not support layout wrapping; Does not cache rendered components; Does not validate prop keys against component expectations]
#AI side_effects: [Records component render timing in Profiler::view()]
#AI flow: render() -> validatePropsClass() -> get_object_vars() -> new Template() -> renderFile() -> Profiler::view() -> return HTML
#AI lifecycle_steps: [render($name, $props); -> is_object($props)? validatePropsClass(); -> $data = is_object? get_object_vars() : $props; -> new Template(componentsPath(), $data, null); -> renderFile($name); -> Profiler::view(); -> return HTML]
#AI section_order: [Rendering API; Validation; Path Resolution]
#AI architectural_notes: Components are intentionally isolated from the layout and shared data systems. This prevents accidental variable leakage and makes components predictable and testable.

#AI:render
#AI group: Rendering API
#AI frequency: high
#AI signature: public static function render(string $name, array|object $props = []): string
#AI contract: Renders a component template in an isolated scope with validated props. Records timing in profiler.
#AI param_details: [{name: $name | type: string | required: true | desc: Component name (maps to views/components/{$name}.php).}; {name: $props | type: array|object | required: false | desc: Props array or *_props readonly object.}]
#AI return_detail: {type: string | desc: Rendered component HTML.}
#AI throws_details: [{type: ViewException | desc: If props object is not a *_props class or component file is not found.}]
#AI side_effects: [Records render timing in Profiler::view()]

#AI:validatePropsClass
#AI group: Validation
#AI frequency: internal
#AI signature: private static function validatePropsClass(string $class): void
#AI contract: Throws when the class name does not end with '_props'. Enforces the props naming convention.
#AI param_details: [{name: $class | type: string | required: true | desc: FQCN of the props object.}]
#AI throws_details: [{type: ViewException | desc: When the class name does not end in '_props'.}]

#AI:componentsPath
#AI group: Path Resolution
#AI frequency: internal
#AI signature: private static function componentsPath(): string
#AI contract: Resolves the components subdirectory under the active views path by delegating to View::viewsPath().
#AI return_detail: {type: string | desc: Absolute path to the views/components directory.}
