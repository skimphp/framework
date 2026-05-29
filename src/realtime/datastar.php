<?php declare(strict_types=1);

namespace skim\realtime;

/**
 * Datastar SSE helpers — higher-level wrappers over raw SSE for DOM patching.
 *
 * Use when pushing server-rendered HTML fragments or reactive signal updates
 * to a Datastar-enabled browser. Extends sse with Datastar protocol events:
 * merge fragments, remove elements, update signals, execute scripts.
 *
 * Example:
 *   return $res->stream(function(datastar $ds) {
 *       $ds->merge('<div id="status">Active</div>', '#status');
 *       $ds->signal(['loading' => false]);
 *   });
 *
 * Testing: Capture output with ob_start() in tests.
 *
 * #AI:class
 */
class datastar extends sse {
    /**
     * Sends an HTML fragment for Datastar to merge into the DOM. #AI:merge
     *
     * Example:
     *   $ds->merge('<p>Updated</p>', '#content', 'inner');
     *
     * @param string $html     HTML fragment to merge.
     * @param string $selector CSS selector of target element (e.g. '#user-card').
     * @param string $mode     Merge mode: 'morph' (default), 'inner', 'outer', 'prepend', 'append'.
     */
    public function merge(string $html, string $selector = '', string $mode = 'morph'): void {
        $lines = "fragments\n";
        if ($selector !== '') {
            $lines .= "selector {$selector}\n";
        }
        if ($mode !== 'morph') {
            $lines .= "mergeMode {$mode}\n";
        }
        foreach (explode("\n", trim($html)) as $line) {
            $lines .= "data {$line}\n";
        }
        $this->raw_event('datastar-merge-fragments', $lines);
    }

    /**
     * Tells Datastar to remove matching elements from the DOM. #AI:remove
     *
     * @param string $selector CSS selector of elements to remove (e.g. '#notification-1').
     */
    public function remove(string $selector): void {
        $this->raw_event('datastar-remove-fragments', "selector {$selector}\n");
    }

