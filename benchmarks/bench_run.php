<?php declare(strict_types=1);

/**
 * Unified benchmark runner — executes all four SKIM benchmarks and persists
 * the aggregated results to benchmarks/results/<timestamp>.json.
 *
 * Captures for each run:
 *   - ISO-8601 UTC timestamp
 *   - git commit (short + full), branch, dirty flag
 *   - PHP version, SAPI
 *   - per-benchmark timing data + pass/fail against the documented target
 *
 * Usage:
 *   php benchmarks/bench_run.php                       # default 1000 reqs, 10 concurrent
 *   php benchmarks/bench_run.php [total] [concurrent]  # override HTTP request counts
 *
 * Existing benchmarks are reused as-is — http_warm.php is parsed from its
 * human output; http_hello.php / http_json.php output a __JSON__ line that
 * the runner captures directly.
 */

const SKIM_ROOT = null;
$root = dirname(__DIR__);
if (!defined('SKIM_ROOT')) {
    define('SKIM_ROOT', $root);
}

$total_requests = (int) ($argv[1] ?? 1000);
$concurrent     = (int) ($argv[2] ?? 10);
$base_url       = 'http://localhost:8080';

$results_dir = $root . '/benchmarks/results';
if (!is_dir($results_dir)) {
    mkdir($results_dir, 0o755, true);
}

$timestamp = gmdate('Y-m-d\TH-i-s\Z');
$git       = git_info($root);
$env       = [
    'php_version'  => PHP_VERSION,
    'sapi'         => PHP_SAPI,
    'opcache'      => function_exists('opcache_get_status') ? (bool) opcache_get_status(false) : false,
    'uname'        => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
];

$benchmarks = [];

// --- 1. boot_isolation.php ---
echo "==> boot_isolation\n";
$benchmarks['boot_isolation'] = run_bench_inproc(
    $root . '/benchmarks/boot_isolation.php',
    static function(string $stdout): array {
        if (!preg_match('/boot_isolation:\s+([\d.]+)\s+ms/', $stdout, $m)) {
            return ['ok' => false, 'error' => 'parse_failed', 'raw' => $stdout];
        }
        $ms  = (float) $m[1];
        return [
            'ms'           => $ms,
            'target_ms'    => 1.0,
            'ok'           => $ms <= 1.0,
        ];
    },
);

// --- 2. cache_hit.php ---
echo "==> cache_hit\n";
$benchmarks['cache_hit'] = run_bench_inproc(
    $root . '/benchmarks/cache_hit.php',
    static function(string $stdout): array {
        if (!preg_match('/cache_hit:\s+([\d.]+)\s+ms per call/', $stdout, $m)) {
            return ['ok' => false, 'error' => 'parse_failed', 'raw' => $stdout];
        }
        $ms = (float) $m[1];
        return [
            'ms_per_call'  => $ms,
            'target_ms'    => 0.05,
            'ok'           => $ms <= 0.05,
        ];
    },
);

// --- 3. http_warm.php (existing — 404 route) ---
echo "==> http_warm\n";
$benchmarks['http_warm'] = run_bench_cli(
    $root . '/benchmarks/http_warm.php',
    [$base_url . '/', (string) $total_requests, (string) $concurrent],
    static function(string $stdout): array {
        $r = [
            'name'          => 'http_warm',
            'avg_ms'        => null,
            'p95_ms'        => null,
            'rps'           => null,
            'requests'      => null,
            'errors'        => null,
            'total_s'       => null,
            'target_avg_ms' => 10.0,
            'ok'            => false,
        ];
        if (preg_match('/avg:\s+([\d.]+)\s+ms\s*\|\s*p95:\s+([\d.]+)\s+ms\s*\|\s*rps:\s+([\d,.]+)/', $stdout, $m)) {
            $r['avg_ms'] = (float) $m[1];
            $r['p95_ms'] = (float) $m[2];
            $r['rps']    = (float) str_replace(',', '', $m[3]);
        }
        if (preg_match('/requests:\s+(\d+)\s+ok,\s+(\d+)\s+errors,\s+([\d.]+)s\s+total/', $stdout, $m)) {
            $r['requests'] = (int) $m[1];
            $r['errors']   = (int) $m[2];
            $r['total_s']  = (float) $m[3];
        }
        if ($r['avg_ms'] !== null) {
            $r['ok'] = $r['avg_ms'] <= $r['target_avg_ms'];
        }
        return $r;
    },
);

