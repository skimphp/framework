<?php declare(strict_types=1);

// Pest bootstrap — loaded before any test file.
// Defines shared helpers and global test setup.

uses()->beforeEach(function(): void {
    \skim\core\config::reset();
    \skim\core\env::reset();
    \skim\dev\profiler::reset();
    \skim\view\view::reset();
    \skim\cache\cache::reset();
    \skim\log\log::reset();
    \skim\i18n\i18n::reset();
    \skim\events\event::off();
    \skim\db\db::reset();
})->in(__DIR__);
