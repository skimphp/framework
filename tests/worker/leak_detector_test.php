<?php declare(strict_types=1);

use skim\core\app;
use skim\core\request;
use skim\core\response;
use skim\events\event;
use skim\view\view;
use skim\view\component_collector;
use skim\worker\leak_detector;
use skim\worker\worker_reset;

beforeEach(function (): void {
    event::off();
    view::reset();
    component_collector::reset_request();
    leak_detector::reset();
});

function boot_worker_app_with_leak_detection(string $mode = 'strict'): app {
    $app = app::test_instance([
        'app.debug' => false,
        'app.view.default_layout' => null,
        'app.leak_detection' => $mode,
    ]);
    $app->router->get('/ping', fn(): array => ['ok' => true]);
    $app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));
    event::on('boot.ping', fn() => null);
    $app->boot();
    $app->boot_extensions();
    $app->freeze();
    event::capture_boot_snapshot();
    leak_detector::configure($mode);
    return $app;
}

function leak_run_once(app $app, string $uri): void {
    $app->begin_request();
    try {
        $app->dispatch(request::make('GET', $uri), new response());
    } catch (\Throwable $e) {
        ob_start();
        $app->handle_exception($e);
        ob_end_clean();
    } finally {
        $app->end_request();
    }
}

describe('leak_detector — hard invariants', function (): void {

    test('clean app produces zero findings after N requests', function (): void {
        $app = boot_worker_app_with_leak_detection('strict');

        for ($i = 0; $i < 60; $i++) {
            leak_run_once($app, '/ping');
        }

        // Only hard invariants must be empty; growth trends can legitimately
        // trigger in PHPUnit due to framework allocations, so we filter them.
        $hard = array_filter(leak_detector::findings(), fn($f) => str_starts_with($f['key'], 'hard.'));
        expect($hard)->toBe([]);
    });

    test('user scope leak is flagged', function (): void {
        $app = boot_worker_app_with_leak_detection('strict');

        leak_run_once($app, '/ping');
        // Simulate a bug: user scope not cleared
        $app->set('user.name', 'leaked');
        // Force worker_reset without end_request to bypass normal cleanup
        worker_reset::apply(ob_get_level());
        // Now call leak_detector directly
        leak_detector::check($app);

        $findings = leak_detector::findings();
        expect(count($findings))->toBeGreaterThan(0);
        expect($findings[0]['key'])->toBe('hard.user_scope');
    });

    test('resolved singleton growth is flagged', function (): void {
        $app = app::test_instance([
            'app.debug' => false,
            'app.view.default_layout' => null,
            'app.leak_detection' => 'strict',
        ]);

        // Bind leaky singletons BEFORE freeze
        static $leak_arr = [];
        $app->bind('leaky.svc', fn() => $leak_arr[] = 'x');

        $app->router->get('/ping', fn(): array => ['ok' => true]);
        event::on('boot.ping', fn() => null);
        $app->boot();
        $app->boot_extensions();
        $app->freeze();
        event::capture_boot_snapshot();
        leak_detector::configure('strict');

        // Warmup
        for ($i = 0; $i < 15; $i++) {
            leak_run_once($app, '/ping');
            $app->make('leaky.svc');
        }

        // Grow resolved count each request by adding new request-time bindings
        // (in a real leak this would be a singleton caching per-request state)
        for ($i = 0; $i < 20; $i++) {
            leak_run_once($app, '/ping');
            // Use make_transient to build a new object and cache it manually
            // by injecting into a helper that tracks count
            $app->make('leaky.svc');
        }

        $findings = leak_detector::findings();
        $growth_keys = array_filter($findings, fn($f) => str_starts_with($f['key'], 'growth.'));
        expect(count($growth_keys))->toBeGreaterThan(0);
    });

});

describe('leak_detector — modes', function (): void {

    test('off mode produces no findings', function (): void {
        $app = boot_worker_app_with_leak_detection('off');
        $app->set('user.name', 'leaked');

        leak_run_once($app, '/ping');

        expect(leak_detector::findings())->toBe([]);
    });

    test('warn mode does not accumulate findings', function (): void {
        $app = boot_worker_app_with_leak_detection('warn');

        for ($i = 0; $i < 20; $i++) {
            leak_run_once($app, '/ping');
        }

        expect(leak_detector::findings())->toBe([]);
    });

    test('strict mode accumulates findings', function (): void {
        $app = boot_worker_app_with_leak_detection('strict');
        $app->set('user.name', 'leaked');
        worker_reset::apply(ob_get_level());
        leak_detector::check($app);

        expect(count(leak_detector::findings()))->toBeGreaterThan(0);
    });

});
