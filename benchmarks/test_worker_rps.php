<?php declare(strict_types=1);

// Measure actual RPS by sending N requests sequentially and timing.

$port = (int) ($argv[1] ?? 9999);
$count = (int) ($argv[2] ?? 1000);
$address = "127.0.0.1:{$port}";

$request = "GET /bench HTTP/1.1\r\nHost: localhost\r\nConnection: keep-alive\r\n\r\n";

$sock = @stream_socket_client("tcp://{$address}", $errno, $errstr, 2);
if (!$sock) {
    fwrite(STDERR, "Cannot connect: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($sock, false);

$buf = '';
$received = 0;
$sent = 0;
$start = microtime(true);

while ($received < $count) {
    if ($sent < $count && $sent === $received) {
        @fwrite($sock, $request);
        $sent++;
    }

    $data = @fread($sock, 4096);
    if ($data !== false && $data !== '') {
        $buf .= $data;
    }

    while (($pos = strpos($buf, "\r\n\r\n")) !== false) {
        $header = substr($buf, 0, $pos);
        $rest = substr($buf, $pos + 4);

        $body_len = 0;
        if (preg_match('/Content-Length:\s*(\d+)/i', $header, $m)) {
            $body_len = (int) $m[1];
        }

        if (strlen($rest) >= $body_len) {
            $buf = substr($rest, $body_len);
            $received++;
        } else {
            break;
        }
    }

    usleep(10);
}

$elapsed = microtime(true) - $start;
$rps = round($received / $elapsed, 2);
$ms = round(($elapsed / $received) * 1000, 3);

fclose($sock);

echo "Sent: {$sent}, Received: {$received}\n";
echo "Elapsed: " . round($elapsed, 3) . " s\n";
echo "RPS: {$rps}\n";
echo "Avg: {$ms} ms/request\n";
