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

describe('event::reset_request()', function(): void {

    test('capture_boot_snapshot preserves boot-time listeners across resets', function(): void {
        event::off(); // clear snapshot for a clean slate
        $bootCalls = 0;
        event::on('boot.event', function() use (&$bootCalls): void { $bootCalls++; });

        event::capture_boot_snapshot(); // explicit boot snapshot

        // Request-time listener registered after snapshot should NOT survive the next reset.
        $reqCalls = 0;
        event::on('req.event', function() use (&$reqCalls): void { $reqCalls++; });

        event::reset_request(); // restore snapshot, drop req.event

        event::emit('boot.event', null);
        event::emit('req.event', null);

        expect($bootCalls)->toBe(1); // boot-time listener survived
        expect($reqCalls)->toBe(0);  // request-time listener was cleared
    });

    test('snapshot is cleared by off() so it does not leak between tests', function(): void {
        event::off();
        expect(event::listener_count('boot.event'))->toBe(0);
    });

});
