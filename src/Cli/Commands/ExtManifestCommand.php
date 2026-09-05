<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Command;
use Skim\Ext\ExtManifest;
use Skim\Ext\Extension;

/**
 * Generates skim.json manifest from an extension class for distribution.
 *
 * Use when publishing a SKIM extension — the manifest describes capabilities,
 * config, migrations, and env requirements for the extension registry.
 *
 * Example:
 *   php skim ext:manifest app\extensions\my_extension
 *
 * Testing: Instantiate directly and call handle() with test args.
 *
 * #AI:class
 */
class ExtManifestCommand extends \Skim\Cli\Command {
    protected string $description = 'Generate skim.json from extension class';
    protected string $usage = '<ExtensionClass>';

    /**
     * Generates skim.json from the given extension class. #AI:handle
     *
     * Validates that the argument is a class extending extension, then
     * delegates to ExtManifest::generate() to write skim.json at base_path.
     */
    public function handle(): int {
        $class = $this->arg(0);
        if (!$class || !class_exists($class) || !is_subclass_of($class, \Skim\Ext\Extension::class)) {
            $this->error('Usage: php skim ext:manifest <ExtensionClass>');
            return 1;
        }

        \Skim\Ext\ExtManifest::generate(new $class(), basePath('skim.json'));
        $this->success('Generated: ' . basePath('skim.json'));
        return 0;
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\ExtManifestCommand
#AI source_path: src/Cli/Commands/ExtManifestCommand.php
#AI title: ExtManifestCommand
#AI description: CLI command that generates skim.json manifest from an extension class for distribution.
#AI role: CLI manifest generator
#AI layer: cli
#AI badges: [cli; command; extension; manifest]
#AI intro: `ExtManifestCommand` implements the `php skim ext:manifest` CLI command. It takes a fully-qualified extension class name, validates it extends `extension`, and generates `skim.json` at the project root via `ExtManifest::generate()`.
#AI lifecycle: instantiated by kernel, handle() called once per invocation
#AI fallback: prints usage error when argument is missing or not a valid extension class
#AI test_seam: instantiate directly, call setInput() with test args, then handle()
#AI invariants: [argument must be a class extending extension; skim.json is written to basePath()]
#AI core_behaviors: [Validates class exists and extends Extension; Delegates to ExtManifest::generate(); Writes skim.json to project root]
#AI owns: nothing — delegates to ExtManifest
#AI entry_points: [handle]
#AI config_reads: []
#AI non_goals: [Does not validate manifest content; Does not publish to a registry]
#AI side_effects: [writes skim.json to basePath()]
#AI flow: ExtManifestCommand::handle() -> validate arg(0) is extension subclass -> ExtManifest::generate() -> write skim.json
#AI lifecycle_steps: [handle(); -> arg(0) class name; -> validate class_exists + is_subclass_of(extension); -> ExtManifest::generate(new $class(), basePath('skim.json')); -> print success]
#AI section_order: [Command Execution]
#AI architectural_notes: Thin CLI wrapper over ExtManifest::generate(). All manifest logic lives in ExtManifest.

#AI:handle
#AI group: Command Execution
#AI frequency: low
#AI signature: public function handle(): int
#AI contract: Generates skim.json from the given extension class. Validates the argument is a class extending extension, then delegates to ExtManifest::generate().
#AI return_detail: {type: int | desc: 0 on success, 1 on invalid argument.}
