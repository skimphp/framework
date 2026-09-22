<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Core\Request;
use Skim\Core\Response;
use Skim\Events\Event;
use Skim\View\View;
use Skim\View\ComponentCollector;
use Skim\Worker\WorkerReset;

beforeEach(function (): void {
    \Skim\Events\Event::off();
    \Skim\View\View::reset();
    \Skim\View\ComponentCollector::resetRequest();
});

function bootWorkerApp(): \Skim\Core\App {
    $app = \Skim\Core\App::testInstance(['app.debug' => false, 'app.view.default_layout' => null]);
    $app->router->get('/ping', fn(): array => ['ok' => true]);
    $app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));
    \Skim\Events\Event::on('boot.ping', fn() => null);
    $app->boot();
    $app->bootExtensions();
    $app->freeze();
    \Skim\Events\Event::captureBootSnapshot();
    return $app;
}

function runOnce(\Skim\Core\App $app, string $uri): void {
    $app->beginRequest();
    try {
        $app->dispatch(\Skim\Core\Request::make('GET', $uri), new \Skim\Core\Response());
    } catch (\Throwable $e) {
        ob_start();
        $app->handleException($e);
        ob_end_clean();
    } finally {
        $app->endRequest();
    }
}

describe('worker lifecycle — in-process gate (T2)', function (): void {

    test('user scope and view shared data are cleared between requests', function (): void {
        $app = bootWorkerApp();

        runOnce($app, '/ping');
        $app->set('user.name', 'alice');
        \Skim\View\View::share('title', 'test');

        expect($app->get('user.name'))->toBe('alice');
        expect(\Skim\View\View::getShared('title'))->toBe('test');

        runOnce($app, '/ping');

        expect($app->get('user.name'))->toBeNull();
        expect(\Skim\View\View::getShared('title'))->toBeNull();
    });

    test('request-time event listeners do not accumulate', function (): void {
        $app = bootWorkerApp();
        $bootBaseline = \Skim\Events\Event::listenerCount('boot.ping');

        for ($i = 0; $i < 10; $i++) {
            runOnce($app, '/ping');
            \Skim\Events\Event::on('req.ping', fn() => null);
            runOnce($app, '/ping'); // end_request triggers reset
        }

        expect(\Skim\Events\Event::listenerCount('boot.ping'))->toBe($bootBaseline);
        expect(\Skim\Events\Event::listenerCount('req.ping'))->toBe(0);
    });

    test('lazy-loaded resettable is discovered and reset', function (): void {
        $app = bootWorkerApp();
        runOnce($app, '/ping');

        $class = 'late_resettable_' . str_replace('.', '', uniqid('', true));
        eval("class {$class} implements \\Skim\\Worker\\Resettable {
            public static bool \$was_reset = false;
            public static function resetRequest(): void { static::\$was_reset = true; }
        }");

        runOnce($app, '/ping');

        expect(\Skim\Worker\WorkerReset::discovered())->toContain($class);
        expect($class::$was_reset)->toBeTrue();
    });

    test('post-error isolation leaves no stale state', function (): void {
        $app = bootWorkerApp();
        $baselineOb = ob_get_level();

        runOnce($app, '/boom');

        runOnce($app, '/ping');

        expect($app->get('user.name'))->toBeNull();
        expect(\Skim\View\ComponentCollector::current())->toBeNull();
        expect(ob_get_level())->toBe($baselineOb);
    });

    test('memory is bounded over many requests', function (): void {
        $app = bootWorkerApp();

        // Warmup: first requests legitimately allocate.
        for ($i = 0; $i < 5; $i++) {
            runOnce($app, '/ping');
        }

        $baseline = memory_get_usage(true);
        $peakDelta = 0;

        for ($i = 0; $i < 1000; $i++) {
            runOnce($app, '/ping');
            $peakDelta = max($peakDelta, memory_get_usage(true) - $baseline);
        }

        $mb = $peakDelta / 1024 / 1024;
        expect($mb)->toBeLessThan(1.0,
            "Memory grew {$mb} MB over 1000 requests after warmup"
        );
    });

});
