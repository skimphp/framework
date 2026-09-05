<?php declare(strict_types=1);

namespace Skim\Queue;

/**
 * Long-lived queue worker that polls Redis with BRPOP and executes jobs with retry logic.
 *
 * Use when running `php skim queue:work` to process background jobs.
 * The worker promotes delayed jobs, blocks on BRPOP for up to $sleep seconds,
 * unserializes and executes each job, and handles retries with exponential
 * back-off. Supports graceful shutdown via SIGTERM/SIGINT and Redis restart signals.
 *
 * Example:
 *   $worker = new Worker(queue: 'default', sleep: 3, max_jobs: 0);
 *   $worker->work(); // blocks until stopped
 *
 * Testing: Instantiate with mock Queue::setRedis(), call work() with max_jobs=1.
 *
 * #AI:class
 */
final class Worker {
    private bool $shouldStop = false;

    /**
     * @param string $queue    Queue name to poll.
     * @param int    $sleep    Seconds to block on BRPOP when queue is empty.
     * @param int    $maxJobs Maximum jobs to process before stopping (0 = unlimited).
     */
    public function __construct(
        private readonly string $queue   = 'default',
        private readonly int    $sleep   = 3,
        private readonly int    $maxJobs = 0,
    ) {}

    /**
     * Runs the main work loop — blocks until stop(), signal, or max_jobs reached. #AI:work
     *
     * Each iteration: promotes delayed jobs, checks restart signal, BRPOPs for
     * a job, and processes it. Registers SIGTERM/SIGINT handlers for graceful
     * shutdown when pcntl is available.
     */
    public function work(): void {
        $this->registerSignals();
        $processed = 0;

        while (!$this->shouldStop) {
            \Skim\Queue\Queue::promoteDelayed();
            $this->checkRestartSignal();

            $raw = \Skim\Queue\Queue::redis()->brpop('skim:queue:' . $this->queue, $this->sleep);

            if ($raw === null || $raw === false) {
                continue;
            }

            $payload = $raw[1] ?? null;
            if ($payload === null) {
                continue;
            }

            $this->process($payload);
            $processed++;

            if ($this->maxJobs > 0 && $processed >= $this->maxJobs) {
                break;
            }
        }
    }

    /**
     * Signals the worker to stop after the current job completes. #AI:stop
     */
    public function stop(): void {
        $this->shouldStop = true;
    }

    /**
     * Processes a raw Redis payload: unserializes job, runs handle(), manages retries. #AI:process
     *
     * On exception: increments attempts, re-queues with exponential back-off
     * (5s, 10s, 15s...) if retries remain, or calls failed() on exhaustion.
     *
     * @param string $raw JSON payload from Redis BRPOP.
     */
    private function process(string $raw): void {
        $data     = \Skim\Queue\Queue::deserialize($raw);
        $class    = $data['class'] ?? '';
        $attempts = (int) ($data['attempts'] ?? 0) + 1;
        $tries    = (int) ($data['tries'] ?? 1);

        /** @var \Skim\Queue\Job|null $job */
        $job = @unserialize($data['payload'] ?? '');

        if (!$job instanceof \Skim\Queue\Job) {
            error_log("[worker] Failed to unserialize job: {$class}");
            return;
        }

        try {
            $job->handle();
        } catch (\Throwable $e) {
            error_log(sprintf("[worker] Job %s attempt %d/%d failed: %s", $class, $attempts, $tries, $e->getMessage()));

            if ($attempts < $tries) {
                $backOff = $attempts * 5;
                $retryPayload = (string) json_encode(array_merge(
                    $data,
                    ['attempts' => $attempts],
                ));
                \Skim\Queue\Queue::redis()->zadd('skim:queue:delayed', time() + $backOff, $retryPayload);
            } else {
                try {
                    $job->failed($e);
                } catch (\Throwable $inner) {
                    error_log("[worker] failed() itself threw: " . $inner->getMessage());
                }
            }
        }
    }

    /**
     * Registers SIGTERM/SIGINT handlers for graceful shutdown. #AI:registerSignals
     *
     * Requires pcntl extension. No-ops when pcntl is not available.
     */
    private function registerSignals(): void {
        if (!extension_loaded('pcntl')) {
            return;
        }
        pcntl_async_signals(true);
        $stop = function(): void { $this->stop(); };
        pcntl_signal(\SIGTERM, $stop);
        pcntl_signal(\SIGINT,  $stop);
    }

