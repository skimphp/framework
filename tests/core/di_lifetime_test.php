<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Core\Config;
use Skim\Core\Lifetime;

describe('DI lifetime — explicit lifetime enum', function (): void {

    afterEach(function (): void {
        \Skim\Core\Config::set('app.strict_di', false);
    });

    test('bind with lifetime::Singleton returns the same instance on repeated make()', function (): void {
        $app = \Skim\Core\App::test_instance();
        $app->bind('svc.s', fn() => new \stdClass(), lifetime: \Skim\Core\Lifetime::Singleton);

        $first  = $app->make('svc.s');
        $second = $app->make('svc.s');

        expect($first)->toBe($second);
    });

    test('bind with lifetime::Transient returns a different instance each make()', function (): void {
        $app = \Skim\Core\App::test_instance();
        $app->bind('svc.t', fn() => new \stdClass(), lifetime: \Skim\Core\Lifetime::Transient);

        $first  = $app->make('svc.t');
        $second = $app->make('svc.t');

        expect($first)->not->toBe($second);
    });

    test('bind with lifetime::Request is cleared after end_request', function (): void {
        $app = \Skim\Core\App::test_instance();
        $app->bind('svc.r', fn() => new \stdClass(), lifetime: \Skim\Core\Lifetime::Request);

        $before = $app->make('svc.r');
        $app->end_request();
        $after = $app->make('svc.r');

        expect($after)->not->toBe($before);
    });

    test('strict_di on: bind without lifetime throws LogicException', function (): void {
        \Skim\Core\Config::set('app.strict_di', true);
        $app = \Skim\Core\App::test_instance();

        expect(fn() => $app->bind('svc.x', fn() => 'x'))
            ->toThrow(\LogicException::class);
    });

    test('strict_di on: explicit lifetime and helpers do not throw', function (): void {
        \Skim\Core\Config::set('app.strict_di', true);
        $app = \Skim\Core\App::test_instance();

        expect(fn() => $app->bind('svc.s', fn() => 's', lifetime: \Skim\Core\Lifetime::Singleton))
            ->not->toThrow(\Throwable::class);
        expect(fn() => $app->bind_request('svc.r', fn() => 'r'))
            ->not->toThrow(\Throwable::class);
        expect(fn() => $app->bind_transient('svc.t', fn() => 't'))
            ->not->toThrow(\Throwable::class);
    });

    test('strict_di off: bind without lifetime defaults to singleton', function (): void {
        \Skim\Core\Config::set('app.strict_di', false);
        $app = \Skim\Core\App::test_instance();
        $app->bind('svc.x', fn() => new \stdClass());

        $first  = $app->make('svc.x');
        $second = $app->make('svc.x');

        expect($first)->toBe($second);
    });

});
