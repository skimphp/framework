<?php declare(strict_types=1);

$url = $argv[1] ?? 'http://localhost:8080/';
$total_requests = (int) ($argv[2] ?? 1000);
$concurrent = (int) ($argv[3] ?? 10);

$latencies = [];
$errors = 0;

$overall_start = hrtime(true);

for ($batch = 0; $batch < intdiv($total_requests, $concurrent); $batch++) {
    $multi = curl_multi_init();
    $handles = [];

    for ($i = 0; $i < $concurrent; $i++) {
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
        $info = curl_getinfo($ch);
        $code = $info['http_code'];
        $latency = $info['total_time'] * 1000;

        if ($code >= 200 && $code < 500) {
            $latencies[] = $latency;
        } else {
            $errors++;
        }

        curl_multi_remove_handle($multi, $ch);
    }
}

$remainder = $total_requests % $concurrent;
if ($remainder > 0) {
    $multi = curl_multi_init();
    $handles = [];

    for ($i = 0; $i < $remainder; $i++) {
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
        $info = curl_getinfo($ch);
        $code = $info['http_code'];
        $latency = $info['total_time'] * 1000;

        if ($code >= 200 && $code < 500) {
            $latencies[] = $latency;
        } else {
            $errors++;
        }

        curl_multi_remove_handle($multi, $ch);
    }
}

$overall_end = (hrtime(true) - $overall_start) / 1e9;

if (empty($latencies)) {
    echo "http_warm: FAILED — no successful requests (errors: {$errors})\n";
    echo "Is the server running at {$url}?\n";
    exit(1);
}

sort($latencies);
$count = count($latencies);
$sum = array_sum($latencies);
$avg = $sum / $count;
$p95_index = (int) ceil($count * 0.95) - 1;
$p95 = $latencies[$p95_index];
$rps = $count / $overall_end;

echo "http_warm: avg: " . number_format($avg, 2) . " ms | p95: " . number_format($p95, 2) . " ms | rps: " . number_format($rps, 1) . "\n";
echo "  requests: {$count} ok, {$errors} errors, {$overall_end}s total\n";

if ($avg > 10.0) {
    echo "WARNING: avg latency exceeds 10ms target\n";
    exit(1);
}
