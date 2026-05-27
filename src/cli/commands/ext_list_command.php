<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\cli;
use skim\cli\command;
use skim\ext\capability_vocabulary;
use skim\ext\ext_registry;

/**
 * Lists installed SKIM extensions from local Composer metadata.
 */
class ext_list_command extends command {
    /**
     * @ai-contract accepts optional registry for tests; defaults to scanning base_path()/vendor
     */
    public function __construct(
        private readonly ?ext_registry $registry = null,
    ) {}

    /**
     * @ai-contract prints installed SKIM extensions without network access
     */
    public function handle(): int {
        $extensions = $this->registry()->installed();

        if ($extensions === []) {
            $this->info('No SKIM extensions installed.');
            return 0;
        }

        $this->line('Installed SKIM extensions:');
        $this->line();

        foreach ($extensions as $extension) {
            $this->line(sprintf(
                '%-12s v%-7s %s',
                $extension['name'],
                $extension['version'],
                $extension['description'],
            ));

            $capabilities = $extension['capabilities'] ?? [];
            if ($capabilities !== []) {
                $this->line('  capabilities: ' . implode(', ', $capabilities));
            }

            foreach ($capabilities as $capability) {
                if (!capability_vocabulary::is_known($capability)) {
                    $this->warn("Unknown capability '{$capability}' declared by {$extension['name']}");
                }
            }

            $this->line();
        }

        foreach ($this->registry()->conflicts() as $conflict) {
            $this->warn('Conflict: ' . ucfirst($conflict['message']));
        }

        return 0;
    }

    private function registry(): ext_registry {
        return $this->registry ?? new ext_registry(base_path());
    }
}
