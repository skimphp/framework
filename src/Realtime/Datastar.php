<?php declare(strict_types=1);

namespace Skim\Realtime;

use Skim\Realtime\Contract\ElementPatcher;
use Skim\Realtime\Contract\SignalPatcher;
use Skim\Realtime\Contract\ScriptRunner;

/**
 * Datastar v1 SSE driver — implements the official v1 wire protocol.
 *
 * Injected with an SSE transport so the same driver can later run over
 * WebSocket or other transports. Controllers should type-hint the
 * interfaces (element_patcher, signal_patcher, script_runner), never
 * this concrete class.
 *
 * Example:
 *   return $res->stream(function(ElementPatcher $ds) {
 *       $ds->patch('<div id="status">Active</div>', '#status', 'inner');
 *       $ds->signals(['loading' => false]);
 *   });
 *
 * #AI:class
 */
class Datastar implements \Skim\Realtime\Contract\ElementPatcher, \Skim\Realtime\Contract\SignalPatcher, \Skim\Realtime\Contract\ScriptRunner {
    public function __construct(private \Skim\Realtime\Sse $transport) {}

    /**
     * Patches the DOM with an HTML fragment using Datastar v1 protocol. #AI:patch
     *
     * @param string $html     HTML fragment to patch.
     * @param string $selector CSS selector of target element (e.g. '#content').
     * @param string $mode     Patch mode: 'morph', 'inner', 'outer', 'prepend', 'append', 'before', 'after', 'replace', 'remove'.
     */
    public function patch(string $html, string $selector = '', string $mode = 'morph'): static {
        $lines = [];
        if ($selector !== '') {
            $lines[] = "selector {$selector}";
        }
        if ($mode !== 'morph') {
            $lines[] = "mode {$mode}";
        }
        foreach (explode("\n", $html) as $line) {
            $lines[] = "elements {$line}";
        }
        $this->transport->send(implode("\n", $lines), event: 'datastar-patch-elements');
        return $this;
    }

    /**
     * Removes elements matching the selector from the DOM. #AI:remove
     *
     * @param string $selector CSS selector of elements to remove.
     */
    public function remove(string $selector): static {
        $this->transport->send("selector {$selector}\nmode remove", event: 'datastar-patch-elements');
        return $this;
    }