// --- 4. http_hello.php (Hello World closure) ---
echo "==> http_hello\n";
$benchmarks['http_hello'] = run_bench_cli(
    $root . '/benchmarks/http_hello.php',
    [$base_url . '/', (string) $total_requests, (string) $concurrent],
    static function(string $stdout): array {
        return parse_json_line($stdout, 'http_hello') ?? [
            'name'          => 'http_hello',
            'ok'            => false,
            'error'         => 'no_json_line',
        ];
    },
);

// --- 5. http_json.php (JSON closure) ---
echo "==> http_json\n";
$benchmarks['http_json'] = run_bench_cli(
    $root . '/benchmarks/http_json.php',
    [$base_url . '/json', (string) $total_requests, (string) $concurrent],
    static function(string $stdout): array {
        return parse_json_line($stdout, 'http_json') ?? [
            'name'          => 'http_json',
            'ok'            => false,
            'error'         => 'no_json_line',
        ];
    },
);

// --- 6. http_hello.php @ concurrency=1 (sequential floor + max single-worker RPS) ---
// At c=1, avg_ms is the per-request floor and rps = 1000/avg_ms is the
// theoretical max throughput a single PHP process can sustain.
echo "==> http_hello_1c\n";
$benchmarks['http_hello_1c'] = run_bench_cli(
    $root . '/benchmarks/http_hello.php',
    [$base_url . '/', (string) $total_requests, '1'],
    static function(string $stdout): array {
        $r = parse_json_line($stdout, 'http_hello') ?? [
            'name'  => 'http_hello_1c',
            'ok'    => false,
            'error' => 'no_json_line',
        ];
        $r['name']           = 'http_hello_1c';
        $r['target_avg_ms']  = 2.0;
        $r['concurrency']    = 1;
        if (isset($r['avg_ms'])) {
            $r['single_worker_max_rps'] = (int) round(1000.0 / max($r['avg_ms'], 0.001));
            $r['ok'] = $r['avg_ms'] <= $r['target_avg_ms'];
        }
        return $r;
    },
);

// --- 7. http_json.php @ concurrency=1 ---
echo "==> http_json_1c\n";
$benchmarks['http_json_1c'] = run_bench_cli(
    $root . '/benchmarks/http_json.php',
    [$base_url . '/json', (string) $total_requests, '1'],
    static function(string $stdout): array {
        $r = parse_json_line($stdout, 'http_json') ?? [
            'name'  => 'http_json_1c',
            'ok'    => false,
            'error' => 'no_json_line',
        ];
        $r['name']           = 'http_json_1c';
        $r['target_avg_ms']  = 7.0;
        $r['concurrency']    = 1;
        if (isset($r['avg_ms'])) {
            $r['single_worker_max_rps'] = (int) round(1000.0 / max($r['avg_ms'], 0.001));
            $r['ok'] = $r['avg_ms'] <= $r['target_avg_ms'];
        }
        return $r;
    },
);

// --- aggregate & write ---
$summary = [
    'all_ok' => !in_array(false, array_map(static fn(array $b): bool => $b['ok'] ?? false, $benchmarks), true),
];

$record = [
    'timestamp'  => $timestamp,
    'git'        => $git,
    'env'        => $env,
    'config'     => [
        'total_requests'       => $total_requests,
        'concurrent'           => $concurrent,
        'concurrent_single'    => 1,
        'base_url'             => $base_url,
    ],
    'benchmarks' => $benchmarks,
    'summary'    => $summary,
];

$latest_path = $results_dir . '/latest.json';
$archive_path = $results_dir . '/' . $timestamp . '.json';

