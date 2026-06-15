<?php declare(strict_types=1);

// Send exactly N requests and verify each response has status 200 and correct JSON.

$port = (int) ($argv[1] ?? 9999);
$count = (int) ($argv[2] ?? 10);
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
$errors = 0;
$sent = 0;

while ($sent < $count || $received < $count) {
    if ($sent < $count && $sent === $received) {
        @fwrite($sock, $request);
        $sent++;
    }

    $data = @fread($sock, 4096);
    if ($data !== false && $data !== '') {
        $buf .= $data;
    }

    // Parse complete HTTP responses
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

            // Verify status
            if (!str_starts_with($header, 'HTTP/1.1 200')) {
                echo "[{$received}] BAD STATUS: " . explode("\r\n", $header)[0] . "\n";
                $errors++;
                continue;
            }

            // Verify JSON
            $json = json_decode($body, true);
            if ($json === null || !isset($json['ok']) || $json['ok'] !== true || !isset($json['time'])) {
                echo "[{$received}] BAD BODY: {$body}\n";
                $errors++;
                continue;
            }

            echo "[{$received}] OK: " . json_encode($json) . "\n";
        } else {
            break;
        }
    }

    usleep(100);
}

fclose($sock);

echo "\nSent: {$sent}, Received: {$received}, Errors: {$errors}\n";
if ($errors > 0) exit(1);
echo "All responses valid.\n";
