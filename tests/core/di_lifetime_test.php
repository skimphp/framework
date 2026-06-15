<?php declare(strict_types=1);

use skim\core\app;
use skim\core\config;
use skim\core\lifetime;

describe('DI lifetime — explicit lifetime enum', function (): void {

    afterEach(function (): void {
        config::set('app.strict_di', false);
    });

    test('bind with lifetime::singleton returns the same instance on repeated make()', function (): void {
        $app = app::test_instance();
        $app->bind('svc.s', fn() => new \stdClass(), lifetime: lifetime::singleton);

        $first  = $app->make('svc.s');
        $second = $app->make('svc.s');

        expect($first)->toBe($second);
    });

    test('bind with lifetime::transient returns a different instance each make()', function (): void {
        $app = app::test_instance();
        $app->bind('svc.t', fn() => new \stdClass(), lifetime: lifetime::transient);

        $first  = $app->make('svc.t');
        $second = $app->make('svc.t');

        expect($first)->not->toBe($second);
    });

    test('bind with lifetime::request is cleared after end_request', function (): void {
        $app = app::test_instance();
        $app->bind('svc.r', fn() => new \stdClass(), lifetime: lifetime::request);

        $before = $app->make('svc.r');
        $app->end_request();
        $after = $app->make('svc.r');

        expect($after)->not->toBe($before);
    });

    test('strict_di on: bind without lifetime throws LogicException', function (): void {
        config::set('app.strict_di', true);
        $app = app::test_instance();

        expect(fn() => $app->bind('svc.x', fn() => 'x'))
            ->toThrow(\LogicException::class);
    });

    test('strict_di on: explicit lifetime and helpers do not throw', function (): void {
        config::set('app.strict_di', true);
        $app = app::test_instance();

        expect(fn() => $app->bind('svc.s', fn() => 's', lifetime: lifetime::singleton))
            ->not->toThrow(\Throwable::class);
        expect(fn() => $app->bind_request('svc.r', fn() => 'r'))
            ->not->toThrow(\Throwable::class);
        expect(fn() => $app->bind_transient('svc.t', fn() => 't'))
            ->not->toThrow(\Throwable::class);
    });

    test('strict_di off: bind without lifetime defaults to singleton', function (): void {
        config::set('app.strict_di', false);
        $app = app::test_instance();
        $app->bind('svc.x', fn() => new \stdClass());

        $first  = $app->make('svc.x');
        $second = $app->make('svc.x');

        expect($first)->toBe($second);
    });

});
