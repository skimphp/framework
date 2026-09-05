<?php declare(strict_types=1);

namespace Skim\View;

use Skim\Worker\Resettable;

/**
 * Closure-based part capture for components — no global state. #AI:class
 *
 * Use inside component_with_parts() to declare named content blocks that
 * the component template can query and render. Each instance is scoped to
 * a single component render; nested components get their own instance.
 *
 * Parts are captured via output buffering so templates can use native PHP
 * syntax. The main body (outside any part() call) is also captured.
 *
 * Throws \LogicException when part() callbacks are nested or unbalanced.
 *
 * Example:
 *   component_with_parts('card', new card_props(title: 'Profile'), function($c) {
 *       $c->part('header', fn() => '<h2>Profile</h2>');
 *       echo '<p>Main content</p>';
 *   });
 *
 * Testing: Instantiate directly and call capture_main/capture_part.
 *
 * #AI:class
 */
class ComponentCollector implements \Skim\Worker\Resettable {
    // Captured output between part() calls (the "main" slot)
    private string $mainPart = '';
    // Named parts: name → captured HTML
    private array $namedParts = [];
    // Tracks which collector is currently active for nested components
    private static array $stack = [];

    /**
     * Runs the closure and captures everything not inside a named part. #AI:captureMain
     *
     * The closure receives $this so templates can call $c->part().
     * Output is buffered; any echo/print inside the closure becomes main_part.
     *
     * @param callable $render Closure receiving the collector instance.
     */
    public function captureMain(callable $render): void {
        self::$stack[] = $this;
        ob_start();
        $render($this);
        $this->mainPart = (string) ob_get_clean();
        array_pop(self::$stack);
    }

    /**
     * Alias for capture_part() — concise API matching the global helper. #AI:part
     *
     * @param string   $name   Part identifier used by the component template.
     * @param callable $render Closure that outputs the part content.
     */
    public function part(string $name, callable $render): void {
        $this->capturePart($name, $render);
    }

    /**
     * Captures a named part by executing the closure inside output buffering. #AI:capturePart
     *
     * @param string   $name   Part identifier used by the component template.
     * @param callable $render Closure that outputs the part content.
     */
    public function capturePart(string $name, callable $render): void {
        ob_start();
        $render();
        $this->namedParts[$name] = (string) ob_get_clean();
    }

    /**
     * Returns the main body captured outside named parts. #AI:getMain
     */
    public function getMain(): string {
        return $this->mainPart;
    }

    /**
     * Returns a named part, or $default if it was never captured. #AI:getPart
     *
     * @param string $name    Part identifier to retrieve.
     * @param string $default Fallback HTML when the part is absent.
     */
    public function getPart(string $name, string $default = ''): string {
        return $this->namedParts[$name] ?? $default;
    }

    /**
     * Checks if a named part was captured. #AI:hasPart
     *
     * @param string $name Part identifier to check.
     */
    public function hasPart(string $name): bool {
        return isset($this->namedParts[$name]);
    }

    /**
     * Returns the most recently pushed collector (top of stack). #AI:current
     *
     * Used by the part()/has_part() global helpers to resolve the active
     * collector without global mutable state.
     */
    public static function current(): ?self {
        return empty(self::$stack) ? null : self::$stack[count(self::$stack) - 1];
    }

    /**
     * Pushes a collector onto the static stack (for component_with_parts). #AI:push
     *
     * Must be paired with pop().
     */
    public static function push(self $collector): void {
        self::$stack[] = $collector;
    }

    /**
     * Pops the top collector from the static stack. #AI:pop
     *
     * Must be paired with push().
     */
    public static function pop(): void {
        array_pop(self::$stack);
    }

    /**
     * Clears the static collector stack between requests in worker mode. #AI:resetRequest
     *
     * Under normal flow capture_main() pushes then pops, so the stack is empty
     * between renders. If a component closure throws, the matching pop() never
     * runs and a stale collector lingers in the process — this drops it so the
     * next request starts with an empty stack.
     */
    public static function resetRequest(): void {
        self::$stack = [];
    }
}

