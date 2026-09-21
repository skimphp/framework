<?php declare(strict_types=1);

use Skim\Core\App as CoreApp;
use Skim\Core\Config;

/** @var CoreApp $app */

// Real application routes (controller-based — route-cache friendly).
$app->router->get('/', [\App\Controllers\HomeController::class, 'index']);

// --- demo / benchmark routes ---
// Closures disable production route-cache compilation, so they are gated behind
// debug mode. Set APP_DEBUG=true (the benchmark harness does) to expose
// /json, /bench, /boom and /__leaks.
if (Config::get('app.debug', false)) {
    $app->router->get('/json', function(): void {
        header('Content-Type: application/json');
        echo json_encode(['time' => microtime(true)]);
    });

    $app->router->get('/bench', function (): array {
        $sleep = (int) ($_GET['sleep'] ?? 0);
        if ($sleep > 0 && $sleep < 10000) {
            usleep($sleep * 1000);
        }
        return ['ok' => true, 'time' => microtime(true), 'sleep_ms' => $sleep, 'id' => $_GET['id'] ?? null];
    });

    $app->router->get('/boom', fn() => throw new \RuntimeException('handler blew up'));

    $app->router->get('/__leaks', fn(): array => [
        'findings' => Skim\Worker\LeakDetector::findings(),
        'count'    => count(Skim\Worker\LeakDetector::findings()),
    ]);
}
