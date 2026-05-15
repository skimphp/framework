<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\command;
use skim\db\migrator;

// Delegates to migrator — no SQL logic here.
// Usage:
//   php skim migrate           → run pending
//   php skim migrate:down      → rollback last batch
//   php skim migrate:fresh     → drop all + re-run (dev only)
//   php skim migrate:status    → show pending/applied list
class migrate_command extends command {
    public function handle(): int {
        $sub  = $this->arg(0, 'run');
        $mig  = new migrator(base_path('migrations'));

        return match ($sub) {
            'down'   => $this->down($mig),
            'fresh'  => $this->fresh($mig),
            'status' => $this->status($mig),
            default  => $this->run($mig),
        };
    }

    private function run(migrator $mig): int {
        $ran = $mig->run();
        if ($ran === []) {
            $this->info('Nothing to migrate.');
            return 0;
        }
        foreach ($ran as $file) {
            $this->success("Migrated: {$file}");
        }
        return 0;
    }

    private function down(migrator $mig): int {
        $steps = (int) $this->flag('steps', 0);
        $rolled = $mig->down($steps);
        if ($rolled === []) {
            $this->info('Nothing to roll back.');
            return 0;
        }
        foreach ($rolled as $file) {
            $this->warn("Rolled back: {$file}");
        }
        return 0;
    }

    private function fresh(migrator $mig): int {
        $this->warn('Running migrate:fresh — all data will be DESTROYED.');
        $mig->fresh();
        $this->success('Fresh migration complete.');
        return 0;
    }

    private function status(migrator $mig): int {
        $rows = $mig->status();
        $this->line();
        \skim\cli\cli::table(
            ['filename', 'batch', 'status'],
            array_map(fn($r) => [
                'filename' => $r['filename'],
                'batch'    => $r['batch'] ?? '-',
                'status'   => $r['status'],
            ], $rows),
        );
        return 0;
    }
}