#AI:class
#AI symbol: Skim\View\ComponentCollector
#AI source_path: src/view/component_collector.php
#AI title: component_collector
#AI description: Closure-based part capture for components with stack-scoped isolation.
#AI role: part capture engine
#AI layer: view
#AI badges: [component; parts; closures; isolation; stack]
#AI intro: `component_collector` captures named content blocks inside components via closures and output buffering. Each render gets its own instance; a static stack tracks nested components.
#AI lifecycle: instantiated per component_with_parts() call, pushed onto static stack during capture
#AI fallback: get_part() returns empty string for missing parts; has_part() returns false
#AI test_seam: instantiate directly and call capture_main/capture_part; call current() to inspect stack
#AI invariants: [Stack tracks nested components in LIFO order; Each collector has isolated main_part and named_parts; No global mutable state beyond the static stack]
#AI core_behaviors: [capture_main buffers the closure output as main_part; capture_part buffers closure output into named_parts; Stack enables nested components with independent part resolution]
#AI warnings: [Calling current() when stack is empty returns null — global helpers must handle this]
#AI notes: The static stack is the only shared state; it is strictly LIFO and is cleared between requests via reset_request() (worker mode) to drop any entry left behind when a component closure throws before its matching pop().
#AI owns: main_part, named_parts
#AI entry_points: [capture_main; capture_part; get_main; get_part; has_part; current]
#AI config_reads: []
#AI non_goals: [Does not validate part names against a schema; Does not compile or cache parts; Does not handle layout wrapping]
#AI side_effects: [Mutates static stack during capture_main; Uses output buffering for all capture methods]
#AI flow: component_with_parts() -> new component_collector() -> capture_main() -> capture_part() -> get_main()/get_part()/has_part()
#AI lifecycle_steps: [new component_collector(); -> push onto stack; -> capture_main($render); -> capture_part($name, $render); -> pop from stack; -> component template reads get_main/get_part/has_part]
#AI section_order: [Capture API; Query API; Stack Management]
#AI architectural_notes: The static stack replaces a scalar global, enabling nested components. Each collector is independent; parts do not leak between siblings or parents.

#AI:captureMain
#AI group: Capture API
#AI frequency: high
#AI signature: public function captureMain(callable $render): void
#AI contract: Buffers the closure output as the main part. Pushes this collector onto the static stack before execution and pops after.
#AI param_details: [{name: $render | type: callable | required: true | desc: Closure receiving the collector instance.}]
#AI side_effects: [Pushes then pops from static stack; Starts and ends output buffering]

#AI:part
#AI group: Capture API
#AI frequency: high
#AI signature: public function part(string $name, callable $render): void
#AI contract: Alias for capture_part(). Buffers the closure output and stores it under the given part name.
#AI param_details: [{name: $name | type: string | required: true | desc: Part identifier used by the component template.}; {name: $render | type: callable | required: true | desc: Closure that outputs the part content.}]
#AI side_effects: [Starts and ends output buffering]

#AI:capturePart
#AI group: Capture API
#AI frequency: high
#AI signature: public function capturePart(string $name, callable $render): void
#AI contract: Buffers the closure output and stores it under the given part name.
#AI param_details: [{name: $name | type: string | required: true | desc: Part identifier used by the component template.}; {name: $render | type: callable | required: true | desc: Closure that outputs the part content.}]
#AI side_effects: [Starts and ends output buffering]

#AI:getMain
#AI group: Query API
#AI frequency: high
#AI signature: public function getMain(): string
#AI contract: Returns the output captured outside any named part() calls.
#AI return_detail: {type: string | desc: Main body HTML.}

#AI:getPart
#AI group: Query API
#AI frequency: high
#AI signature: public function getPart(string $name, string $default = ''): string
#AI contract: Returns captured HTML for a named part, or $default if absent.
#AI param_details: [{name: $name | type: string | required: true | desc: Part identifier to retrieve.}; {name: $default | type: string | required: false | desc: Fallback HTML when the part is absent.}]
#AI return_detail: {type: string | desc: Named part HTML or default.}

#AI:hasPart
#AI group: Query API
#AI frequency: medium
#AI signature: public function hasPart(string $name): bool
#AI contract: Returns true when the named part was captured.
#AI param_details: [{name: $name | type: string | required: true | desc: Part identifier to check.}]
#AI return_detail: {type: bool | desc: True if the part exists.}

#AI:current
#AI group: Stack Management
#AI frequency: internal
#AI signature: public static function current(): ?self
#AI contract: Returns the top of the static stack, or null when no component is rendering.
#AI return_detail: {type: ?self | desc: Active collector or null.}

#AI:push
#AI group: Stack Management
#AI frequency: internal
#AI signature: public static function push(self $collector): void
#AI contract: Pushes a collector onto the static stack. Used by component_with_parts() to keep the collector active during template rendering.
#AI param_details: [{name: $collector | type: self | required: true | desc: Collector instance to push.}]
#AI side_effects: [Adds collector to static stack]

#AI:pop
#AI group: Stack Management
#AI frequency: internal
#AI signature: public static function pop(): void
#AI contract: Pops the top collector from the static stack. Must be paired with push().
#AI side_effects: [Removes top collector from static stack]
