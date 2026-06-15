<?php declare(strict_types=1);

// Simulates a worker loop in-process to verify cross-request isolation
// and memory stability without FrankenPHP.

define('SKIM_ROOT', dirname(__DIR__));

require SKIM_ROOT . '/vendor/autoload.php';

// Isolate from any previously-loaded config/env state (mirrors Pest bootstrap).
\skim\core\config::reset();
\skim\core\env::reset();

$app = skim\core\app::test_instance([
    'app.debug' => false,
    'app.view.default_layout' => null,
]);

// Register routes so dispatch has something to do.
$app->router->get('/bench', fn() => 'ok');
$app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));

// Boot-time listener that MUST survive every reset.
skim\events\event::on('boot.ping', fn() => null);

$app->boot();
$app->boot_extensions();
$app->freeze();

// Mirror public/worker.php — REQUIRED for event-accumulation assertions.
skim\events\event::capture_boot_snapshot();

$initial = memory_get_usage(true);
$peak_delta = 0;
$boot_listener_baseline = skim\events\event::listener_count('boot.ping');

for ($i = 0; $i < 100; $i++) {
    $app->begin_request();

    // Simulate request-scoped mutation.
    $app->set('user.name', 'alice-' . $i);
    \skim\view\view::share('title', 'test-' . $i);
    \skim\events\event::on('req.ping', fn() => null);

    $uri = ($i === 50) ? '/boom' : '/bench';
    $req = skim\core\request::make('GET', $uri);
    $res = new skim\core\response();

    try {
        $app->dispatch($req, $res);
    } catch (\Throwable $e) {
        ob_start();
        $app->handle_exception($e);
        ob_end_clean();
    } finally {
        $app->end_request();
    }

    // Verify reset.
    if ($app->get('user.name') !== null) {
        fwrite(STDERR, "FAIL: user scope leaked at iteration {$i}\n");
        exit(1);
    }
    if (\skim\view\view::get_shared('title') !== null) {
        fwrite(STDERR, "FAIL: view shared data leaked at iteration {$i}\n");
        exit(1);
    }
    if (\skim\events\event::listener_count('boot.ping') !== $boot_listener_baseline) {
        fwrite(STDERR, "FAIL: boot listener count changed at iteration {$i}\n");
        exit(1);
    }
    if (\skim\events\event::listener_count('req.ping') !== 0) {
        fwrite(STDERR, "FAIL: request listener leaked at iteration {$i}\n");
        exit(1);
    }
    if (\skim\view\component_collector::current() !== null) {
        fwrite(STDERR, "FAIL: component_collector stack leaked at iteration {$i}\n");
        exit(1);
    }

    $delta = memory_get_usage(true) - $initial;
    $peak_delta = max($peak_delta, $delta);
}

$mb = $peak_delta / 1024 / 1024;
echo "peak memory delta: {$mb} MB\n";

if ($mb > 1.0) {
    fwrite(STDERR, "FAIL: memory grew > 1MB over 100 requests\n");
    exit(1);
}

echo "OK\n";
