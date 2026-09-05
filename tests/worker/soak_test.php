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
    \Skim\View\ComponentCollector::reset_request();
    \Skim\Worker\LeakDetector::reset();
});

function boot_soak_app(): \Skim\Core\App {
    $app = \Skim\Core\App::test_instance([
        'app.debug' => false,
        'app.view.default_layout' => null,
        'app.leak_detection' => 'strict',
    ]);

    // Realistic multi-component routes
    $app->router->get('/home', fn(): array => ['page' => 'home']);

    $app->router->get('/user/@id:int', function(\Skim\Core\Request $req, \Skim\Core\Response $res, string $id): array {
        return ['user_id' => (int) $id];
    });

    $app->router->post('/contact', function(): mixed {
        $result = \Skim\Validation\Validate::make([
            'email' => ['required', 'email'],
            'message' => ['required', 'string', 'min:5'],
        ])->check([
            'email' => 'test@example.com',
            'message' => 'hello world this is a message',
        ]);

        if (!$result->ok) {
            return (new \Skim\Core\Response())->status(422)->json(['errors' => $result->errors()]);
        }
        return ['sent' => true];
    });

    $app->router->get('/event', function(): array {
        \Skim\Events\Event::on('soak.demo', fn($data) => null);
        \Skim\Events\Event::emit('soak.demo', ['time' => microtime(true)]);
        return ['emitted' => true];
    });

    $app->router->get('/view', function(): array {
        \Skim\View\View::share('app_name', 'SKIM');
        return ['view_shared' => true];
    });

    $app->router->get('/component', function(): array {
        $collector = new \Skim\View\ComponentCollector();
        \Skim\View\ComponentCollector::push($collector);
        // 1% chance of throw (exercises post-error isolation)
        if (random_int(1, 100) === 1) {
            \Skim\View\ComponentCollector::pop();
            throw new \RuntimeException('render failed');
        }
        \Skim\View\ComponentCollector::pop();
        return ['component' => true];
    });

    $app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));

    \Skim\Events\Event::on('boot.warm', fn() => null);

    $app->boot();
    $app->boot_extensions();
    $app->freeze();

    \Skim\Events\Event::capture_boot_snapshot();
    \Skim\Worker\LeakDetector::configure('strict');

    return $app;
}

function soak_run_once(\Skim\Core\App $app, string $uri, string $method = 'GET', array $data = []): void {
    $app->begin_request();
    try {
        $req = \Skim\Core\Request::make($method, $uri, $data);
        $app->dispatch($req, new \Skim\Core\Response());
    } catch (\Throwable $e) {
        ob_start();
        $app->handle_exception($e);
        ob_end_clean();
    } finally {
        $app->end_request();
    }
}

describe('soak test — 1000 mixed requests', function (): void {

    test('zero leak findings after realistic workload', function (): void {
        $app = boot_soak_app();

        $routes = ['/home', '/user/42', '/event', '/view', '/component'];
        $errors_expected = 0;

        for ($i = 0; $i < 1000; $i++) {
            if ($i % 20 === 0) {
                soak_run_once($app, '/contact', 'POST');
            } elseif ($i % 50 === 0) {
                soak_run_once($app, '/boom');
                $errors_expected++;
            } else {
                $uri = $routes[$i % count($routes)] . '?req=' . $i;
                soak_run_once($app, $uri);
            }
        }

        $findings = \Skim\Worker\LeakDetector::findings();
        $hard = array_filter($findings, fn($f) => str_starts_with($f['key'], 'hard.'));
        $growth = array_filter($findings, fn($f) => str_starts_with($f['key'], 'growth.'));

        expect($hard)->toBe([]);
        expect($growth)->toBe([]);
    });

    test('memory stays bounded under mixed workload', function (): void {
        $app = boot_soak_app();

        $routes = ['/home', '/user/42', '/event', '/view', '/component'];

        // Warmup
        for ($i = 0; $i < 10; $i++) {
            soak_run_once($app, $routes[$i % count($routes)]);
        }

        $baseline = memory_get_usage(true);
        $peak_delta = 0;

        for ($i = 0; $i < 1000; $i++) {
            if ($i % 20 === 0) {
                soak_run_once($app, '/contact', 'POST');
            } elseif ($i % 50 === 0) {
                soak_run_once($app, '/boom');
            } else {
                soak_run_once($app, $routes[$i % count($routes)]);
            }
            $peak_delta = max($peak_delta, memory_get_usage(true) - $baseline);
        }

        $mb = $peak_delta / 1024 / 1024;
        expect($mb)->toBeLessThan(1.0,
            "Memory grew {$mb} MB over 1000 mixed requests after warmup"
        );
    });

});
