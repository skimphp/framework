<?php declare(strict_types=1);

// Parallel HTTP benchmark for FrankenPHP worker mode.
// Spawns N concurrent curl clients to measure real throughput.

$url = $argv[1] ?? 'http://localhost:8081/bench';
$concurrency = (int) ($argv[2] ?? 4);
$duration = (int) ($argv[3] ?? 5);

$pids = [];
$results = [];
$pipe_dir = sys_get_temp_dir() . '/frankenphp_bench_' . getmypid();
@mkdir($pipe_dir, 0777, true);

for ($i = 0; $i < $concurrency; $i++) {
    $pipe = $pipe_dir . '/result_' . $i . '.json';
    $cmd = sprintf(
        'php -r \'
        $ch = curl_init("%s");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_NOSIGNAL => true,
        ]);
        $ok = 0; $err = 0; $n = 0;
        $end = microtime(true) + %d;
        while (microtime(true) < $end) {
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code === 200) {
                $json = json_decode($body, true);
                if ($json && isset($json["ok"]) && $json["ok"] === true) $ok++;
                else $err++;
            } else {
                $err++;
            }
            $n++;
        }
        file_put_contents("%s", json_encode(["ok" => $ok, "err" => $err, "total" => $n]));
        \' > /dev/null 2>&1 &',
        $url,
        $duration,
        $pipe
    );
    exec($cmd);
    $pids[$i] = $pipe;
}

sleep($duration + 1);

$total_ok = 0;
$total_err = 0;
$total_req = 0;

foreach ($pids as $pipe) {
    if (is_file($pipe)) {
        $data = json_decode(file_get_contents($pipe), true);
        $total_ok += $data['ok'];
        $total_err += $data['err'];
        $total_req += $data['total'];
        unlink($pipe);
    }
}

@rmdir($pipe_dir);

$rps = round($total_req / $duration, 2);

echo "URL:         {$url}\n";
echo "Workers:     {$concurrency}\n";
echo "Duration:    {$duration}s\n";
echo "Total:       {$total_req}\n";
echo "OK:          {$total_ok}\n";
echo "Errors:      {$total_err}\n";
echo "RPS:         {$rps}\n";
