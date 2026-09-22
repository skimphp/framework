<?php declare(strict_types=1);

namespace Skim\Queue;

/**
 * Redis-backed job queue using LPUSH/BRPOP for O(1) enqueue/dequeue and sorted sets for delays.
 *
 * Use when dispatching background jobs from request handlers or other workers.
 * Jobs are serialized and stored in Redis lists (LPUSH). Workers poll with
 * BRPOP for blocking, latency-free dequeue. Delayed jobs use a sorted set
 * keyed by execute_at timestamp and are promoted to the main list when due.
 *
 * Example:
 *   Queue::push(new SendEmailJob($user_id));
 *   Queue::push(new GenerateReportJob($id), queue: 'reports');
 *   Queue::pushMany([$job1, $job2], 'default');
 *
 * Testing: Use setRedis() to inject a mock Redis instance, flush() to clear queues.
 *
 * #AI:class
 */
final class Queue {
    private static string  $queueKey    = 'skim:queue:default';
    private static string  $delayedKey  = 'skim:queue:delayed';
    private static ?\Redis $redis        = null;

    /**
     * Pushes a job to the queue — LPUSH into Redis list or sorted set for delayed. #AI:push
     *
     * Jobs with delay() > 0 go into a sorted set keyed by execute_at timestamp.
     * The worker's promoteDelayed() moves them to the main list when due.
     *
     * @param \Skim\Queue\Job $job Job instance to enqueue. Must be serializable.
     * @param string $queue Queue name for multi-queue support (default: 'default').
     */
    public static function push(\Skim\Queue\Job $job, string $queue = 'default'): void {
        $payload = self::serializeJob($job, $queue);
        $delay   = $job->delay();

        if ($delay > 0) {
            $executeAt = time() + $delay;
            self::redis()->zadd(self::$delayedKey, $executeAt, $payload);
        } else {
            self::redis()->lpush('skim:queue:' . $queue, $payload);
        }
    }

    /**
     * Pushes multiple jobs atomically via Redis pipeline. #AI:pushMany
     *
     * All jobs use the same queue name. Delayed jobs in the batch are not
     * supported — use push() individually for delayed jobs.
     *
     * @param array  $jobs  Array of job instances.
     * @param string $queue Queue name for all jobs.
     */
    public static function pushMany(array $jobs, string $queue = 'default'): void {
        $pipe = self::redis()->pipeline();
        foreach ($jobs as $job) {
            $payload = self::serializeJob($job, $queue);
            $pipe->lpush('skim:queue:' . $queue, $payload);
        }
        $pipe->exec();
    }

    /**
     * Moves due delayed jobs from sorted set to their target queue. #AI:promoteDelayed
     *
     * Called by the worker on each loop iteration. Scans the delayed sorted set
     * for jobs whose execute_at timestamp has passed and LPUSHes them to the
     * appropriate queue.
     *
     * @return int Number of jobs promoted.
     */
    public static function promoteDelayed(): int {
        $now   = time();
        $jobs  = self::redis()->zrangebyscore(self::$delayedKey, '-inf', (string) $now);
        $count = 0;

        foreach ($jobs as $payload) {
            $data  = json_decode($payload, true);
            $queue = $data['queue'] ?? 'default';
            self::redis()->lpush('skim:queue:' . $queue, $payload);
            self::redis()->zrem(self::$delayedKey, $payload);
            $count++;
        }

        return $count;
    }

    /**
     * Returns the approximate count of pending jobs in a queue. #AI:size
     *
     * @param string $queue Queue name.
     */
    public static function size(string $queue = 'default'): int {
        return (int) self::redis()->llen('skim:queue:' . $queue);
    }

    /**
     * Injects a mock Redis instance for testing. #AI:setRedis
     *
     * Use in PHPUnit to bypass the real Redis connection. Call redis() reset
     * in tearDown() to restore normal behavior.
     *
     * Example:
     *   Queue::setRedis($mock_redis);
     *   // ... run tests ...
     *   // reset in tearDown
     *
     * @param \Redis $redis Mock or fake Redis instance.
     */
    public static function setRedis(\Redis $redis): void {
        self::$redis = $redis;
    }

