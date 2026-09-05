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
    \Skim\View\ComponentCollector::reset_request();
});

function boot_worker_app(): \Skim\Core\App {
    $app = \Skim\Core\App::test_instance(['app.debug' => false, 'app.view.default_layout' => null]);
    $app->router->get('/ping', fn(): array => ['ok' => true]);
    $app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));
    \Skim\Events\Event::on('boot.ping', fn() => null);
    $app->boot();
    $app->boot_extensions();
    $app->freeze();
    \Skim\Events\Event::capture_boot_snapshot();
    return $app;
}

function run_once(\Skim\Core\App $app, string $uri): void {
    $app->begin_request();
    try {
        $app->dispatch(\Skim\Core\Request::make('GET', $uri), new \Skim\Core\Response());
    } catch (\Throwable $e) {
        ob_start();
        $app->handle_exception($e);
        ob_end_clean();
    } finally {
        $app->end_request();
    }
}

describe('worker lifecycle — in-process gate (T2)', function (): void {

    test('user scope and view shared data are cleared between requests', function (): void {
        $app = boot_worker_app();

        run_once($app, '/ping');
        $app->set('user.name', 'alice');
        \Skim\View\View::share('title', 'test');

        expect($app->get('user.name'))->toBe('alice');
        expect(\Skim\View\View::get_shared('title'))->toBe('test');

        run_once($app, '/ping');

        expect($app->get('user.name'))->toBeNull();
        expect(\Skim\View\View::get_shared('title'))->toBeNull();
    });

    test('request-time event listeners do not accumulate', function (): void {
        $app = boot_worker_app();
        $boot_baseline = \Skim\Events\Event::listener_count('boot.ping');

        for ($i = 0; $i < 10; $i++) {
            run_once($app, '/ping');
            \Skim\Events\Event::on('req.ping', fn() => null);
            run_once($app, '/ping'); // end_request triggers reset
        }

        expect(\Skim\Events\Event::listener_count('boot.ping'))->toBe($boot_baseline);
        expect(\Skim\Events\Event::listener_count('req.ping'))->toBe(0);
    });

    test('lazy-loaded resettable is discovered and reset', function (): void {
        $app = boot_worker_app();
        run_once($app, '/ping');

        $class = 'late_resettable_' . str_replace('.', '', uniqid('', true));
        eval("class {$class} implements \\skim\\worker\\resettable {
            public static bool \$was_reset = false;
            public static function reset_request(): void { static::\$was_reset = true; }
        }");

        run_once($app, '/ping');

        expect(\Skim\Worker\WorkerReset::discovered())->toContain($class);
        expect($class::$was_reset)->toBeTrue();
    });

    test('post-error isolation leaves no stale state', function (): void {
        $app = boot_worker_app();
        $baseline_ob = ob_get_level();

        run_once($app, '/boom');

        run_once($app, '/ping');

        expect($app->get('user.name'))->toBeNull();
        expect(\Skim\View\ComponentCollector::current())->toBeNull();
        expect(ob_get_level())->toBe($baseline_ob);
    });

    test('memory is bounded over many requests', function (): void {
        $app = boot_worker_app();

        // Warmup: first requests legitimately allocate.
        for ($i = 0; $i < 5; $i++) {
            run_once($app, '/ping');
        }

        $baseline = memory_get_usage(true);
        $peak_delta = 0;

        for ($i = 0; $i < 1000; $i++) {
            run_once($app, '/ping');
            $peak_delta = max($peak_delta, memory_get_usage(true) - $baseline);
        }

        $mb = $peak_delta / 1024 / 1024;
        expect($mb)->toBeLessThan(1.0,
            "Memory grew {$mb} MB over 1000 requests after warmup"
        );
    });

});
