<?php declare(strict_types=1);

use skim\core\app;
use skim\core\request;
use skim\core\response;

describe('app boot lifecycle', function (): void {

    test('app::instance() does not call boot() — completes quickly', function (): void {
        class_exists(app::class);

        $start   = hrtime(true);
        $app     = app::instance();
        $elapsed = (hrtime(true) - $start) / 1e6;

        expect($elapsed)->toBeLessThan(10.0)
            ->and($app)->toBeInstanceOf(app::class);
    });

    test('app::instance() returns same singleton', function (): void {
        $first  = app::instance();
        $second = app::instance();

        expect($first)->toBe($second);
    });

    test('app::test_instance() creates isolated instance', function (): void {
        $singleton = app::instance();
        $isolated  = app::test_instance(['app.debug' => false]);

        expect($isolated)->toBeInstanceOf(app::class)
            ->and($isolated)->not->toBe($singleton);
    });

    test('dispatch() triggers ensureBooted and returns 404 for unknown route', function (): void {
        $app = app::test_instance(['app.debug' => false]);
        $req = request::make('GET', '/nonexistent-route');
        $res = $app->dispatch($req, new response());

        expect($res)->toBeInstanceOf(response::class)
            ->and($res->get_status())->toBe(404);
    });

    test('dispatch() returns 405 for method mismatch', function (): void {
        $app = app::test_instance(['app.debug' => false]);
        $app->boot();
        $app->router->get('/only-get', fn() => 'ok');

        $req = request::make('POST', '/only-get');
        $res = $app->dispatch($req, new response());

        expect($res->get_status())->toBe(405);
    });

});
