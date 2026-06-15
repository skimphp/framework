<?php declare(strict_types=1);

// Measure RPS over fixed time with validation.

$port = (int) ($argv[1] ?? 9999);
$duration = (int) ($argv[2] ?? 1);
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
$errors = 0;
$start = microtime(true);
$end = $start + $duration;

while (microtime(true) < $end) {
    if ($sent === $received) {
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
            $body = substr($rest, 0, $body_len);
            $buf = substr($rest, $body_len);
            $received++;

            if (!str_starts_with($header, 'HTTP/1.1 200')) {
                $errors++;
            } else {
                $json = json_decode($body, true);
                if ($json === null || !isset($json['ok']) || $json['ok'] !== true) {
                    $errors++;
                }
            }
        } else {
            break;
        }
    }
}

fclose($sock);

echo "Sent: {$sent}, Received: {$received}, Errors: {$errors}\n";
echo "RPS: " . round($received / $duration, 2) . "\n";
