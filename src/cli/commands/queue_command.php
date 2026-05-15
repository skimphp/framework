<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\command;
use skim\queue\queue;
use skim\queue\worker;

// Usage:
//   php skim queue:work [queue] [--sleep=3] [--max-jobs=0]
//   php skim queue:status
//   php skim queue:flush [queue]
//   php skim queue:restart
class queue_command extends command {
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

    private function work(): int {
        $queue_name = $this->arg(1, 'default');
        $sleep      = (int) $this->flag('sleep', 3);
        $max_jobs   = (int) $this->flag('max-jobs', 0);

        $this->info("Starting worker on queue '{$queue_name}' (sleep={$sleep}s)");
        $this->muted('Press Ctrl+C to stop gracefully.');

        $w = new worker(queue: $queue_name, sleep: $sleep, max_jobs: $max_jobs);
        $w->work();

        $this->success('Worker stopped.');
        return 0;
    }

    private function status(): int {
        $queues = ['default'];
        $rows   = [];
        foreach ($queues as $q) {
            $rows[] = ['queue' => $q, 'pending' => queue::size($q)];
        }
        \skim\cli\cli::table(['queue', 'pending'], $rows);
        return 0;
    }

    private function flush(): int {
        $q = $this->arg(1, 'default');
        queue::flush($q);
        $this->success("Queue '{$q}' flushed.");
        return 0;
    }

    private function restart(): int {
        queue::redis()->set('skim:queue:restart', time());
        $this->success('Restart signal sent — workers will stop after current job.');
        return 0;
    }
}
