<?php declare(strict_types=1);

use skim\events\event;

beforeEach(function(): void {
    event::off();   // reset all listeners between tests
});

describe('event::on() and emit()', function(): void {

    test('registered listener is called on emit', function(): void {
        $called = false;
        event::on('user.created', function() use (&$called): void {
            $called = true;
        });
        event::emit('user.created', ['id' => 1]);
        expect($called)->toBeTrue();
    });

    test('multiple listeners all run in priority order', function(): void {
        $log = [];
        event::on('order', function() use (&$log): void { $log[] = 'normal'; }, priority: 0);
        event::on('order', function() use (&$log): void { $log[] = 'high'; },   priority: 10);
        event::on('order', function() use (&$log): void { $log[] = 'low'; },    priority: -5);

        event::emit('order', null);

        expect($log)->toBe(['high', 'normal', 'low']);
    });

    test('typed event object is passed to listener', function(): void {
        $received = null;
        $ev       = new class('test@example.com') {
            public function __construct(public readonly string $email) {}
        };

        event::on(get_class($ev), function($e) use (&$received): void {
            $received = $e;
        });

        event::emit($ev);
        expect($received)->toBe($ev);
    });

    test('no listeners registered → emit() is a safe no-op', function(): void {
        event::emit('nonexistent.event', null);
        expect(true)->toBeTrue();   // must not throw
    });

});

describe('event::once()', function(): void {

    test('once() listener is removed after first call', function(): void {
        $calls = 0;
        event::once('ping', function() use (&$calls): void { $calls++; });

        event::emit('ping', null);
        event::emit('ping', null);
        event::emit('ping', null);

        expect($calls)->toBe(1);
    });

});

describe('event::off()', function(): void {

    test('off($event) removes listeners for that event only', function(): void {
        $a = $b = false;
        event::on('a', function() use (&$a): void { $a = true; });
        event::on('b', function() use (&$b): void { $b = true; });

        event::off('a');

        event::emit('a', null);
        event::emit('b', null);

        expect($a)->toBeFalse();
        expect($b)->toBeTrue();
    });

    test('off() with no argument removes all listeners', function(): void {
        event::on('x', fn() => null);
        event::on('y', fn() => null);
        event::off();
        expect(event::listener_count('x'))->toBe(0);
        expect(event::listener_count('y'))->toBe(0);
    });

});
