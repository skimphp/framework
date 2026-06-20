<?php declare(strict_types=1);

namespace skim\realtime\contract;

/**
 * Contract for hypermedia drivers that patch DOM elements. #AI:interface
 *
 * Drivers implementing this can replace, remove, or insert HTML fragments
 * into the browser. Each call returns $this for fluent chaining.
 *
 * #AI:interface
 */
interface element_patcher {
    /**
     * Patches the DOM with an HTML fragment at the given selector. #AI:patch
     *
     * @param string $html     HTML fragment to insert / replace.
     * @param string $selector CSS selector of the target element (e.g. '#content').
     * @param string $mode     Merge mode: 'morph', 'inner', 'outer', 'prepend', 'append'.
     */
    public function patch(string $html, string $selector = '', string $mode = 'morph'): static;

    /**
     * Removes elements matching the selector from the DOM. #AI:remove
     *
     * @param string $selector CSS selector of elements to remove.
     */
    public function remove(string $selector): static;
}

#AI:interface
#AI symbol: skim\realtime\contract\element_patcher
#AI source_path: src/realtime/contract/element_patcher.php
#AI title: element_patcher
#AI description: Interface for hypermedia drivers that patch DOM elements.
#AI role: realtime contract
#AI layer: realtime
#AI badges: [realtime; contract; hypermedia; dom]
