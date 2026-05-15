<?php declare(strict_types=1);

namespace skim\queue;

// Redis-backed job queue.
// Jobs go into a Redis list (LPUSH). Workers poll with BRPOP (blocking pop).
// Delayed jobs use a sorted set keyed by execute_at timestamp; a scheduler
// moves them to the main list when they're due.
//
// Why Redis list + BRPOP: O(1) enqueue/dequeue, no polling interval latency,
// BRPOP blocks efficiently without spinning — much better than a cron-based approach.
final class queue {
    private static string  $queue_key    = 'skim:queue:default';
    private static string  $delayed_key  = 'skim:queue:delayed';
    private static ?\Redis $redis        = null;

    /**
     * @ai-contract pushes a job to the queue — LPUSH into Redis list
     * @ai-contract $queue overrides the default queue name (multi-queue support)
     * @ai-contract delayed jobs go into sorted set, not the main list
     */
    public static function push(job $job, string $queue = 'default'): void {
        $payload = self::serialize_job($job, $queue);
        $delay   = $job->delay();

        if ($delay > 0) {
            $execute_at = time() + $delay;
            self::redis()->zadd(self::$delayed_key, $execute_at, $payload);
        } else {
            self::redis()->lpush('skim:queue:' . $queue, $payload);
        }
    }

    /**
     * @ai-contract pushes multiple jobs atomically via pipeline
     * @ai-contract all jobs use the same $queue
     */
    public static function push_many(array $jobs, string $queue = 'default'): void {
        $pipe = self::redis()->pipeline();
        foreach ($jobs as $job) {
            $payload = self::serialize_job($job, $queue);
            $pipe->lpush('skim:queue:' . $queue, $payload);
        }
        $pipe->execute();
    }

    /**
     * @ai-contract moves due delayed jobs to their target queue (called by worker on each loop)
     * @ai-contract returns number of jobs promoted
     */
    public static function promote_delayed(): int {
        $now   = time();
        $jobs  = self::redis()->zrangebyscore(self::$delayed_key, '-inf', (string) $now);
        $count = 0;

        foreach ($jobs as $payload) {
            $data  = json_decode($payload, true);
            $queue = $data['queue'] ?? 'default';
            self::redis()->lpush('skim:queue:' . $queue, $payload);
            self::redis()->zrem(self::$delayed_key, $payload);
            $count++;
        }

        return $count;
    }

    /**
     * @ai-contract returns approximate count of pending jobs in a queue
     */
    public static function size(string $queue = 'default'): int {
        return (int) self::redis()->llen('skim:queue:' . $queue);
    }

    /**
     * @ai-contract for tests — inject a mock Redis instance
     */
    public static function set_redis(\Redis $redis): void {
        self::$redis = $redis;
    }

    /**
     * @ai-contract for tests — flush all jobs from a queue
     */
    public static function flush(string $queue = 'default'): void {
        self::redis()->del('skim:queue:' . $queue);
    }

    // --- internals ---

    public static function serialize_job(job $job, string $queue): string {
        return (string) json_encode([
            'class'    => get_class($job),
            'payload'  => serialize($job),
            'queue'    => $queue,
            'tries'    => $job->tries(),
            'attempts' => 0,
            'pushed_at' => time(),
        ]);
    }

    public static function deserialize(string $raw): array {
        return (array) json_decode($raw, true);
    }

    public static function redis(): \Redis {
        if (self::$redis !== null) {
            return self::$redis;
        }
        $cfg = \skim\core\config::get('cache.redis', []);
        $r   = new \Redis();
        $r->connect(
            $cfg['host'] ?? '127.0.0.1',
            $cfg['port'] ?? 6379,
            1.0,
        );
        if (!empty($cfg['password'])) {
            $r->auth($cfg['password']);
        }
        return self::$redis = $r;
    }
}
