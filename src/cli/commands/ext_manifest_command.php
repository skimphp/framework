<?php declare(strict_types=1);

namespace skim\cli\commands;

use skim\cli\command;
use skim\ext\ext_manifest;
use skim\ext\extension;

class ext_manifest_command extends command {
    protected string $description = 'Generate skim.json from extension class';
    protected string $usage = '<ExtensionClass>';

    public function handle(): int {
        $class = $this->arg(0);
        if (!$class || !class_exists($class) || !is_subclass_of($class, extension::class)) {
            $this->error('Usage: php skim ext:manifest <ExtensionClass>');
            return 1;
        }

        ext_manifest::generate(new $class(), base_path('skim.json'));
        $this->success('Generated: ' . base_path('skim.json'));
        return 0;
    }
}
