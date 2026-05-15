<?php declare(strict_types=1);

namespace skim\queue;

// Every background job implements this interface.
// The job class must be serializable (no closures, no resource handles).
// Reason: jobs are stored as JSON in Redis — all properties must be JSON-encodable.
interface job {
    /**
     * @ai-contract contains the actual work — runs in worker process, not request process
     * @ai-contract must not echo or write response — call log::info() for output
     */
    public function handle(): void;

    /**
     * @ai-contract called when all retry attempts are exhausted
     * @ai-contract use to notify admin, move to dead-letter queue, or clean up
     */
    public function failed(\Throwable $e): void;

    /**
     * @ai-contract returns how many times the worker should retry on failure before calling failed()
     * @ai-contract return 0 for no retries — failed() called on first failure
     */
    public function tries(): int;

    /**
     * @ai-contract returns delay in seconds before first attempt
     * @ai-contract return 0 for immediate execution
     */
    public function delay(): int;
}
