<?php declare(strict_types=1);

// Debug version of worker_mode_client.php to understand counting.

$port = (int) ($argv[1] ?? 9999);
$duration = 1; // 1 second
$address = "127.0.0.1:{$port}";

$request = "GET /bench HTTP/1.1\r\nHost: localhost\r\nConnection: keep-alive\r\n\r\n";

$sock = @stream_socket_client("tcp://{$address}", $errno, $errstr, 1);
if (!$sock) {
    fwrite(STDERR, "Cannot connect: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($sock, false);

$buf = '';
$sent = 0;
$received = 0;
$end = microtime(true) + $duration;

while (microtime(true) < $end) {
    if ($sent === $received) {
        $w = @fwrite($sock, $request);
        $sent++;
    }

    $data = @fread($sock, 4096);
    if ($data === false) {
        continue;
    }
    $buf .= $data;

    while (($pos = strpos($buf, "\r\n\r\n")) !== false) {
        $header = substr($buf, 0, $pos);
        $rest = substr($buf, $pos + 4);

        if (preg_match('/Content-Length:\s*(\d+)/i', $header, $m)) {
            $body_len = (int) $m[1];
            if (strlen($rest) >= $body_len) {
                $body = substr($rest, 0, $body_len);
                $buf = substr($rest, $body_len);
                $received++;
                echo "R{$received}: status=" . explode("\r\n", $header)[0] . " body_len=$body_len body=$body\n";
            } else {
                break;
            }
        } else {
            $buf = $rest;
            $received++;
            echo "R{$received}: no content-length\n";
        }
    }
}

echo "sent=$sent received=$received\n";
