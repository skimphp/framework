<?php declare(strict_types=1);

namespace Skim\Queue;

/**
 * Convenience base class for queue jobs with sensible defaults for tries, delay, and failure handling.
 *
 * Use when creating new Job classes that don't need custom retry or delay logic.
 * Provides default implementations: 3 retries, 0s delay, and error_log on failure.
 * Override only the methods you need — most jobs just implement handle().
 *
 * Example:
 *   class SendEmailJob extends BaseJob {
 *       public function __construct(private readonly int $user_id) {}
 *       public function handle(): void {
 *           $user = User::findOrFail($this->user_id);
 *           Mailer::send($user->email, 'welcome');
 *       }
 *   }
 *
 * Testing: Extend in test job classes, override failed() to capture errors.
 *
 * #AI:class
 */
abstract class BaseJob implements \Skim\Queue\Job {
    /**
     * Returns the default retry count of 3. #AI:tries
     *
     * Override in subclass if this job needs more or fewer retry attempts.
     */
    public function tries(): int {
        return 3;
    }

    /**
     * Returns the default delay of 0 seconds (immediate execution). #AI:delay
     *
     * Override in subclass to delay first attempt (e.g. scheduled notifications).
     */
    public function delay(): int {
        return 0;
    }

    /**
     * Logs the error when all retries are exhausted. #AI:failed
     *
     * Default implementation writes to error_log. Override to send alerts,
     * move to dead-letter queue, or perform cleanup. Does not rethrow —
     * the worker catches any exception from failed() anyway.
     *
     * @param \Throwable $e The exception that caused the final failure.
     */
    public function failed(\Throwable $e): void {
        error_log(sprintf('[queue] Job %s failed permanently: %s', static::class, $e->getMessage()));
    }
}

#AI:class
#AI symbol: Skim\Queue\BaseJob
#AI source_path: src/Queue/BaseJob.php
#AI title: BaseJob
#AI description: Convenience base class for queue jobs with default retry count, delay, and error logging.
#AI role: queue job base class
#AI layer: queue
#AI badges: [queue; job; abstract; base-class; defaults]
#AI intro: `BaseJob` is a convenience abstract class that implements the `job` interface with sensible defaults: 3 retry attempts, 0-second delay, and error_log on permanent failure. Most job classes extend this and override only `handle()`.
#AI lifecycle: instantiated by worker via unserialize, handle() called, failed() called on exhaustion
#AI fallback: failed() logs to error_log by default — override for custom failure handling
#AI test_seam: extend in test job classes, override failed() to capture errors
#AI invariants: [tries() defaults to 3; delay() defaults to 0; failed() does not rethrow]
#AI core_behaviors: [Provides default tries() of 3; Provides default delay() of 0; failed() writes to error_log with job class name and exception message]
#AI owns: nothing — subclasses own their constructor properties
#AI entry_points: [handle; failed; tries; delay]
#AI config_reads: []
#AI non_goals: [Does not implement handle() — subclasses must; Does not provide dead-letter queue; Does not implement retry back-off (handled by worker)]
#AI side_effects: [failed() writes to error_log]
#AI flow: worker unserializes job -> handle() -> on exception: retry or failed()
#AI lifecycle_steps: [worker unserializes BaseJob subclass; -> handle() called; -> on exception: worker retries up to tries() times; -> on exhaustion: failed($e) called]
#AI section_order: [Job Configuration; Failure Handling]
#AI architectural_notes: Abstract class implementing the job interface. Subclasses must implement handle() and may override tries(), delay(), and failed().

#AI:tries
#AI group: Job Configuration
#AI frequency: low
#AI signature: public function tries(): int
#AI contract: Returns the default retry count of 3. Override in subclass for different retry behavior.
#AI return_detail: {type: int | desc: Number of retry attempts before failed() is called.}

#AI:delay
#AI group: Job Configuration
#AI frequency: low
#AI signature: public function delay(): int
#AI contract: Returns the default delay of 0 seconds. Override to delay first execution.
#AI return_detail: {type: int | desc: Delay in seconds before first attempt.}

#AI:failed
#AI group: Failure Handling
#AI frequency: low
#AI signature: public function failed(\Throwable $e): void
#AI contract: Logs the error when all retries are exhausted. Default writes to error_log. Override for alerts or dead-letter handling.
#AI param_details: [{name: $e | type: \Throwable | required: true | desc: The exception that caused the final failure.}]
#AI notes: Does not rethrow — the worker catches any exception from failed() anyway.
