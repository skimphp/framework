<?php declare(strict_types=1);

namespace Skim\Realtime\Contract;

/**
 * Contract for hypermedia drivers that patch reactive signals. #AI:interface
 *
 * Drivers implementing this can push state updates to the client without
 * touching the DOM directly. Each call returns $this for fluent chaining.
 *
 * #AI:interface
 */
interface SignalPatcher {
    /**
     * Merges key-value pairs into the client's reactive signals. #AI:signals
     *
     * @param array $signals         Key-value pairs to merge.
     * @param bool  $onlyIfMissing When true, only sets signals that do not already exist.
     */
    public function signals(array $signals, bool $onlyIfMissing = false): static;
}

#AI:interface
#AI symbol: Skim\Realtime\Contract\SignalPatcher
#AI source_path: src/Realtime/Contract/SignalPatcher.php
#AI title: SignalPatcher
#AI description: Interface for hypermedia drivers that patch reactive signals.
#AI role: realtime contract
#AI layer: realtime
#AI badges: [realtime; contract; hypermedia; signals]
