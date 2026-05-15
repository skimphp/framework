<?php declare(strict_types=1);

namespace skim\queue;

// Convenience base class — override only the methods you need.
// Most jobs only implement handle() and optionally failed().
abstract class base_job implements job {
    /**
     * @ai-contract override in subclass if this job needs more than 3 retry attempts
     */
    public function tries(): int {
        return 3;
    }

    /**
     * @ai-contract override in subclass to delay execution (e.g. password reset email: 0s)
     */
    public function delay(): int {
        return 0;
    }

    /**
     * @ai-contract default failed() logs the error — override to send alerts or move to dead-letter
     */
    public function failed(\Throwable $e): void {
        // Log only — don't rethrow. Worker catches any exception from failed() anyway.
        error_log(sprintf('[queue] Job %s failed permanently: %s', static::class, $e->getMessage()));
    }
}