file_put_contents($archive_path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($latest_path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

append_history($results_dir . '/history.jsonl', $record);

// --- print human summary ---
echo "\n";
echo str_repeat('=', 60) . "\n";
echo "SKIM Benchmark — {$timestamp} — commit {$git['short']}\n";
echo str_repeat('=', 60) . "\n";
foreach ($benchmarks as $name => $b) {
    $status = ($b['ok'] ?? false) ? '  OK ' : 'FAIL ';
    $line   = $status . str_pad($name, 18);
    foreach (['ms', 'ms_per_call', 'avg_ms'] as $key) {
        if (isset($b[$key])) {
            $line .= sprintf('%8.3f ms', $b[$key]);
            break;
        }
    }
    if (isset($b['rps'])) {
        $line .= sprintf('  %7.1f rps', $b['rps']);
    }
    echo $line . "\n";
}

echo "\nSingle-worker max RPS (c=1, sequential floor):\n";
foreach (['http_hello_1c', 'http_json_1c'] as $name) {
    $b = $benchmarks[$name] ?? null;
    if ($b === null) {
        continue;
    }
    $avg    = $b['avg_ms'] ?? null;
    $rps    = $b['single_worker_max_rps'] ?? null;
    $target = $b['target_avg_ms'] ?? null;
    if ($avg !== null && $rps !== null) {
        printf("  %-18s avg %6.3f ms  ->  %4d rps/worker  (target: %.1f ms)\n", $name, $avg, $rps, $target);
    }
}
echo "\nResults written:\n  {$archive_path}\n  {$latest_path}\n  {$results_dir}/history.jsonl\n";

exit($summary['all_ok'] ? 0 : 1);

// ============================================================================
// helpers
// ============================================================================

function run_bench_inproc(string $script, callable $parse): array {
    $stdout = [];
    $rc     = 0;
    exec(sprintf('php %s 2>&1', escapeshellarg($script)), $stdout, $rc);
    $out = implode("\n", $stdout);
    echo $out . "\n";
    $result           = $parse($out);
    $result['exit']   = $rc;
    return $result;
}

function run_bench_cli(string $script, array $args, callable $parse): array {
    $cmd = 'BENCH_JSON=1 php ' . escapeshellarg($script);
    foreach ($args as $a) {
        $cmd .= ' ' . escapeshellarg((string) $a);
    }
    $stdout = [];
    $rc     = 0;
    exec($cmd . ' 2>&1', $stdout, $rc);
    $out = implode("\n", $stdout);
    echo $out . "\n";
    $result           = $parse($out);
    $result['exit']   = $rc;
    return $result;
}

function parse_json_line(string $stdout, string $expected_name): ?array {
    foreach (explode("\n", $stdout) as $line) {
        if (str_starts_with($line, '__JSON__ ')) {
            $payload = json_decode(substr($line, 9), true);
            if (is_array($payload) && ($payload['name'] ?? null) === $expected_name) {
                return $payload;
            }
        }
    }
    return null;
}

function git_info(string $cwd): array {
    $info = [
        'commit'  => null,
        'short'   => null,
        'branch'  => null,
        'dirty'   => false,
        'subject' => null,
    ];
    if (!is_dir($cwd . '/.git')) {
        return $info;
    }
    $info['commit'] = trim((string) shell_exec('git -C ' . escapeshellarg($cwd) . ' rev-parse HEAD 2>/dev/null'));
    $info['short']  = trim((string) shell_exec('git -C ' . escapeshellarg($cwd) . ' rev-parse --short HEAD 2>/dev/null'));
    $info['branch'] = trim((string) shell_exec('git -C ' . escapeshellarg($cwd) . ' rev-parse --abbrev-ref HEAD 2>/dev/null'));
    $info['subject']= trim((string) shell_exec('git -C ' . escapeshellarg($cwd) . ' log -1 --pretty=%s 2>/dev/null'));
    $dirty          = trim((string) shell_exec('git -C ' . escapeshellarg($cwd) . ' status --porcelain 2>/dev/null'));
    $info['dirty']  = $dirty !== '';
    return $info;
}

function append_history(string $path, array $record): void {
    $flat = [
        'timestamp'    => $record['timestamp'],
        'commit'       => $record['git']['short'],
        'branch'       => $record['git']['branch'],
        'all_ok'       => $record['summary']['all_ok'],
    ];
    foreach ($record['benchmarks'] as $name => $b) {
        foreach (['ms', 'ms_per_call', 'avg_ms'] as $key) {
            if (isset($b[$key])) {
                $flat[$name] = $b[$key];
                break;
            }
        }
    }
    file_put_contents($path, json_encode($flat, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}