    /**
     * Removes all pending jobs from a queue. #AI:flush
     *
     * WARNING: Destroys all pending jobs. Already-processing jobs are not affected.
     *
     * @param string $queue Queue name to flush.
     */
    public static function flush(string $queue = 'default'): void {
        self::redis()->del('skim:queue:' . $queue);
    }

    /**
     * Serializes a job into a JSON payload for Redis storage. #AI:serializeJob
     *
     * @param \Skim\Queue\Job $job Job instance to serialize.
     * @param string $queue Queue name embedded in the payload.
     */
    public static function serializeJob(\Skim\Queue\Job $job, string $queue): string {
        return (string) json_encode([
            'class'    => get_class($job),
            'payload'  => serialize($job),
            'queue'    => $queue,
            'tries'    => $job->tries(),
            'attempts' => 0,
            'pushed_at' => time(),
        ]);
    }

    /**
     * Deserializes a raw Redis payload into a job data array. #AI:deserialize
     *
     * @param string $raw JSON string from Redis.
     */
    public static function deserialize(string $raw): array {
        return (array) json_decode($raw, true);
    }

    /**
     * Returns the cached Redis connection, creating it from config on first use. #AI:redis
     *
     * Connects lazily using cache.redis config. Supports password authentication.
     */
    public static function redis(): \Redis {
        if (self::$redis !== null) {
            return self::$redis;
        }
        $cfg = \Skim\Core\Config::get('cache.redis', []);
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

#AI:class
#AI symbol: Skim\Queue\Queue
#AI source_path: src/Queue/Queue.php
#AI title: queue
#AI description: Redis-backed job queue using LPUSH/BRPOP for O(1) operations and sorted sets for delayed jobs.
#AI role: Redis job queue
#AI layer: queue
#AI badges: [queue; redis; static-facade; job-dispatch]
#AI intro: `queue` is the static facade for the Redis-backed job queue. Jobs are serialized and LPUSHed into Redis lists. Workers dequeue with BRPOP for blocking, latency-free operation. Delayed jobs use a sorted set keyed by executeAt timestamp and are promoted to the main list by the worker.
#AI lifecycle: static facade, Redis connection resolved lazily on first use from config
#AI fallback: none — Redis connection failure throws
#AI test_seam: setRedis() to inject mock Redis, flush() to clear queues
#AI invariants: [jobs are serialized via PHP serialize() wrapped in JSON; delayed jobs use sorted set; promoteDelayed() moves due jobs to main list]
#AI core_behaviors: [push() LPUSHes to Redis list or ZADDs to delayed sorted set; pushMany() uses pipeline for atomic batch; promoteDelayed() scans sorted set for due jobs; size() returns LLEN count; flush() DELetes queue key]
#AI owns: Redis connection cache
#AI entry_points: [push; pushMany; promoteDelayed; size; flush]
#AI config_reads: [cache.redis.host; cache.redis.port; cache.redis.password]
#AI non_goals: [Does not implement job execution (see worker); Does not manage retry logic (see worker); Does not provide dead-letter queues]
#AI side_effects: [writes to Redis lists and sorted sets; setRedis() replaces active connection; flush() deletes queue data]
#AI flow: Queue::push(job) -> serializeJob() -> delay > 0 ? ZADD delayed : LPUSH queue -> worker BRPOP -> deserialize -> handle()
#AI lifecycle_steps: [Queue::push(job); -> serializeJob(job, queue); -> job->delay() > 0 ? ZADD delayedKey : LPUSH skim:queue:$queue; -> worker BRPOP; -> deserialize(); -> unserialize payload; -> handle()]
#AI section_order: [Enqueue; Delayed Jobs; Queue Inspection; Testing Hooks; Architecture]
#AI architectural_notes: Static facade sharing the Redis connection with the cache subsystem via config. The connection is lazy — not opened until the first queue operation.

#AI:push
#AI group: Enqueue
#AI frequency: high
#AI signature: public static function push(Job $job, string $queue = 'default'): void
#AI contract: Pushes a job to the queue. Jobs with delay > 0 go into a sorted set; others are LPUSHed to the Redis list.
#AI param_details: [{name: $job | type: job | required: true | desc: Job instance to enqueue. Must be serializable.}; {name: $queue | type: string | required: false | desc: Queue name for multi-queue support.}]
#AI side_effects: Writes to Redis list or sorted set.

#AI:pushMany
#AI group: Enqueue
#AI frequency: medium
#AI signature: public static function pushMany(array $jobs, string $queue = 'default'): void
#AI contract: Pushes multiple jobs atomically via Redis pipeline. All jobs use the same queue. Does not support delayed jobs.
#AI param_details: [{name: $jobs | type: array | required: true | desc: Array of job instances.}; {name: $queue | type: string | required: false | desc: Queue name for all jobs.}]
#AI side_effects: Writes multiple entries to Redis list via pipeline.

#AI:promoteDelayed
#AI group: Delayed Jobs
#AI frequency: internal
#AI signature: public static function promoteDelayed(): int
#AI contract: Moves due delayed jobs from the sorted set to their target queue. Called by worker on each loop iteration.
#AI return_detail: {type: int | desc: Number of jobs promoted.}
#AI side_effects: Moves entries from sorted set to Redis lists.

#AI:size
#AI group: Queue Inspection
#AI frequency: medium
#AI signature: public static function size(string $queue = 'default'): int
#AI contract: Returns the approximate count of pending jobs in a queue via Redis LLEN.
#AI param_details: [{name: $queue | type: string | required: false | desc: Queue name.}]
#AI return_detail: {type: int | desc: Number of pending jobs.}

#AI:setRedis
#AI group: Testing Hooks
#AI frequency: low
#AI signature: public static function setRedis(\Redis $redis): void
#AI contract: Injects a mock Redis instance for testing. Bypasses config-based connection.
#AI param_details: [{name: $redis | type: \Redis | required: true | desc: Mock or fake Redis instance.}]
#AI side_effects: Replaces static Redis connection.

#AI:flush
#AI group: Queue Inspection
#AI frequency: low
#AI signature: public static function flush(string $queue = 'default'): void
#AI contract: Removes all pending jobs from a queue by deleting the Redis key.
#AI param_details: [{name: $queue | type: string | required: false | desc: Queue name to flush.}]
#AI warnings: [Destroys all pending jobs in the queue — already-processing jobs are not affected]
#AI side_effects: Deletes Redis queue key.

#AI:serializeJob
#AI group: Architecture
#AI frequency: internal
#AI signature: public static function serializeJob(Job $job, string $queue): string
#AI contract: Serializes a job into a JSON payload containing class name, serialized payload, queue, tries, attempts, and timestamp.
#AI param_details: [{name: $job | type: job | required: true | desc: Job instance to serialize.}; {name: $queue | type: string | required: true | desc: Queue name embedded in the payload.}]
#AI return_detail: {type: string | desc: JSON-encoded payload string.}

#AI:deserialize
#AI group: Architecture
#AI frequency: internal
#AI signature: public static function deserialize(string $raw): array
#AI contract: Deserializes a raw Redis JSON payload into a job data array.
#AI param_details: [{name: $raw | type: string | required: true | desc: JSON string from Redis.}]
#AI return_detail: {type: array | desc: Decoded job data array.}

#AI:redis
#AI group: Architecture
#AI frequency: internal
#AI signature: public static function redis(): \Redis
#AI contract: Returns the cached Redis connection, creating it lazily from config/cache.php on first use.
#AI notes: Public because worker and QueueCommand access it directly for restart signals and status.
