<?php declare(strict_types=1);

use Skim\Events\Event;

beforeEach(function(): void {
    \Skim\Events\Event::off();   // reset all listeners between tests
});

describe('Event::on() and emit()', function(): void {

    test('registered listener is called on emit', function(): void {
        $called = false;
        \Skim\Events\Event::on('user.created', function() use (&$called): void {
            $called = true;
        });
        \Skim\Events\Event::emit('user.created', ['id' => 1]);
        expect($called)->toBeTrue();
    });

    test('multiple listeners all run in priority order', function(): void {
        $log = [];
        \Skim\Events\Event::on('order', function() use (&$log): void { $log[] = 'normal'; }, priority: 0);
        \Skim\Events\Event::on('order', function() use (&$log): void { $log[] = 'high'; },   priority: 10);
        \Skim\Events\Event::on('order', function() use (&$log): void { $log[] = 'low'; },    priority: -5);

        \Skim\Events\Event::emit('order', null);

        expect($log)->toBe(['high', 'normal', 'low']);
    });

    test('typed event object is passed to listener', function(): void {
        $received = null;
        $ev       = new class('test@example.com') {
            public function __construct(public readonly string $email) {}
        };

        \Skim\Events\Event::on(get_class($ev), function($e) use (&$received): void {
            $received = $e;
        });

        \Skim\Events\Event::emit($ev);
        expect($received)->toBe($ev);
    });

    test('no listeners registered → emit() is a safe no-op', function(): void {
        \Skim\Events\Event::emit('nonexistent.event', null);
        expect(true)->toBeTrue();   // must not throw
    });

});

describe('Event::once()', function(): void {

    test('once() listener is removed after first call', function(): void {
        $calls = 0;
        \Skim\Events\Event::once('ping', function() use (&$calls): void { $calls++; });

        \Skim\Events\Event::emit('ping', null);
        \Skim\Events\Event::emit('ping', null);
        \Skim\Events\Event::emit('ping', null);

        expect($calls)->toBe(1);
    });

});

describe('Event::off()', function(): void {

    test('off($event) removes listeners for that event only', function(): void {
        $a = $b = false;
        \Skim\Events\Event::on('a', function() use (&$a): void { $a = true; });
        \Skim\Events\Event::on('b', function() use (&$b): void { $b = true; });

        \Skim\Events\Event::off('a');

        \Skim\Events\Event::emit('a', null);
        \Skim\Events\Event::emit('b', null);

        expect($a)->toBeFalse();
        expect($b)->toBeTrue();
    });

    test('off() with no argument removes all listeners', function(): void {
        \Skim\Events\Event::on('x', fn() => null);
        \Skim\Events\Event::on('y', fn() => null);
        \Skim\Events\Event::off();
        expect(\Skim\Events\Event::listenerCount('x'))->toBe(0);
        expect(\Skim\Events\Event::listenerCount('y'))->toBe(0);
    });

});

describe('Event::resetRequest()', function(): void {

    test('captureBootSnapshot preserves boot-time listeners across resets', function(): void {
        \Skim\Events\Event::off(); // clear snapshot for a clean slate
        $bootCalls = 0;
        \Skim\Events\Event::on('boot.event', function() use (&$bootCalls): void { $bootCalls++; });

        \Skim\Events\Event::captureBootSnapshot(); // explicit boot snapshot

        // Request-time listener registered after snapshot should NOT survive the next reset.
        $reqCalls = 0;
        \Skim\Events\Event::on('req.event', function() use (&$reqCalls): void { $reqCalls++; });

        \Skim\Events\Event::resetRequest(); // restore snapshot, drop req.event

        \Skim\Events\Event::emit('boot.event', null);
        \Skim\Events\Event::emit('req.event', null);

        expect($bootCalls)->toBe(1); // boot-time listener survived
        expect($reqCalls)->toBe(0);  // request-time listener was cleared
    });

    test('snapshot is cleared by off() so it does not leak between tests', function(): void {
        \Skim\Events\Event::off();
        expect(\Skim\Events\Event::listenerCount('boot.event'))->toBe(0);
    });

});
