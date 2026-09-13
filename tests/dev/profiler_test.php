<?php declare(strict_types=1);

use Skim\Dev\Profiler;

beforeEach(function(): void {
    \Skim\Dev\Profiler::reset();
    \Skim\Dev\Profiler::disable();
});

describe('profiler — disabled (production mode)', function(): void {

    test('db() is a no-op when profiler is disabled', function(): void {
        \Skim\Dev\Profiler::db('SELECT 1', 1.5);
        expect(\Skim\Dev\Profiler::events())->toBeEmpty();
    });

    test('cache() is a no-op when profiler is disabled', function(): void {
        \Skim\Dev\Profiler::cache('get', 'some_key');
        expect(\Skim\Dev\Profiler::events())->toBeEmpty();
    });

    test('view() is a no-op when profiler is disabled', function(): void {
        \Skim\Dev\Profiler::view('home.index');
        expect(\Skim\Dev\Profiler::events())->toBeEmpty();
    });

});

describe('profiler — enabled (debug mode)', function(): void {

    beforeEach(function(): void {
        \Skim\Dev\Profiler::enable();
    });

    test('records db queries with sql, ms, connection, rows', function(): void {
        \Skim\Dev\Profiler::db('SELECT * FROM users', 12.3, 'default', 5);
        $events = \Skim\Dev\Profiler::events();
        expect($events)->toHaveCount(1);
        expect($events[0]['type'])->toBe('db');
        expect($events[0]['sql'])->toBe('SELECT * FROM users');
        expect($events[0]['ms'])->toBe(12.3);
        expect($events[0]['rows'])->toBe(5);
    });

    test('records cache hits and misses', function(): void {
        \Skim\Dev\Profiler::cache('get', 'user:1', hit: true, driver: 'array');
        \Skim\Dev\Profiler::cache('get', 'user:2', hit: false, driver: 'array');

        $summary = \Skim\Dev\Profiler::summary();
        expect($summary['cache']['hits'])->toBe(1);
        expect($summary['cache']['misses'])->toBe(1);
    });

    test('records view renders', function(): void {
        \Skim\Dev\Profiler::view('home.index', null, 5.2);
        \Skim\Dev\Profiler::view('partials.user', 'user-card', 1.1);
        expect(\Skim\Dev\Profiler::summary()['views'])->toBe(2);
    });

    test('summary() returns correct db totals', function(): void {
        \Skim\Dev\Profiler::db('SELECT 1', 5.0);
        \Skim\Dev\Profiler::db('SELECT 2', 10.0);
        $summary = \Skim\Dev\Profiler::summary();
        expect($summary['db']['count'])->toBe(2);
        expect($summary['db']['ms'])->toBe(15.0);
    });

});

describe('Profiler::reset()', function(): void {

    test('clears event buffer but does not disable profiler', function(): void {
        \Skim\Dev\Profiler::enable();
        \Skim\Dev\Profiler::db('SELECT 1', 1.0);
        \Skim\Dev\Profiler::reset();
        expect(\Skim\Dev\Profiler::events())->toBeEmpty();

        // profiler still enabled — can record again
        \Skim\Dev\Profiler::db('SELECT 2', 2.0);
        expect(\Skim\Dev\Profiler::events())->toHaveCount(1);
    });

});
