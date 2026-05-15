<?php declare(strict_types=1);

namespace skim\queue;

// Queue worker — runs as a long-lived CLI process.
// Launched via: php skim queue:work [queue] [--sleep=N]
//
// Loop:
//   1. promote_delayed() — move due delayed jobs to the main list
//   2. BRPOP $queue 2 — block up to 2s waiting for a job
//   3. unserialize job → attempt handle()
//   4. on exception: increment attempts, push back (if tries remain) or call failed()
//
// Restart: the worker does not hot-reload. After deployments, restart with:
//   php skim queue:restart  (sets a Redis key; workers check it each iteration)
final class worker {
    private bool $should_stop = false;

    public function __construct(
        private readonly string $queue   = 'default',
        private readonly int    $sleep   = 3,
        private readonly int    $max_jobs = 0,   // 0 = unlimited
    ) {}

    /**
     * @ai-contract runs the main work loop — blocks until stop() or process signal
     * @ai-contract handles SIGTERM/SIGINT for graceful shutdown (pcntl required)
     */
    public function work(): void {
        $this->register_signals();
        $processed = 0;

        while (!$this->should_stop) {
            queue::promote_delayed();
            $this->check_restart_signal();

            $raw = queue::redis()->brpop('skim:queue:' . $this->queue, $this->sleep);

            if ($raw === null || $raw === false) {
                continue;
            }

            $payload = $raw[1] ?? null;
            if ($payload === null) {
                continue;
            }

            $this->process($payload);
            $processed++;

            if ($this->max_jobs > 0 && $processed >= $this->max_jobs) {
                break;
            }
        }
    }

    /**
     * @ai-contract signals the worker to stop after current job completes
     */
    public function stop(): void {
        $this->should_stop = true;
    }

    // --- internals ---

    private function process(string $raw): void {
        $data     = queue::deserialize($raw);
        $class    = $data['class'] ?? '';
        $attempts = (int) ($data['attempts'] ?? 0) + 1;
        $tries    = (int) ($data['tries'] ?? 1);

        /** @var job|null $job */
        $job = @unserialize($data['payload'] ?? '');

        if (!$job instanceof job) {
            error_log("[worker] Failed to unserialize job: {$class}");
            return;
        }

        try {
            $job->handle();
        } catch (\Throwable $e) {
            error_log(sprintf("[worker] Job %s attempt %d/%d failed: %s", $class, $attempts, $tries, $e->getMessage()));

            if ($attempts < $tries) {
                // Re-queue with incremented attempts and exponential back-off delay
                $back_off = $attempts * 5;   // 5s, 10s, 15s...
                $retry_payload = (string) json_encode(array_merge(
                    $data,
                    ['attempts' => $attempts],
                ));
                queue::redis()->zadd('skim:queue:delayed', time() + $back_off, $retry_payload);
            } else {
                try {
                    $job->failed($e);
                } catch (\Throwable $inner) {
                    error_log("[worker] failed() itself threw: " . $inner->getMessage());
                }
            }
        }
    }

    private function register_signals(): void {
        if (!extension_loaded('pcntl')) {
            return;
        }
        pcntl_async_signals(true);
        $stop = function(): void { $this->stop(); };
        pcntl_signal(\SIGTERM, $stop);
        pcntl_signal(\SIGINT,  $stop);
    }

    private function check_restart_signal(): void {
        $restart_at = (int) queue::redis()->get('skim:queue:restart');
        if ($restart_at > 0 && $restart_at > (int) $_SERVER['REQUEST_TIME_FLOAT']) {
            $this->stop();
        }
    }
}
