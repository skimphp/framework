<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Core\Request;
use Skim\Core\Response;

describe('app boot lifecycle', function (): void {

    test('App::instance() does not call boot() — completes quickly', function (): void {
        class_exists(\Skim\Core\App::class);

        $start   = hrtime(true);
        $app     = \Skim\Core\App::instance();
        $elapsed = (hrtime(true) - $start) / 1e6;

        expect($elapsed)->toBeLessThan(10.0)
            ->and($app)->toBeInstanceOf(\Skim\Core\App::class);
    });

    test('App::instance() returns same singleton', function (): void {
        $first  = \Skim\Core\App::instance();
        $second = \Skim\Core\App::instance();

        expect($first)->toBe($second);
    });

    test('App::testInstance() creates isolated instance', function (): void {
        $singleton = \Skim\Core\App::instance();
        $isolated  = \Skim\Core\App::testInstance(['app.debug' => false]);

        expect($isolated)->toBeInstanceOf(\Skim\Core\App::class)
            ->and($isolated)->not->toBe($singleton);
    });

    test('dispatch() triggers ensureBooted and returns 404 for unknown route', function (): void {
        $app = \Skim\Core\App::testInstance(['app.debug' => false]);
        $req = \Skim\Core\Request::make('GET', '/nonexistent-route');
        $res = $app->dispatch($req, new \Skim\Core\Response());

        expect($res)->toBeInstanceOf(\Skim\Core\Response::class)
            ->and($res->getStatus())->toBe(404);
    });

    test('dispatch() returns 405 for method mismatch', function (): void {
        $app = \Skim\Core\App::testInstance(['app.debug' => false]);
        $app->boot();
        $app->router->get('/only-get', fn() => 'ok');

        $req = \Skim\Core\Request::make('POST', '/only-get');
        $res = $app->dispatch($req, new \Skim\Core\Response());

        expect($res->getStatus())->toBe(405);
    });

});
