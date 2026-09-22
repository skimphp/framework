<?php declare(strict_types=1);

// FrankenPHP worker entry point.
// A single process handles many requests; boot and freeze happen once,
// then beginRequest / dispatch / endRequest run per request via frankenphp_handle_request().
define('SKIM_ROOT', dirname(__DIR__));
define('WORKER_MODE', true);

require SKIM_ROOT . '/vendor/autoload.php';

$app = Skim\Core\App::instance();

// --- global middleware ---
$app->use(Skim\Middleware\Cors::class);

if (Skim\Core\Config::get('app.debug', false)) {
    $app->use(Skim\Middleware\ToolbarMiddleware::class);
}

// --- routes ---
require SKIM_ROOT . '/routes.php';

// One-time boot
$app->boot();
$app->bootExtensions();
$app->freeze();

Skim\Events\Event::captureBootSnapshot();

// Configure leak detector: explicit config wins; null defaults to warn in
// worker+debug, off otherwise.
$leak_mode = $app->get('app.leak_detection');
if ($leak_mode === null) {
    $leak_mode = (defined('WORKER_MODE') && $app->isDebugMode()) ? 'warn' : 'off';
}
Skim\Worker\LeakDetector::configure((string) $leak_mode);

set_exception_handler(function (\Throwable $e) use ($app): void {
    $app->handleException($e);
});

$shutdown_requested = false;

if (extension_loaded('pcntl')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGTERM, function () use (&$shutdown_requested): void {
        $shutdown_requested = true;
    });
    pcntl_signal(SIGINT, function () use (&$shutdown_requested): void {
        $shutdown_requested = true;
    });
}

$handler = function () use ($app): void {
    $app->beginRequest();

    $req = Skim\Core\Request::fromGlobals();
    $res = new Skim\Core\Response();

    try {
        $result = $app->dispatch($req, $res);

        if ($app->isDebugMode() && $result instanceof Skim\Core\Response) {
            $trace = Skim\Dev\RequestTrace::finish($result->getStatus());
        }

        $app->emit($result, $res);
    } catch (\Throwable $e) {
        $app->handleException($e);
    } finally {
        $app->endRequest();
        gc_collect_cycles();
    }
};

$max_requests = (int) ($_ENV['WORKER_MAX_REQUESTS'] ?? 0);
$count = 0;

while (($count < $max_requests || $max_requests === 0) && !$shutdown_requested) {
    $keepRunning = frankenphp_handle_request($handler);
    if (!$keepRunning) {
        break;
    }
    $count++;
}

$app->shutdown();
