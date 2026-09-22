<?php declare(strict_types=1);

use Skim\Cache\ArrayDriver;
use Skim\Cache\Cache;
use Skim\Cli\Commands\CacheCommand;

describe('cache command', function(): void {

    beforeEach(function(): void {
        Cache::setDriver(new ArrayDriver());
        Cache::set('user:1:name', 'a');
        Cache::set('user:2:name', 'b');
        Cache::set('other:key', 'c');
    });

    test('clear with prefix flushes only matching keys', function(): void {
        $cmd = new CacheCommand();
        $cmd->setInput(['clear', 'user:'], []);

        ob_start();
        $code = $cmd->handle();
        ob_end_clean();

        expect($code)->toBe(0);
        expect(Cache::get('user:1:name'))->toBeNull();
        expect(Cache::get('other:key'))->toBe('c');
    });

    test('clear without prefix flushes everything', function(): void {
        $cmd = new CacheCommand();
        $cmd->setInput(['clear'], []);

        ob_start();
        $code = $cmd->handle();
        ob_end_clean();

        expect($code)->toBe(0);
        expect(Cache::get('user:1:name'))->toBeNull();
        expect(Cache::get('other:key'))->toBeNull();
    });

    test('flush sub-command behaves like clear', function(): void {
        $cmd = new CacheCommand();
        $cmd->setInput(['flush', 'other:'], []);

        ob_start();
        $code = $cmd->handle();
        ob_end_clean();

        expect($code)->toBe(0);
        expect(Cache::get('other:key'))->toBeNull();
        expect(Cache::get('user:1:name'))->toBe('a');
    });

    test('unknown sub-command returns error code', function(): void {
        $cmd = new CacheCommand();
        $cmd->setInput(['bogus'], []);

        ob_start();
        $code = $cmd->handle();
        ob_end_clean();

        expect($code)->toBe(1);
        expect(Cache::get('user:1:name'))->toBe('a');
    });

});
