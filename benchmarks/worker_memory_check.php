<?php declare(strict_types=1);

// Simulates a worker loop in-process to verify cross-request isolation
// and memory stability without FrankenPHP.

define('SKIM_ROOT', dirname(__DIR__));

require SKIM_ROOT . '/vendor/autoload.php';

$app = skim\core\app::test_instance([
    'app.debug' => false,
    'app.view.default_layout' => null,
]);

// Register a test route so dispatch has something to do.
$app->router->get('/bench', fn() => 'ok');
$app->boot();
$app->boot_extensions();
$app->freeze();

$initial = memory_get_usage(true);
$peak_delta = 0;

for ($i = 0; $i < 100; $i++) {
    $app->begin_request();

    // Simulate request-scoped mutation.
    $app->set('user.name', 'alice-' . $i);
    \skim\view\view::share('title', 'test-' . $i);
    \skim\events\event::on('ping', fn() => null);

    $req = skim\core\request::make('GET', '/bench');
    $res = $app->dispatch($req, new skim\core\response());

    $app->end_request();

    // Verify reset.
    if ($app->get('user.name') !== null) {
        fwrite(STDERR, "FAIL: user scope leaked at iteration {$i}\n");
        exit(1);
    }
    if (\skim\view\view::get_shared('title') !== null) {
        fwrite(STDERR, "FAIL: view shared data leaked at iteration {$i}\n");
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
