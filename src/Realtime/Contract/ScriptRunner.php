<?php declare(strict_types=1);

namespace Skim\Realtime\Contract;

/**
 * Contract for hypermedia drivers that can execute scripts in the browser. #AI:interface
 *
 * This is an optional capability — not every driver supports it.
 * Each call returns $this for fluent chaining.
 *
 * #AI:interface
 */
interface ScriptRunner {
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
#AI symbol: Skim\Realtime\Contract\ScriptRunner
#AI source_path: src/Realtime/Contract/ScriptRunner.php
#AI title: ScriptRunner
#AI role: realtime contract
#AI layer: realtime
#AI badges: [realtime; contract; hypermedia; script]
