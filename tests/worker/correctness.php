<?php declare(strict_types=1);

// In-process correctness gate for worker mode.
// Exits 0 with JSON when all checks pass; exits 1 with JSON + error details on failure.

define('SKIM_ROOT', dirname(__DIR__, 2));
require SKIM_ROOT . '/vendor/autoload.php';

// Isolate from any previously-loaded config/env state (mirrors Pest bootstrap).
\Skim\Core\Config::reset();
\Skim\Core\Env::reset();

use Skim\Core\App;
use Skim\Core\Request;
use Skim\Core\Response;
use Skim\Events\Event;
use Skim\View\View;
use Skim\View\ComponentCollector;

$results = [
    'isolation' => false,
    'memory_growth' => false,
    'post_error' => false,
    'event_accumulation' => false,
];
$errors = [];

function bootApp(): app {
    $app = App::testInstance([
        'app.debug' => false,
        'app.view.default_layout' => null,
    ]);
    $app->router->get('/ping', fn(): array => ['ok' => true]);
    $app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));
    Event::on('boot.ping', fn() => null);
    $app->boot();
    $app->bootExtensions();
    $app->freeze();
    Event::captureBootSnapshot();
    return $app;
}

function runOnce(app $app, string $uri): void {
    $app->beginRequest();
    try {
        $app->dispatch(Request::make('GET', $uri), new Response());
    } catch (\Throwable $e) {
        ob_start();
        $app->handleException($e);
        ob_end_clean();
    } finally {
        $app->endRequest();
    }
}

// --- 1. Isolation ---
try {
    $app = bootApp();
    runOnce($app, '/ping');
    $app->set('user.name', 'alice');
    View::share('title', 'test');
    Event::on('req.ping', fn() => null);
    runOnce($app, '/ping');

    if ($app->get('user.name') !== null) {
        throw new \RuntimeException('user scope leaked');
    }
    if (View::getShared('title') !== null) {
        throw new \RuntimeException('view shared data leaked');
    }
    if (Event::listenerCount('req.ping') !== 0) {
        throw new \RuntimeException('request listener leaked');
    }
    $results['isolation'] = true;
} catch (\Throwable $e) {
    $errors[] = 'isolation: ' . $e->getMessage();
}

// --- 2. Event accumulation ---
try {
    Event::off();
    View::reset();
    ComponentCollector::resetRequest();

    $app = bootApp();
    $boot_baseline = Event::listenerCount('boot.ping');

    for ($i = 0; $i < 20; $i++) {
        runOnce($app, '/ping');
        Event::on('req.ping', fn() => null);
        runOnce($app, '/ping');
    }

    if (Event::listenerCount('boot.ping') !== $boot_baseline) {
        throw new \RuntimeException('boot listener count changed from ' . $boot_baseline);
    }
    if (Event::listenerCount('req.ping') !== 0) {
        throw new \RuntimeException('request listeners accumulated: ' . Event::listenerCount('req.ping'));
    }
    $results['event_accumulation'] = true;
} catch (\Throwable $e) {
    $errors[] = 'event_accumulation: ' . $e->getMessage();
}

// --- 3. Post-error isolation ---
try {
    Event::off();
    View::reset();
    ComponentCollector::resetRequest();

    $app = bootApp();
    $baseline_ob = ob_get_level();

    runOnce($app, '/boom');
    runOnce($app, '/ping');

    if ($app->get('user.name') !== null) {
        throw new \RuntimeException('user scope leaked after error');
    }
    if (ComponentCollector::current() !== null) {
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
    Event::off();
    View::reset();
    ComponentCollector::resetRequest();

    $app = bootApp();

    for ($i = 0; $i < 5; $i++) {
        runOnce($app, '/ping');
    }
    $baseline = memory_get_usage(true);
    $peak_delta = 0;

    for ($i = 0; $i < 1000; $i++) {
        runOnce($app, '/ping');
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
