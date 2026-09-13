<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Cli;
use Skim\Cli\Command;
use Skim\Ext\CapabilityVocabulary;
use Skim\Ext\ExtRegistry;

/**
 * Lists installed SKIM extensions by scanning local Composer vendor metadata.
 *
 * Use when checking which SKIM extensions are present in the project.
 * Reads from Composer's installed.json — no network access required.
 * Warns about unknown capabilities and inter-extension conflicts.
 *
 * Example:
 *   php skim ext:list
 *
 * Testing: Inject a mock ext_registry via constructor to control installed extensions.
 *
 * #AI:class
 */
class ExtListCommand extends \Skim\Cli\Command {
    /**
     * Accepts optional registry for tests. #AI:__construct
     *
     * @param \Skim\Ext\ExtRegistry|null $registry Test double; defaults to scanning basePath()/vendor.
     */
    public function __construct(
        private readonly ?\Skim\Ext\ExtRegistry $registry = null,
    ) {}

    /**
     * Prints installed extensions, capabilities, and conflict warnings. #AI:handle
     *
     * Scans local Composer metadata — no network access. Warns when an extension
     * declares capabilities not in the known vocabulary, and prints inter-extension
     * conflicts detected by the registry.
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
                if (!\Skim\Ext\CapabilityVocabulary::isKnown($capability)) {
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

    /**
     * Returns the injected or default extension registry. #AI:registry
     */
    private function registry(): \Skim\Ext\ExtRegistry {
        return $this->registry ?? new \Skim\Ext\ExtRegistry(basePath());
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\ExtListCommand
#AI source_path: src/Cli/Commands/ExtListCommand.php
#AI title: ExtListCommand
#AI description: CLI command that lists installed SKIM extensions from local Composer metadata with capability and conflict reporting.
#AI role: CLI extension lister
#AI layer: cli
#AI badges: [cli; command; extension; read-only]
#AI intro: `ExtListCommand` implements the `php skim ext:list` CLI command. It scans local Composer vendor metadata to list installed SKIM extensions, their capabilities, and any inter-extension conflicts. No network access is required.
#AI lifecycle: instantiated by kernel, handle() called once per invocation
#AI fallback: prints informational message when no extensions are installed
#AI test_seam: constructor accepts ExtRegistry test double
#AI invariants: [reads only from local Composer metadata; no network access; warns on unknown capabilities and conflicts]
#AI core_behaviors: [Lists extensions from ExtRegistry->installed(); Prints capabilities per extension; Warns on unknown capabilities via CapabilityVocabulary; Reports conflicts from ExtRegistry->conflicts()]
#AI owns: nothing — delegates to ExtRegistry
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not install or remove extensions; Does not check for updates; Does not validate extension compatibility]
#AI side_effects: [prints to stdout]
#AI flow: ExtListCommand::handle() -> registry()->installed() -> print each extension -> check capabilities -> registry()->conflicts() -> print warnings
#AI lifecycle_steps: [handle(); -> registry()->installed(); -> for each extension: print name/version/description; -> print capabilities; -> warn on unknown capabilities; -> registry()->conflicts(); -> print conflict warnings]
#AI section_order: [Command Execution; Architecture]
#AI architectural_notes: Constructor injection of ExtRegistry enables test isolation without filesystem access.

#AI:__construct
#AI group: Architecture
#AI frequency: low
#AI signature: public function __construct(?ExtRegistry $registry = null)
#AI contract: Accepts optional registry test double. Defaults to scanning basePath()/vendor.
#AI param_details: [{name: $registry | type: ?ExtRegistry | required: false | desc: Test double for extension registry. Null uses default.}]

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Prints installed extensions with capabilities and conflict warnings. Returns 0 always.
#AI return_detail: {type: int | desc: Always 0.}

#AI:registry
#AI group: Architecture
#AI frequency: internal
#AI signature: private function registry(): ExtRegistry
#AI contract: Returns the injected or default extension registry.
