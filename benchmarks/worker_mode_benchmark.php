<?php declare(strict_types=1);

// Benchmarks SKIM in FrankenPHP-style worker mode locally.
// Spins up a small TCP server, accepts connections, and for each request
// runs App::beginRequest() → dispatch() → send() → endRequest() in a loop.
//
// Usage inside container:
//   docker compose exec app php benchmarks/worker_mode_benchmark.php [concurrency] [duration] [port]
//
// Defaults: concurrency=1, duration=5s, port=9999

define('SKIM_ROOT', dirname(__DIR__));
define('WORKER_MODE', true);

require SKIM_ROOT . '/vendor/autoload.php';

$concurrency = max(1, (int) ($argv[1] ?? 1));
$duration    = max(1, (int) ($argv[2] ?? 5));
$port        = (int) ($argv[3] ?? 9999);
$address     = "127.0.0.1:{$port}";

// Boot the app once, just like a worker process would.
$app = Skim\Core\App::instance();
$app->router->get('/bench', fn() => ['ok' => true, 'time' => microtime(true), 'id' => $_GET['id'] ?? null]);
$app->boot();
$app->bootExtensions();
$app->freeze();

// Create a listening socket.
$socket = @stream_socket_server("tcp://{$address}", $errno, $errstr);
if (!$socket) {
    fwrite(STDERR, "Cannot bind to {$address}: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($socket, false);

$clients    = [];
$requests   = 0;
$start_time = microtime(true);
$end_time   = $start_time + $duration;

echo "Worker mode benchmark: concurrency={$concurrency}, duration={$duration}s, port={$port}\n";

while (microtime(true) < $end_time || count($clients) > 0) {
    $now = microtime(true);

    // Accept new clients up to concurrency limit.
    while (count($clients) < $concurrency && $now < $end_time) {
        $conn = @stream_socket_accept($socket, 0);
        if ($conn === false) {
            break;
        }
        stream_set_blocking($conn, false);
        $clients[] = [
            'socket' => $conn,
            'state'  => 'reading',
            'buf'    => '',
        ];
    }

    foreach ($clients as $idx => &$client) {
        $conn = $client['socket'];

        if ($client['state'] === 'reading') {
            $data = @fread($conn, 4096);
            if ($data === false || $data === '') {
                if (!is_resource($conn) || feof($conn)) {
                    fclose($conn);
                    unset($clients[$idx]);
                }
                continue;
            }
            $client['buf'] .= $data;
            if (str_contains($client['buf'], "\r\n\r\n")) {
                $client['state'] = 'processing';
            }
        }

        if ($client['state'] === 'processing') {
            // Run the full worker request cycle.
            $app->beginRequest();

            $original_server = $_SERVER;
            $_SERVER = [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI'    => '/bench',
                'HTTP_HOST'      => 'localhost',
            ];
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];
            $_FILES = [];

            $req = Skim\Core\Request::fromGlobals();
            $res = new Skim\Core\Response();

            ob_start();
            try {
                $result = $app->dispatch($req, $res);
                $result->send();
            } finally {
                $body = ob_get_clean();
                $app->endRequest();
                $_SERVER = $original_server;
            }

            $status = $result->getStatus();
            $length = strlen($body);
            $reason = match ($status) {
                200 => 'OK',
                404 => 'Not Found',
                500 => 'Internal Server Error',
                default => '',
            };
            $headers = "HTTP/1.1 {$status} {$reason}\r\n";
            $headers .= "Content-Type: application/json\r\n";
            $headers .= "Content-Length: {$length}\r\n";
            $headers .= "Connection: keep-alive\r\n";
            $headers .= "\r\n";

            $client['response'] = $headers . $body;
            $client['state']     = 'writing';
            $requests++;
        }

        if ($client['state'] === 'writing') {
            $written = @fwrite($conn, $client['response']);
            if ($written === false) {
                fclose($conn);
                unset($clients[$idx]);
                continue;
            }
            $client['response'] = substr($client['response'], $written);
            if ($client['response'] === '') {
                // Keep connection alive for next request.
                $client['state'] = 'reading';
                // Preserve any pipelined data already in buffer.
                if (str_contains($client['buf'], "\r\n\r\n")) {
                    $client['state'] = 'processing';
                }
            }
        }
    }

    // Re-index client array.
    $clients = array_values($clients);

    // Avoid busy-spinning when idle.
    if (count($clients) === 0) {
        usleep(100);
    }
}

fclose($socket);

$elapsed = microtime(true) - $start_time;
$rps     = round($requests / $elapsed, 2);
$ms      = round(($elapsed / max(1, $requests)) * 1000, 3);

echo "\n";
echo "requests: {$requests}\n";
echo "elapsed:  " . round($elapsed, 3) . " s\n";
echo "RPS:      {$rps}\n";
echo "avg:      {$ms} ms/request\n";

$app->shutdown();
