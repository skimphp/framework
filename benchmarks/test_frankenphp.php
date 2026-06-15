<?php declare(strict_types=1);

// HTTP benchmark for FrankenPHP worker mode.
// Measures RPS over a fixed duration with response validation.

$url = $argv[1] ?? 'http://localhost:8081/bench';
$duration = (int) ($argv[2] ?? 5);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_TIMEOUT        => 2,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_NOSIGNAL       => true,
]);

$received = 0;
$errors = 0;
$start = microtime(true);
$end = $start + $duration;

while (microtime(true) < $end) {
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($code !== 200) {
        $errors++;
    } else {
        $json = json_decode($body, true);
        if ($json === null || !isset($json['ok']) || $json['ok'] !== true) {
            $errors++;
        }
    }
    $received++;
}

$elapsed = microtime(true) - $start;
$rps = round($received / $elapsed, 2);

echo "URL:      {$url}\n";
echo "Duration: {$duration}s\n";
echo "Received: {$received}\n";
echo "Errors:   {$errors}\n";
echo "RPS:      {$rps}\n";
