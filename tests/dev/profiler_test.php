<?php declare(strict_types=1);

use skim\dev\profiler;

beforeEach(function(): void {
    profiler::reset();
    profiler::disable();
});

describe('profiler — disabled (production mode)', function(): void {

    test('db() is a no-op when profiler is disabled', function(): void {
        profiler::db('SELECT 1', 1.5);
        expect(profiler::events())->toBeEmpty();
    });

    test('cache() is a no-op when profiler is disabled', function(): void {
        profiler::cache('get', 'some_key');
        expect(profiler::events())->toBeEmpty();
    });

    test('view() is a no-op when profiler is disabled', function(): void {
        profiler::view('home.index');
        expect(profiler::events())->toBeEmpty();
    });

});

describe('profiler — enabled (debug mode)', function(): void {

    beforeEach(function(): void {
        profiler::enable();
    });

    test('records db queries with sql, ms, connection, rows', function(): void {
        profiler::db('SELECT * FROM users', 12.3, 'default', 5);
        $events = profiler::events();
        expect($events)->toHaveCount(1);
        expect($events[0]['type'])->toBe('db');
        expect($events[0]['sql'])->toBe('SELECT * FROM users');
        expect($events[0]['ms'])->toBe(12.3);
        expect($events[0]['rows'])->toBe(5);
    });

    test('records cache hits and misses', function(): void {
        profiler::cache('get', 'user:1', hit: true, driver: 'array');
        profiler::cache('get', 'user:2', hit: false, driver: 'array');

        $summary = profiler::summary();
        expect($summary['cache']['hits'])->toBe(1);
        expect($summary['cache']['misses'])->toBe(1);
    });

    test('records view renders', function(): void {
        profiler::view('home.index', null, 5.2);
        profiler::view('partials.user', 'user-card', 1.1);
        expect(profiler::summary()['views'])->toBe(2);
    });

    test('summary() returns correct db totals', function(): void {
        profiler::db('SELECT 1', 5.0);
        profiler::db('SELECT 2', 10.0);
        $summary = profiler::summary();
        expect($summary['db']['count'])->toBe(2);
        expect($summary['db']['ms'])->toBe(15.0);
    });

});

describe('profiler::reset()', function(): void {

    test('clears event buffer but does not disable profiler', function(): void {
        profiler::enable();
        profiler::db('SELECT 1', 1.0);
        profiler::reset();
        expect(profiler::events())->toBeEmpty();

        // profiler still enabled — can record again
        profiler::db('SELECT 2', 2.0);
        expect(profiler::events())->toHaveCount(1);
    });

});