    /**
     * Merges key-value pairs into Datastar reactive signals. #AI:signals
     *
     * @param array $signals         Key-value pairs to merge.
     * @param bool  $onlyIfMissing When true, only sets signals that do not already exist.
     */
    public function signals(array $signals, bool $onlyIfMissing = false): static {
        $json = (string) json_encode($signals, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $payload = "signals {$json}";
        if ($onlyIfMissing) {
            $payload .= "\nonlyIfMissing true";
        }
        $this->transport->send($payload, event: 'datastar-patch-signals');
        return $this;
    }

    /**
     * Runs JavaScript in the browser by appending a <script> element. #AI:run
     *
     * WARNING: Use only for actions impossible via signal/patch
     * (e.g. scroll-to-top). Never pass user input.
     *
     * @param string $js JavaScript code to evaluate.
     */
    public function run(string $js): static {
        $lines = ['selector body', 'mode append'];
        foreach (explode("\n", "<script>{$js}</script>") as $line) {
            $lines[] = "elements {$line}";
        }
        $this->transport->send(implode("\n", $lines), event: 'datastar-patch-elements');
        return $this;
    }

    /**
     * Renders a view fragment and patches it into the DOM in one call. #AI:viewFragment
     *
     * @param string $template Template path relative to views directory.
     * @param array  $data     Variables passed to the template.
     * @param string $fragment Fragment name within the template.
     * @param string $selector CSS selector for patch target (empty = fragment default).
     */
    public function viewFragment(string $template, array $data, string $fragment, string $selector = ''): static {
        $html = \Skim\View\View::renderFragment($template, $data, $fragment);
        $this->patch($html, $selector);
        return $this;
    }
}

#AI:class
#AI symbol: Skim\Realtime\Datastar
#AI source_path: src/Realtime/Datastar.php
#AI title: datastar
#AI description: Datastar v1 SSE driver implementing ElementPatcher, SignalPatcher, and ScriptRunner.
#AI role: Datastar v1 driver
#AI layer: realtime
#AI badges: [datastar; v1; realtime; dom-patching; signals]
#AI intro: `datastar` is the official Datastar v1 driver for SKIM. It emits `datastar-patch-elements` and `datastar-patch-signals` events over an injected SSE transport, enabling server-driven UI updates without hand-written JavaScript state.
#AI lifecycle: created per-stream by Response::stream() or container resolution; injected with an sse transport
#AI test_seam: capture output with ob_start()/ob_get_clean() in tests
#AI invariants: [Implements ElementPatcher, SignalPatcher, ScriptRunner; Uses injected sse transport; Emits v1 event names only]
#AI core_behaviors: [patch() sends datastar-patch-elements; remove() sends datastar-patch-elements with mode remove; signals() sends datastar-patch-signals; run() appends a script element to body; viewFragment() renders and patches in one call]
#AI owns: none — delegates to injected sse transport
#AI entry_points: [patch; remove; signals; run; viewFragment]
#AI config_reads: []
#AI non_goals: [Does not manage Datastar client setup; Does not handle WebSocket directly; Does not validate HTML fragments]
#AI side_effects: [Writes to output buffer via injected sse transport]
#AI flow: controller -> res->stream(fn($ds) => $ds->patch(...)) -> Sse::send() -> echo SSE format -> flush()
#AI section_order: [DOM Operations; Signal Operations; Script Execution; View Integration]
#AI warnings: [run() executes arbitrary JavaScript in the browser — use sparingly and never with user-supplied input]

#AI:patch
#AI group: DOM Operations
#AI frequency: high
#AI signature: public function patch(string $html, string $selector = '', string $mode = 'morph'): static
#AI contract: Sends a datastar-patch-elements event with the given HTML fragment, selector, and mode. Each line of HTML is prefixed with 'data: elements' per the v1 protocol.
#AI param_details: [{name: $html | type: string | required: true | desc: HTML fragment to patch into the DOM.}; {name: $selector | type: string | required: false | desc: CSS selector of target element.}; {name: $mode | type: string | required: false | desc: Patch mode: morph, inner, outer, prepend, append, before, after, replace.}]
#AI side_effects: [Writes SSE event to output buffer via transport]

#AI:remove
#AI group: DOM Operations
#AI frequency: medium
#AI signature: public function remove(string $selector): static
#AI contract: Sends a datastar-patch-elements event with mode remove to delete matching elements.
#AI param_details: [{name: $selector | type: string | required: true | desc: CSS selector of elements to remove.}]
#AI side_effects: [Writes SSE event to output buffer via transport]

#AI:signals
#AI group: Signal Operations
#AI frequency: high
#AI signature: public function signals(array $signals, bool $onlyIfMissing = false): static
#AI contract: Sends a datastar-patch-signals event merging the given key-value pairs into reactive signals.
#AI param_details: [{name: $signals | type: array | required: true | desc: Key-value pairs to merge.}; {name: $onlyIfMissing | type: bool | required: false | desc: Only set signals that do not already exist.}]
#AI side_effects: [Writes SSE event to output buffer via transport]

#AI:run
#AI group: Script Execution
#AI frequency: low
#AI signature: public function run(string $js): static
#AI contract: Appends a <script> element to the body via datastar-patch-elements. The browser evaluates it and Datastar removes it.
#AI param_details: [{name: $js | type: string | required: true | desc: JavaScript code to evaluate.}]
#AI warnings: [Executes arbitrary JavaScript — never pass user input]
#AI side_effects: [Writes SSE event to output buffer via transport; Executes JS in browser]

#AI:viewFragment
#AI group: View Integration
#AI frequency: medium
#AI signature: public function viewFragment(string $template, array $data, string $fragment, string $selector = ''): static
#AI contract: Renders a named view fragment and patches it into the DOM in one call.
#AI param_details: [{name: $template | type: string | required: true | desc: Template path.}; {name: $data | type: array | required: true | desc: Template variables.}; {name: $fragment | type: string | required: true | desc: Fragment name.}; {name: $selector | type: string | required: false | desc: CSS selector for patch target.}]
#AI side_effects: [Renders view; Writes SSE event to output buffer via transport]
