<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Command;
use Skim\Queue\Queue;
use Skim\Queue\Worker;

/**
 * CLI dispatcher for queue operations — work, status, flush, and restart.
 *
 * Use when managing the background job queue from the terminal.
 * The work sub-command starts a long-lived worker process that polls Redis.
 * Restart signals all running workers to stop after their current job.
 *
 * Example:
 *   php skim queue:work [queue] [--sleep=3] [--max-jobs=0]
 *   php skim queue:status
 *   php skim queue:flush [queue]
 *   php skim queue:restart
 *
 * Testing: Instantiate directly, call setInput(), then handle().
 *
 * #AI:class
 */
class QueueCommand extends \Skim\Cli\Command {
    /**
     * Dispatches to work, status, flush, or restart sub-commands. #AI:handle
     */
    public function handle(): int {
        $sub = $this->arg(0, 'work');

        return match ($sub) {
            'work'    => $this->work(),
            'status'  => $this->status(),
            'flush'   => $this->flush(),
            'restart' => $this->restart(),
            default   => (fn() => ($this->error("Unknown sub-command: {$sub}") ?: 1))(),
        };
    }

    /**
     * Starts a long-lived queue worker that blocks on Redis BRPOP. #AI:work
     *
     * The worker processes jobs until Ctrl+C (SIGTERM/SIGINT), --max-jobs
     * is reached, or a restart signal is received.
     */
    private function work(): int {
        $queueName = $this->arg(1, 'default');
        $sleep      = (int) $this->flag('sleep', 3);
        $maxJobs   = (int) $this->flag('max-jobs', 0);

        $this->info("Starting worker on queue '{$queueName}' (sleep={$sleep}s)");
        $this->muted('Press Ctrl+C to stop gracefully.');

        $w = new \Skim\Queue\Worker(queue: $queueName, sleep: $sleep, maxJobs: $maxJobs);
        $w->work();

        $this->success('Worker stopped.');
        return 0;
    }

    /**
     * Displays pending job count per queue in a table. #AI:status
     */
    private function status(): int {
        $queues = ['default'];
        $rows   = [];
        foreach ($queues as $q) {
            $rows[] = ['queue' => $q, 'pending' => \Skim\Queue\Queue::size($q)];
        }
        \Skim\Cli\Cli::table(['queue', 'pending'], $rows);
        return 0;
    }

    /**
     * Removes all pending jobs from the specified queue. #AI:flush
     *
     * WARNING: Destroys all pending jobs in the queue. Already-processing
     * jobs are not affected.
     *
     * @return int Exit code.
     */
    private function flush(): int {
        $q = $this->arg(1, 'default');
        \Skim\Queue\Queue::flush($q);
        $this->success("Queue '{$q}' flushed.");
        return 0;
    }

    /**
     * Sends a restart signal to all running workers via Redis. #AI:restart
     *
     * Workers check the restart timestamp each iteration and stop gracefully
     * after completing their current job.
     */
    private function restart(): int {
        \Skim\Queue\Queue::redis()->set('skim:queue:restart', time());
        $this->success('Restart signal sent — workers will stop after current job.');
        return 0;
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\QueueCommand
#AI source_path: src/Cli/Commands/QueueCommand.php
#AI title: QueueCommand
#AI description: CLI dispatcher for queue operations — work, status, flush, and restart.
#AI role: CLI queue manager
#AI layer: cli
#AI badges: [cli; command; queue; worker; destructive]
#AI intro: `QueueCommand` implements the `php skim queue:*` family of CLI commands. It dispatches to work (start worker), status (show pending counts), flush (remove pending jobs), and restart (signal workers to stop) sub-commands.
#AI lifecycle: instantiated by kernel, handle() called once per invocation; work sub-command blocks until worker stops
#AI fallback: unknown sub-commands print an error and return exit code 1
#AI test_seam: instantiate directly, call setInput() with test args, then handle(); use Queue::setRedis() for mock Redis
#AI invariants: [work blocks until worker stops; flush destroys pending jobs; restart sets a Redis timestamp checked by workers]
#AI core_behaviors: [work starts a long-lived worker with BRPOP polling; status reads queue sizes from Redis; flush deletes queue keys; restart writes a timestamp to Redis]
#AI warnings: [flush destroys all pending jobs in the specified queue; work blocks the terminal until stopped]
#AI owns: nothing — delegates to queue and worker classes
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not manage job priorities; Does not implement dead-letter queues; Does not handle job scheduling]
#AI side_effects: [work starts a blocking worker process; flush deletes Redis queue keys; restart writes Redis restart timestamp]
#AI flow: QueueCommand::handle() -> arg(0) sub-command -> match: work/status/flush/restart -> queue/worker methods
#AI lifecycle_steps: [handle(); -> arg(0) sub-command; -> match: work -> new Worker() -> worker->work(); status -> Queue::size(); flush -> Queue::flush(); restart -> Queue::redis()->set(restart key)]
#AI section_order: [Command Execution; Sub-commands]
#AI architectural_notes: Thin CLI wrapper over queue and worker classes. The work sub-command is the only blocking operation — all others return immediately.

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Dispatches to work, status, flush, or restart sub-commands. Defaults to work.
#AI return_detail: {type: int | desc: 0 on success, 1 on unknown sub-command.}

#AI:work
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function work(): int
#AI contract: Starts a long-lived queue worker that blocks on Redis BRPOP. Processes jobs until stopped by signal, max-jobs, or restart signal.

#AI:status
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function status(): int
#AI contract: Displays pending job count per queue in an ASCII table.

#AI:flush
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function flush(): int
#AI contract: Removes all pending jobs from the specified queue.
#AI warnings: [Destroys all pending jobs in the queue — already-processing jobs are not affected]

#AI:restart
#AI group: Sub-commands
#AI frequency: low
#AI signature: private function restart(): int
#AI contract: Sends a restart signal to all running workers via a Redis timestamp key. Workers stop after completing their current job.
