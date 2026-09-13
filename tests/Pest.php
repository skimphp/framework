<?php declare(strict_types=1);

// Pest bootstrap — loaded before any test file.
// Defines shared helpers and global test setup.

uses()->beforeEach(function(): void {
    \Skim\Core\Config::reset();
    \Skim\Core\Env::reset();
    \Skim\Dev\Profiler::reset();
    \Skim\View\View::reset();
    \Skim\Cache\Cache::reset();
    \Skim\Log\Log::reset();
    \Skim\I18n\I18n::reset();
    \Skim\Events\Event::off();
    \Skim\Db\Db::reset();
})->in(__DIR__);
