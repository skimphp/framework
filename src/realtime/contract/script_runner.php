<?php declare(strict_types=1);

namespace skim\realtime\contract;

/**
 * Contract for hypermedia drivers that can execute scripts in the browser. #AI:interface
 *
 * This is an optional capability — not every driver supports it.
 * Each call returns $this for fluent chaining.
 *
 * #AI:interface
 */
interface script_runner {
    /**
     * Runs JavaScript in the browser context. #AI:run
     *
     * WARNING: Use only for actions impossible via signal/patch
     * (e.g. scroll-to-top). Never pass user input.
     *
     * @param string $js JavaScript code to evaluate.
     */
    public function run(string $js): static;
}

#AI:interface
#AI symbol: skim\realtime\contract\script_runner
#AI source_path: src/realtime/contract/script_runner.php
#AI title: script_runner
#AI role: realtime contract
#AI layer: realtime
#AI badges: [realtime; contract; hypermedia; script]
