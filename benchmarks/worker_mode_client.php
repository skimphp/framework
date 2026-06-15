<?php declare(strict_types=1);

// HTTP client for worker_mode_benchmark.php.
// Keeps persistent connections and pumps GET /bench requests.
//
// Usage:
//   docker compose exec app php benchmarks/worker_mode_client.php [clients] [duration] [port]

$clients_count = max(1, (int) ($argv[1] ?? 1));
$duration      = max(1, (int) ($argv[2] ?? 5));
$port          = (int) ($argv[3] ?? 9999);
$address       = "127.0.0.1:{$port}";

$request = "GET /bench HTTP/1.1\r\nHost: localhost\r\nConnection: keep-alive\r\n\r\n";
$sockets = [];
$stats   = [];

for ($i = 0; $i < $clients_count; $i++) {
    $sock = @stream_socket_client("tcp://{$address}", $errno, $errstr, 1);
    if (!$sock) {
        fwrite(STDERR, "Cannot connect to {$address}: {$errstr} ({$errno})\n");
        exit(1);
    }
    stream_set_blocking($sock, false);
    $sockets[$i] = $sock;
    $stats[$i]   = ['sent' => 0, 'received' => 0, 'buf' => ''];
}

$end = microtime(true) + $duration;
$total_received = 0;

while (microtime(true) < $end) {
    foreach ($sockets as $i => $sock) {
        // Send a request if we have nothing pending for this connection.
        if ($stats[$i]['sent'] === $stats[$i]['received']) {
            @fwrite($sock, $request);
            $stats[$i]['sent']++;
        }

        $data = @fread($sock, 4096);
        if ($data === false) {
            continue;
        }
        $stats[$i]['buf'] .= $data;

        // Count complete HTTP responses.
        while (($pos = strpos($stats[$i]['buf'], "\r\n\r\n")) !== false) {
            $header = substr($stats[$i]['buf'], 0, $pos);
            $rest   = substr($stats[$i]['buf'], $pos + 4);

            if (preg_match('/Content-Length:\s*(\d+)/i', $header, $m)) {
                $body_len = (int) $m[1];
                if (strlen($rest) >= $body_len) {
                    $stats[$i]['received']++;
                    $total_received++;
                    $stats[$i]['buf'] = substr($rest, $body_len);
                } else {
                    break;
                }
            } else {
                // No content-length, treat as complete.
                $stats[$i]['received']++;
                $total_received++;
                $stats[$i]['buf'] = $rest;
            }
        }
    }
}

foreach ($sockets as $sock) {
    fclose($sock);
}

echo "client received: {$total_received}\n";
