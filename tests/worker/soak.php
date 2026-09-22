<?php declare(strict_types=1);

// Soak test: drives a realistic multi-component workload through the worker
// loop and asserts the leak detector stays clean. This is the T2 complement to
// the correctness gate — it exercises view, validation, events, and routes.

define('SKIM_ROOT', dirname(__DIR__, 2));
require SKIM_ROOT . '/vendor/autoload.php';

// Isolate from any previously-loaded state.
\Skim\Core\Config::reset();
\Skim\Core\Env::reset();

use Skim\Core\App;
use Skim\Core\Request;
use Skim\Core\Response;
use Skim\Events\Event;
use Skim\View\View;
use Skim\View\ComponentCollector;
use Skim\Worker\LeakDetector;
use Skim\Worker\WorkerReset;

$app = App::testInstance([
    'app.debug' => false,
    'app.view.default_layout' => null,
    'app.leak_detection' => 'strict',
]);

// --- realistic routes ---
$app->router->get('/home', fn(): array => ['page' => 'home']);

$app->router->get('/user/@id:int', function(request $req, response $res, string $id): array {
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
        return (new Response())->status(422)->json(['errors' => $result->errors()]);
    }
    return ['sent' => true];
});

$app->router->get('/event', function(): array {
    Event::on('soak.demo', fn($data) => null);
    Event::emit('soak.demo', ['time' => microtime(true)]);
    return ['emitted' => true];
});

$app->router->get('/view', function(): array {
    View::share('app_name', 'SKIM');
    return ['view_shared' => true];
});

$app->router->get('/component', function(): array {
    $collector = new \Skim\View\ComponentCollector();
    ComponentCollector::push($collector);
    // simulate a component render that throws occasionally
    if (random_int(1, 100) === 1) {
        ComponentCollector::pop(); // balanced on happy path
        throw new \RuntimeException('render failed');
    }
    ComponentCollector::pop();
    return ['component' => true];
});

$app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));

// Boot-time listener (must survive)
Event::on('boot.warm', fn() => null);

$app->boot();
$app->bootExtensions();
$app->freeze();

Event::captureBootSnapshot();
LeakDetector::configure('strict');

$routes = ['/home', '/user/42', '/event', '/view', '/component'];
$post_routes = ['/contact'];

$errors = 0;
$error_log = [];
$start = microtime(true);

for ($i = 0; $i < 1000; $i++) {
    $app->beginRequest();

    try {
        $uri = $routes[$i % count($routes)];

        if ($i % 20 === 0) {
            // occasional POST with validation
            $req = Request::make('POST', '/contact', ['email' => 'test@example.com', 'message' => 'hello world']);
        } elseif ($i % 50 === 0) {
            // occasional error
            $req = Request::make('GET', '/boom');
        } else {
            $req = Request::make('GET', $uri . '?req=' . $i);
        }

        $app->dispatch($req, new Response());
    } catch (\Throwable $e) {
        ob_start();
        $app->handleException($e);
        ob_end_clean();
        $errors++;
        $key = get_class($e) . ': ' . $e->getMessage();
        $error_log[$key] = ($error_log[$key] ?? 0) + 1;
    } finally {
        $app->endRequest();
    }
}

$elapsed = microtime(true) - $start;
$findings = LeakDetector::findings();

$hard = array_filter($findings, fn($f) => str_starts_with($f['key'], 'hard.'));
$growth = array_filter($findings, fn($f) => str_starts_with($f['key'], 'growth.'));

echo "Soak test complete\n";
echo "  Requests: 1000\n";
echo "  Errors (expected): {$errors}\n";
echo "  Elapsed: " . round($elapsed, 2) . "s\n";
echo "  Findings: " . count($findings) . "\n";
echo "  Hard invariant findings: " . count($hard) . "\n";
echo "  Growth trend findings: " . count($growth) . "\n";

if ($hard !== []) {
    echo "\nHard invariant violations:\n";
    foreach ($hard as $f) {
        echo "  [{$f['key']}] {$f['message']}\n";
    }
}

if ($growth !== []) {
    echo "\nGrowth trend warnings:\n";
    foreach ($growth as $f) {
        echo "  [{$f['key']}] {$f['message']}\n";
    }
}

if ($hard !== []) {
    fwrite(STDERR, "FAIL: hard invariant violations detected\n");
    exit(1);
}

echo "\nError breakdown:\n";
foreach ($error_log as $msg => $count) {
    echo "  {$count}x {$msg}\n";
}

echo "\nOK\n";
