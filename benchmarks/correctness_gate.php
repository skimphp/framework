<?php declare(strict_types=1);

// In-process correctness gate for worker mode.
// Exits 0 with JSON when all checks pass; exits 1 with JSON + error details on failure.

define('SKIM_ROOT', dirname(__DIR__));
require SKIM_ROOT . '/vendor/autoload.php';

// Isolate from any previously-loaded config/env state (mirrors Pest bootstrap).
\skim\core\config::reset();
\skim\core\env::reset();

use skim\core\app;
use skim\core\request;
use skim\core\response;
use skim\events\event;
use skim\view\view;
use skim\view\component_collector;

$results = [
    'isolation' => false,
    'memory_growth' => false,
    'post_error' => false,
    'event_accumulation' => false,
];
$errors = [];

function boot_app(): app {
    $app = app::test_instance([
        'app.debug' => false,
        'app.view.default_layout' => null,
    ]);
    $app->router->get('/ping', fn(): array => ['ok' => true]);
    $app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));
    event::on('boot.ping', fn() => null);
    $app->boot();
    $app->boot_extensions();
    $app->freeze();
    event::capture_boot_snapshot();
    return $app;
}

function run_once(app $app, string $uri): void {
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

// --- 1. Isolation ---
try {
    $app = boot_app();
    run_once($app, '/ping');
    $app->set('user.name', 'alice');
    view::share('title', 'test');
    event::on('req.ping', fn() => null);
    run_once($app, '/ping');

    if ($app->get('user.name') !== null) {
        throw new \RuntimeException('user scope leaked');
    }
    if (view::get_shared('title') !== null) {
        throw new \RuntimeException('view shared data leaked');
    }
    if (event::listener_count('req.ping') !== 0) {
        throw new \RuntimeException('request listener leaked');
    }
    $results['isolation'] = true;
} catch (\Throwable $e) {
    $errors[] = 'isolation: ' . $e->getMessage();
}

// --- 2. Event accumulation ---
try {
    event::off();
    view::reset();
    component_collector::reset_request();

    $app = boot_app();
    $boot_baseline = event::listener_count('boot.ping');

    for ($i = 0; $i < 20; $i++) {
        run_once($app, '/ping');
        event::on('req.ping', fn() => null);
        run_once($app, '/ping');
    }

    if (event::listener_count('boot.ping') !== $boot_baseline) {
        throw new \RuntimeException('boot listener count changed from ' . $boot_baseline);
    }
    if (event::listener_count('req.ping') !== 0) {
        throw new \RuntimeException('request listeners accumulated: ' . event::listener_count('req.ping'));
    }
    $results['event_accumulation'] = true;
} catch (\Throwable $e) {
    $errors[] = 'event_accumulation: ' . $e->getMessage();
}

// --- 3. Post-error isolation ---
try {
    event::off();
    view::reset();
    component_collector::reset_request();

    $app = boot_app();
    $baseline_ob = ob_get_level();

    run_once($app, '/boom');
    run_once($app, '/ping');

    if ($app->get('user.name') !== null) {
        throw new \RuntimeException('user scope leaked after error');
    }
    if (component_collector::current() !== null) {
        throw new \RuntimeException('component_collector stack leaked after error');
    }
    if (ob_get_level() !== $baseline_ob) {
        throw new \RuntimeException('output buffer level mismatch after error');
    }
    $results['post_error'] = true;
} catch (\Throwable $e) {
    $errors[] = 'post_error: ' . $e->getMessage();
}

// --- 4. Memory growth ---
try {
    event::off();
    view::reset();
    component_collector::reset_request();

    $app = boot_app();

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
    if ($mb >= 1.0) {
        throw new \RuntimeException("memory grew {$mb} MB over 1000 requests");
    }
    $results['memory_growth'] = true;
} catch (\Throwable $e) {
    $errors[] = 'memory_growth: ' . $e->getMessage();
}

$output = ['correctness' => $results];
if ($errors !== []) {
    $output['errors'] = $errors;
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

$all_ok = !in_array(false, $results, true);
exit($all_ok ? 0 : 1);
