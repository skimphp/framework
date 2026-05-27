<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\command;
use skim\cache\cache;

// Usage:
//   php skim cache:clear           → flushes everything
//   php skim cache:clear user:     → flushes only keys with 'user:' prefix
//   php skim cache:flush           → alias for clear
class cache_command extends command {
    public function handle(): int {
        $sub    = $this->arg(0, 'clear');
        $prefix = $this->arg(1, '');

        if (in_array($sub, ['clear', 'flush'], true)) {
            $prefix !== '' ? cache::flush($prefix) : cache::flush_all();
            $msg = $prefix !== '' ? "Cache prefix '{$prefix}' cleared." : 'Cache cleared.';
            $this->success($msg);
            return 0;
        }

        $this->error("Unknown sub-command: {$sub}");
        return 1;
    }
}
