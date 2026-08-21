<?php declare(strict_types=1);

/**
 * HTTP JSON benchmark — full framework stack over real HTTP, JSON response.
 *
 * Hits a running SKIM dev server at /json with curl_multi concurrent requests,
 * measures end-to-end latency (request -> PHP boot -> router -> controller ->
 * JSON encode -> send). Targets < 7ms average per request.
 *
 * Usage:
 *   php benchmarks/http_json.php [url] [total_requests] [concurrent]
 *   env BENCH_JSON=1 php benchmarks/http_json.php ...
 *
 * Output:
 *   Human-readable summary, then on its own line:
 *     __JSON__ {"name":"http_json",...}
 *   when BENCH_JSON=1 is set.
 *
 * Exit code 0 when avg latency <= target, 1 otherwise.
 */

$url            = $argv[1] ?? 'http://localhost:8080/json';
$total_requests = (int) ($argv[2] ?? 1000);
$concurrent     = (int) ($argv[3] ?? 10);
$target_avg_ms  = 7.0;

$latencies = [];
$errors    = 0;

$overall_start = hrtime(true);

$run_batch = function(int $size) use ($url, &$latencies, &$errors): void {
    $multi   = curl_multi_init();
    $handles = [];

    for ($i = 0; $i < $size; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[] = $ch;
    }

    $running = 0;
    do {
        curl_multi_exec($multi, $running);
        if ($running > 0) {
            curl_multi_select($multi, 1.0);
        }
    } while ($running > 0);

    foreach ($handles as $ch) {
        $info    = curl_getinfo($ch);
        $code    = $info['http_code'];
        $latency = $info['total_time'] * 1000;

        if ($code >= 200 && $code < 500) {
            $latencies[] = $latency;
        } else {
            $errors++;
        }

        curl_multi_remove_handle($multi, $ch);
    }

    curl_multi_close($multi);
};

$batches   = intdiv($total_requests, $concurrent);
$remainder = $total_requests % $concurrent;

for ($b = 0; $b < $batches; $b++) {
    $run_batch($concurrent);
}
if ($remainder > 0) {
    $run_batch($remainder);
}

$overall_end = (hrtime(true) - $overall_start) / 1e9;

if (empty($latencies)) {
    fwrite(STDERR, "http_json: FAILED — no successful requests (errors: {$errors})\n");
    fwrite(STDERR, "Is the server running at {$url}?\n");
    exit(1);
}

sort($latencies);
$count = count($latencies);
$sum   = array_sum($latencies);
$avg   = $sum / $count;
$p95   = $latencies[(int) ceil($count * 0.95) - 1];
$rps   = $count / $overall_end;
$ok    = $avg <= $target_avg_ms;

echo "http_json: avg: " . number_format($avg, 2) . " ms | p95: " . number_format($p95, 2) . " ms | rps: " . number_format($rps, 1) . "\n";
echo "  requests: {$count} ok, {$errors} errors, " . number_format($overall_end, 2) . "s total | target: {$target_avg_ms} ms\n";

if (getenv('BENCH_JSON') === '1') {
    $payload = [
        'name'           => 'http_json',
        'url'            => $url,
        'requests'       => $count,
        'errors'         => $errors,
        'total_s'        => round($overall_end, 4),
        'avg_ms'         => round($avg, 3),
        'p95_ms'         => round($p95, 3),
        'rps'            => round($rps, 1),
        'target_avg_ms'  => $target_avg_ms,
        'ok'             => $ok,
    ];
    echo '__JSON__ ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
}

if (!$ok) {
    echo "WARNING: avg latency exceeds {$target_avg_ms}ms target\n";
    exit(1);
}
