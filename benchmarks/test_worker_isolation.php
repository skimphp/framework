<?php declare(strict_types=1);

// Test cross-request isolation in worker mode.
// Send requests with different query params and verify responses are isolated.

$port = (int) ($argv[1] ?? 9999);
$address = "127.0.0.1:{$port}";

$sock = @stream_socket_client("tcp://{$address}", $errno, $errstr, 2);
if (!$sock) {
    fwrite(STDERR, "Cannot connect: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($sock, false);

function makeRequest($id) {
    return "GET /bench?id={$id} HTTP/1.1\r\nHost: localhost\r\nConnection: keep-alive\r\n\r\n";
}

$buf = '';
$received = 0;
$sent = 0;
$errors = 0;
$expected_ids = [];

while ($received < 20) {
    if ($sent < 20 && $sent === $received) {
        $req = makeRequest($sent + 1);
        $expected_ids[$sent + 1] = true;
        @fwrite($sock, $req);
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

            $expected_id = $received;
            $json = json_decode($body, true);
            if ($json === null || !isset($json['ok']) || $json['ok'] !== true || !isset($json['id']) || $json['id'] != $expected_id) {
                echo "[{$received}] BAD BODY: {$body}\n";
                $errors++;
            } else {
                echo "[{$received}] OK (id={$json['id']})\n";
            }
        } else {
            break;
        }
    }
}

fclose($sock);

echo "\nSent: {$sent}, Received: {$received}, Errors: {$errors}\n";
if ($errors > 0) exit(1);
echo "Isolation test passed.\n";
