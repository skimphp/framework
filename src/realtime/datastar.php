<?php declare(strict_types=1);

namespace skim\realtime;

// Datastar SSE helpers — higher-level wrappers over raw SSE.
// Datastar is a lightweight JS library (3KB) that patches the DOM via SSE events.
// Replaces htmx's full-duplex model with pure SSE push — no JS fetch, no form handling.
//
// Event types (Datastar protocol v0.20+):
//   datastar-merge-fragments  — merge HTML fragment into DOM
//   datastar-remove-fragments — remove elements by CSS selector
//   datastar-merge-signals    — update reactive signals (JSON key-value)
//   datastar-execute-script   — evaluate JS in browser (use sparingly)
class datastar extends sse {
    /**
     * @ai-contract sends HTML fragment for Datastar to merge into the DOM
     * @ai-contract $selector: CSS selector of target element (e.g. '#user-card')
     * @ai-contract $mode: 'morph' (default) | 'inner' | 'outer' | 'prepend' | 'append'
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
     * @ai-contract tells Datastar to remove matching elements from the DOM
     * @ai-contract $selector: CSS selector string e.g. '#notification-1'
     */
    public function remove(string $selector): void {
        $this->raw_event('datastar-remove-fragments', "selector {$selector}\n");
    }

    /**
     * @ai-contract merges key-value pairs into Datastar reactive signals
     * @ai-contract triggers any computed or watcher that depends on the changed signal
     * @ai-contract $signals: ['count' => 5, 'loading' => false]
     */
    public function signal(array $signals): void {
        $json = (string) json_encode($signals, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $this->raw_event('datastar-merge-signals', "data {$json}\n");
    }

    /**
     * @ai-contract executes arbitrary JavaScript in the browser
     * @ai-contract use only for actions impossible via signal/merge (e.g. scroll-to-top)
     * @ai-contract avoid for business logic — keep JS minimal
     */
    public function script(string $js): void {
        foreach (explode("\n", trim($js)) as $line) {
            $this->raw_event('datastar-execute-script', "autoRemove true\ndata {$line}\n");
        }
    }

    /**
     * @ai-contract emits a full rendered view fragment via merge()
     * @ai-contract combines view::render_fragment() + datastar::merge() in one call
     */
    public function view_fragment(string $template, array $data, string $fragment, string $selector = ''): void {
        $html = \skim\view\view::render_fragment($template, $data, $fragment);
        $this->merge($html, $selector);
    }

    // --- internals ---

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
