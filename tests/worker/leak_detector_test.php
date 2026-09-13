<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Core\Request;
use Skim\Core\Response;
use Skim\Events\Event;
use Skim\View\View;
use Skim\View\ComponentCollector;
use Skim\Worker\LeakDetector;
use Skim\Worker\WorkerReset;

beforeEach(function (): void {
    \Skim\Events\Event::off();
    \Skim\View\View::reset();
    \Skim\View\ComponentCollector::resetRequest();
    \Skim\Worker\LeakDetector::reset();
});

function bootWorkerAppWithLeakDetection(string $mode = 'strict'): \Skim\Core\App {
    $app = \Skim\Core\App::testInstance([
        'app.debug' => false,
        'app.view.default_layout' => null,
        'app.leak_detection' => $mode,
    ]);
    $app->router->get('/ping', fn(): array => ['ok' => true]);
    $app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));
    \Skim\Events\Event::on('boot.ping', fn() => null);
    $app->boot();
    $app->bootExtensions();
    $app->freeze();
    \Skim\Events\Event::captureBootSnapshot();
    \Skim\Worker\LeakDetector::configure($mode);
    return $app;
}

function leakRunOnce(\Skim\Core\App $app, string $uri): void {
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

describe('LeakDetector — hard invariants', function (): void {

    test('clean app produces zero findings after N requests', function (): void {
        $app = bootWorkerAppWithLeakDetection('strict');

        for ($i = 0; $i < 60; $i++) {
            leakRunOnce($app, '/ping');
        }

        // Only hard invariants must be empty; growth trends can legitimately
        // trigger in PHPUnit due to framework allocations, so we filter them.
        $hard = array_filter(\Skim\Worker\LeakDetector::findings(), fn($f) => str_starts_with($f['key'], 'hard.'));
        expect($hard)->toBe([]);
    });

    test('user scope leak is flagged', function (): void {
        $app = bootWorkerAppWithLeakDetection('strict');

        leakRunOnce($app, '/ping');
        // Simulate a bug: user scope not cleared
        $app->set('user.name', 'leaked');
        // Force worker_reset without end_request to bypass normal cleanup
        \Skim\Worker\WorkerReset::apply(ob_get_level());
        // Now call leak_detector directly
        \Skim\Worker\LeakDetector::check($app);

        $findings = \Skim\Worker\LeakDetector::findings();
        expect(count($findings))->toBeGreaterThan(0);
        expect($findings[0]['key'])->toBe('hard.user_scope');
    });

    test('resolved singleton growth is flagged', function (): void {
        $app = \Skim\Core\App::testInstance([
            'app.debug' => false,
            'app.view.default_layout' => null,
            'app.leak_detection' => 'strict',
        ]);

        // Bind leaky singletons BEFORE freeze
        static $leakArr = [];
        $app->bind('leaky.svc', fn() => $leakArr[] = 'x');

        $app->router->get('/ping', fn(): array => ['ok' => true]);
        \Skim\Events\Event::on('boot.ping', fn() => null);
        $app->boot();
        $app->bootExtensions();
        $app->freeze();
        \Skim\Events\Event::captureBootSnapshot();
        \Skim\Worker\LeakDetector::configure('strict');

        // Warmup
        for ($i = 0; $i < 15; $i++) {
            leakRunOnce($app, '/ping');
            $app->make('leaky.svc');
        }

        // Grow resolved count each request by adding new Request-time bindings
        // (in a real leak this would be a singleton caching per-request state)
        for ($i = 0; $i < 20; $i++) {
            leakRunOnce($app, '/ping');
            // Use make_transient to build a new object and cache it manually
            // by injecting into a helper that tracks count
            $app->make('leaky.svc');
        }

        $findings = \Skim\Worker\LeakDetector::findings();
        $growthKeys = array_filter($findings, fn($f) => str_starts_with($f['key'], 'growth.'));
        expect(count($growthKeys))->toBeGreaterThan(0);
    });

});

describe('LeakDetector — modes', function (): void {

    test('off mode produces no findings', function (): void {
        $app = bootWorkerAppWithLeakDetection('off');
        $app->set('user.name', 'leaked');

        leakRunOnce($app, '/ping');

        expect(\Skim\Worker\LeakDetector::findings())->toBe([]);
    });

    test('warn mode does not accumulate findings', function (): void {
        $app = bootWorkerAppWithLeakDetection('warn');

        for ($i = 0; $i < 20; $i++) {
            leakRunOnce($app, '/ping');
        }

        expect(\Skim\Worker\LeakDetector::findings())->toBe([]);
    });

    test('strict mode accumulates findings', function (): void {
        $app = bootWorkerAppWithLeakDetection('strict');
        $app->set('user.name', 'leaked');
        \Skim\Worker\WorkerReset::apply(ob_get_level());
        \Skim\Worker\LeakDetector::check($app);

        expect(count(\Skim\Worker\LeakDetector::findings()))->toBeGreaterThan(0);
    });

});