    /**
     * Merges key-value pairs into Datastar reactive signals. #AI:signal
     *
     * Triggers any computed signals or watchers that depend on the
     * changed values.
     *
     * Example:
     *   $ds->signal(['count' => 5, 'loading' => false]);
     *
     * @param array $signals Key-value pairs to merge into reactive signals.
     */
    public function signal(array $signals): void {
        $json = (string) json_encode($signals, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->raw_event('datastar-merge-signals', "data {$json}\n");
    }

    /**
     * Executes arbitrary JavaScript in the browser. #AI:script
     *
     * WARNING: Use only for actions impossible via signal/merge (e.g. scroll-to-top).
     * Avoid for business logic — keep JS minimal and server-rendered.
     *
     * @param string $js JavaScript code to evaluate in the browser.
     */
    public function script(string $js): void {
        foreach (explode("\n", trim($js)) as $line) {
            $this->raw_event('datastar-execute-script', "autoRemove true\ndata {$line}\n");
        }
    }

    /**
     * Renders a view fragment and merges it into the DOM in one call. #AI:view_fragment
     *
     * Combines view::render_fragment() + merge() for convenience.
     *
     * Example:
     *   $ds->view_fragment('users/card', ['user' => $user], 'user-card', '#user-card');
     *
     * @param string $template Template path relative to views directory.
     * @param array  $data     Variables passed to the template.
     * @param string $fragment Fragment name within the template.
     * @param string $selector CSS selector for merge target (empty = fragment default).
     */
    public function view_fragment(string $template, array $data, string $fragment, string $selector = ''): void {
        $html = \skim\view\view::render_fragment($template, $data, $fragment);
        $this->merge($html, $selector);
    }

    private function raw_event(string $event_type, string $body): void {
        echo "event: {$event_type}\n";
        foreach (explode("\n", rtrim($body)) as $line) {
            echo $line !== '' ? "{$line}\n" : "\n";
        }
        echo "\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}

#AI:class
#AI symbol: skim\realtime\datastar
#AI source_path: src/realtime/datastar.php
#AI title: datastar
#AI description: Datastar SSE helpers for DOM patching, signal updates, and script execution.
#AI role: Datastar SSE wrapper
#AI layer: realtime
#AI badges: [datastar; sse; realtime; dom-patching; signals]
#AI intro: `datastar` extends `sse` with Datastar protocol v0.20+ event types for merging HTML fragments, removing DOM elements, updating reactive signals, and executing browser JavaScript.
#AI lifecycle: created per-stream by response::stream() callback; extends sse lifecycle
#AI test_seam: capture output with ob_start()/ob_get_clean() in tests
#AI invariants: [Extends sse — inherits send/ping/close; Each method emits a specific Datastar event type; script() auto-removes after execution]
#AI core_behaviors: [merge() sends datastar-merge-fragments events; remove() sends datastar-remove-fragments; signal() sends datastar-merge-signals; script() sends datastar-execute-script]
#AI owns: output stream (inherited from sse)
#AI entry_points: [merge; remove; signal; script; view_fragment]
#AI config_reads: []
#AI non_goals: [Does not manage Datastar client-side setup; Does not handle WebSocket; Does not validate HTML fragments]
#AI side_effects: [Writes to PHP output buffer; Calls flush()]
#AI flow: controller -> res->stream(fn($ds) => $ds->merge(...)) -> raw_event() -> echo Datastar SSE format -> flush()
#AI section_order: [DOM Operations; Signal Operations; Script Execution; View Integration]
#AI warnings: [script() executes arbitrary JavaScript in the browser — use sparingly and never with user-supplied input]

#AI:merge
#AI group: DOM Operations
#AI frequency: high
#AI signature: public function merge(string $html, string $selector = '', string $mode = 'morph'): void
#AI contract: Sends an HTML fragment as a datastar-merge-fragments event for the client to merge into the DOM at the specified selector.
#AI param_details: [{name: $html | type: string | required: true | desc: HTML fragment to merge into the DOM.}; {name: $selector | type: string | required: false | desc: CSS selector of target element. Empty uses fragment default.}; {name: $mode | type: string | required: false | desc: Merge mode: morph (default), inner, outer, prepend, append.}]
#AI side_effects: [Writes SSE event to output buffer and flushes]

#AI:remove
#AI group: DOM Operations
#AI frequency: medium
#AI signature: public function remove(string $selector): void
#AI contract: Sends a datastar-remove-fragments event telling the client to remove elements matching the CSS selector.
#AI param_details: [{name: $selector | type: string | required: true | desc: CSS selector of elements to remove.}]
#AI side_effects: [Writes SSE event to output buffer and flushes]

#AI:signal
#AI group: Signal Operations
#AI frequency: high
#AI signature: public function signal(array $signals): void
#AI contract: Merges key-value pairs into Datastar reactive signals via a datastar-merge-signals event. Triggers dependent computed signals and watchers.
#AI param_details: [{name: $signals | type: array | required: true | desc: Key-value pairs to merge into reactive signals.}]
#AI side_effects: [Writes SSE event to output buffer and flushes]

#AI:script
#AI group: Script Execution
#AI frequency: low
#AI signature: public function script(string $js): void
#AI contract: Sends JavaScript for the browser to evaluate via a datastar-execute-script event. Auto-removes after execution.
#AI param_details: [{name: $js | type: string | required: true | desc: JavaScript code to evaluate in the browser.}]
#AI warnings: [Executes arbitrary JavaScript in the browser — never pass user-supplied input; Use only for actions impossible via signal/merge]
#AI side_effects: [Writes SSE event to output buffer and flushes; Executes JS in browser]

#AI:view_fragment
#AI group: View Integration
#AI frequency: medium
#AI signature: public function view_fragment(string $template, array $data, string $fragment, string $selector = ''): void
#AI contract: Renders a named view fragment and merges it into the DOM in one call. Combines view::render_fragment() with merge().
#AI param_details: [{name: $template | type: string | required: true | desc: Template path relative to views directory.}; {name: $data | type: array | required: true | desc: Variables passed to the template.}; {name: $fragment | type: string | required: true | desc: Fragment name within the template.}; {name: $selector | type: string | required: false | desc: CSS selector for merge target.}]
#AI side_effects: [Writes SSE event to output buffer and flushes]