    /**
     * Checks the Redis restart signal timestamp and stops if newer than process start. #AI:checkRestartSignal
     */
    private function checkRestartSignal(): void {
        $restartAt = (int) \Skim\Queue\Queue::redis()->get('skim:queue:restart');
        if ($restartAt > 0 && $restartAt > (int) $_SERVER['REQUEST_TIME_FLOAT']) {
            $this->stop();
        }
    }
}

#AI:class
#AI symbol: Skim\Queue\Worker
#AI source_path: src/Queue/Worker.php
#AI title: worker
#AI description: Long-lived queue worker that polls Redis with BRPOP, executes jobs, and handles retries with exponential back-off.
#AI role: queue worker process
#AI layer: queue
#AI badges: [queue; worker; long-lived; retry; signal-handling]
#AI intro: `worker` is the long-lived CLI process that polls Redis for queued jobs using BRPOP. It promotes delayed jobs, unserializes and executes each job, handles retries with exponential back-off (5s, 10s, 15s...), and supports graceful shutdown via SIGTERM/SIGINT and Redis restart signals.
#AI lifecycle: instantiated by QueueCommand, work() blocks until stopped by signal, maxJobs, or restart signal
#AI fallback: unserializable jobs are logged and skipped; failed() exceptions are caught and logged
#AI test_seam: use Queue::setRedis() for mock Redis, set maxJobs=1 for single-iteration testing
#AI invariants: [BRPOP blocks for $sleep seconds when queue is empty; retries use exponential back-off; restart signal checked every iteration; pcntl signals registered when available]
#AI core_behaviors: [Promotes delayed jobs each iteration; BRPOP blocks efficiently without spinning; Retries with exponential back-off (attempts * 5 seconds); Calls failed() on retry exhaustion; Graceful shutdown via SIGTERM/SIGINT; Redis restart signal checked each loop]
#AI owns: shouldStop flag, processed job count
#AI entry_points: [work; stop]
#AI config_reads: []
#AI non_goals: [Does not manage multiple queues simultaneously; Does not implement job priorities; Does not hot-reload code after deployments]
#AI side_effects: [executes job handle() methods; writes retry payloads to Redis delayed sorted set; reads restart signal from Redis]
#AI flow: Worker::work() -> loop: promoteDelayed() -> checkRestartSignal() -> BRPOP -> process() -> handle() or retry/failed()
#AI lifecycle_steps: [work(); -> registerSignals(); -> loop: promoteDelayed(); -> checkRestartSignal(); -> BRPOP(sleep); -> process(payload); -> unserialize job; -> handle(); -> on exception: retry with back-off or failed(); -> increment processed; -> check maxJobs]
#AI section_order: [Worker Execution; Job Processing; Signal Handling; Architecture]
#AI architectural_notes: The worker does not hot-reload code. After deployments, use `php skim queue:restart` to signal workers to stop and restart with fresh code. Exponential back-off prevents thundering herd on transient failures.

#AI:work
#AI group: Worker Execution
#AI frequency: high
#AI signature: public function work(): void
#AI contract: Runs the main work loop. Blocks until stop() is called, a process signal is received, or maxJobs is reached.

#AI:stop
#AI group: Worker Execution
#AI frequency: low
#AI signature: public function stop(): void
#AI contract: Signals the worker to stop after the current job completes. Called by signal handlers and restart signal check.

#AI:process
#AI group: Job Processing
#AI frequency: internal
#AI signature: private function process(string $raw): void
#AI contract: Unserializes a job from raw Redis payload, executes handle(), and manages retries with exponential back-off on failure.
#AI param_details: [{name: $raw | type: string | required: true | desc: JSON payload from Redis BRPOP.}]

#AI:registerSignals
#AI group: Signal Handling
#AI frequency: internal
#AI signature: private function registerSignals(): void
#AI contract: Registers SIGTERM and SIGINT handlers for graceful shutdown. No-ops when pcntl extension is not available.

#AI:checkRestartSignal
#AI group: Signal Handling
#AI frequency: internal
#AI signature: private function checkRestartSignal(): void
#AI contract: Checks the Redis restart signal timestamp. Stops the worker if the signal is newer than the process start time.
