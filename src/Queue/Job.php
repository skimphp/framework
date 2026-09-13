<?php declare(strict_types=1);

namespace Skim\Queue;

/**
 * Contract for all background queue jobs — must be serializable for Redis storage.
 *
 * Use when defining a new background job type. The job class must be fully
 * serializable (no closures, no resource handles) since jobs are stored as
 * JSON+serialize in Redis. All constructor properties must be JSON-encodable.
 *
 * Example:
 *   class GenerateReportJob implements Job {
 *       public function __construct(private readonly int $report_id) {}
 *       public function handle(): void { Report::generate($this->report_id); }
 *       public function failed(\Throwable $e): void { Log::error("Report {$this->report_id} failed"); }
 *       public function tries(): int { return 1; }
 *       public function delay(): int { return 0; }
 *   }
 *
 * Testing: Implement in test job classes with mock dependencies.
 *
 * #AI:class
 */
interface Job {
    /**
     * Contains the actual work — runs in worker process, not request process. #AI:handle
     *
     * Must not echo or write HTTP response. Use Log::info() for output.
     * Exceptions are caught by the worker and trigger retry or failed().
     */
    public function handle(): void;

    /**
     * Called when all retry attempts are exhausted. #AI:failed
     *
     * Use to notify admin, move to dead-letter queue, or clean up partial work.
     *
     * @param \Throwable $e The exception from the final failed attempt.
     */
    public function failed(\Throwable $e): void;

    /**
     * Returns how many times the worker should retry before calling failed(). #AI:tries
     *
     * Return 0 for no retries — failed() is called on first failure.
     */
    public function tries(): int;

    /**
     * Returns delay in seconds before the first execution attempt. #AI:delay
     *
     * Return 0 for immediate execution. Delayed jobs go into a Redis sorted set
     * and are promoted to the main queue when their time arrives.
     */
    public function delay(): int;
}

#AI:class
#AI symbol: Skim\Queue\Job
#AI source_path: src/Queue/Job.php
#AI title: job
#AI description: Interface contract for background queue jobs — must be serializable for Redis storage.
#AI role: queue job interface
#AI layer: queue
#AI badges: [queue; job; interface; serializable]
#AI intro: `job` is the interface that all background queue jobs must implement. Job classes must be fully serializable since they are stored as JSON+serialize payloads in Redis. The interface defines handle() for work, failed() for exhaustion, tries() for retry count, and delay() for deferred execution.
#AI lifecycle: instantiated by application code for push(), unserialized by worker for execution
#AI fallback: none — interface defines the contract, implementations decide behavior
#AI test_seam: implement in test job classes with mock dependencies
#AI invariants: [job classes must be serializable; no closures or resource handles in properties; all constructor params must be JSON-encodable]
#AI core_behaviors: [handle() contains the work logic; failed() handles permanent failure; tries() controls retry count; delay() controls deferred execution]
#AI owns: nothing — interface defines contract only
#AI entry_points: [handle; failed; tries; delay]
#AI config_reads: []
#AI non_goals: [Does not define serialization format (see Queue::serializeJob); Does not manage retry back-off (see worker); Does not handle job scheduling]
#AI side_effects: [handle() performs the actual work; failed() may send alerts or clean up]
#AI flow: Queue::push(job) -> serialize -> Redis -> worker unserializes -> handle() -> on failure: retry or failed()
#AI lifecycle_steps: [application creates job instance; -> Queue::push(job); -> Queue::serializeJob(); -> Redis LPUSH/ZADD; -> worker BRPOP; -> unserialize; -> handle(); -> on exception: retry or failed()]
#AI section_order: [Job Execution; Failure Handling; Configuration]
#AI architectural_notes: Interface — not instantiated directly. Implementations must be serializable for Redis storage. Most jobs extend BaseJob for default tries/delay/failed behavior.

#AI:handle
#AI group: Job Execution
#AI frequency: high
#AI signature: public function handle(): void
#AI contract: Contains the actual work. Runs in the worker process, not the request process. Must not echo or write HTTP response.

#AI:failed
#AI group: Failure Handling
#AI frequency: low
#AI signature: public function failed(\Throwable $e): void
#AI contract: Called when all retry attempts are exhausted. Use for notifications, dead-letter, or cleanup.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception from the final failed attempt.}]

#AI:tries
#AI group: Configuration
#AI frequency: low
#AI signature: public function tries(): int
#AI contract: Returns how many times the worker should retry before calling failed(). Return 0 for no retries.
#AI return_detail: {type: int | desc: Maximum retry attempts.}

#AI:delay
#AI group: Configuration
#AI frequency: low
#AI signature: public function delay(): int
#AI contract: Returns delay in seconds before first execution. Return 0 for immediate execution.
#AI return_detail: {type: int | desc: Delay in seconds.}
