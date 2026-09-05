<?php declare(strict_types=1);

/**
 * Unified benchmark runner — correctness gate + performance benchmarks.
 *
 * Splits results into two sections:
 *   - correctness: hard pass/fail (isolation, memory, post-error, events)
 *   - performance: recorded values + regression-warn, never a hard CI fail
 *
 * Usage:
 *   php benchmarks/bench_run.php                       # default 1000 reqs, 10 concurrent
 *   php benchmarks/bench_run.php [total] [concurrent]  # override HTTP request counts
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
$git       = gitInfo($root);
$env       = [
    'php_version'  => PHP_VERSION,
    'sapi'         => PHP_SAPI,
    'opcache'      => function_exists('opcache_get_status') ? (bool) opcache_get_status(false) : false,
    'uname'        => php_uname('s') . ' ' . php_uname('r') . ' ' . php_uname('m'),
];

// ========================================================================
// Correctness gate (hard pass/fail)
// ========================================================================
echo "==> correctness_gate\n";
$correctness = runBenchInproc(
    $root . '/benchmarks/correctness_gate.php',
    static function(string $stdout): array {
        $json = json_decode($stdout, true);
        if (!is_array($json) || !isset($json['correctness'])) {
            return [
                'isolation' => false,
                'memory_growth' => false,
                'post_error' => false,
                'event_accumulation' => false,
                'error' => 'parse_failed',
                'raw' => $stdout,
            ];
        }
        return $json['correctness'];
    },
);

$correctness_ok = !in_array(false, $correctness, true);

// ========================================================================
// Performance benchmarks (recorded, warn-only)
// ========================================================================
$performance = [];

// --- 1. boot_isolation.php ---
echo "==> boot_isolation\n";
$performance['boot_isolation'] = runBenchInproc(
    $root . '/benchmarks/boot_isolation.php',
    static function(string $stdout): array {
        if (!preg_match('/boot_isolation:\s+([\d.]+)\s+ms/', $stdout, $m)) {
            return ['error' => 'parse_failed', 'raw' => $stdout];
        }
        return ['ms' => (float) $m[1]];
    },
);

// --- 2. cache_hit.php ---
echo "==> cache_hit\n";
$performance['cache_hit'] = runBenchInproc(
    $root . '/benchmarks/cache_hit.php',
    static function(string $stdout): array {
        if (!preg_match('/cache_hit:\s+([\d.]+)\s+ms per call/', $stdout, $m)) {
            return ['error' => 'parse_failed', 'raw' => $stdout];
        }
        return ['ms_per_call' => (float) $m[1]];
    },
);

// --- 3. http_warm.php (existing — 404 route) ---
echo "==> http_warm\n";
$performance['http_warm'] = runBenchCli(
    $root . '/benchmarks/http_warm.php',
    [$base_url . '/', (string) $total_requests, (string) $concurrent],
    static function(string $stdout): array {
        $r = ['avg_ms' => null, 'p95_ms' => null, 'rps' => null, 'requests' => null, 'errors' => null, 'total_s' => null];
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
        return $r;
    },
);

// --- 4. http_hello.php (Hello World closure) ---
echo "==> http_hello\n";
$performance['http_hello'] = runBenchCli(
    $root . '/benchmarks/http_hello.php',
    [$base_url . '/', (string) $total_requests, (string) $concurrent],
    static function(string $stdout): array {
        return parseJsonLine($stdout, 'http_hello') ?? ['error' => 'no_json_line'];
    },
);

// --- 5. http_json.php (JSON closure) ---
echo "==> http_json\n";
$performance['http_json'] = runBenchCli(
    $root . '/benchmarks/http_json.php',
    [$base_url . '/json', (string) $total_requests, (string) $concurrent],
    static function(string $stdout): array {
        return parseJsonLine($stdout, 'http_json') ?? ['error' => 'no_json_line'];
    },
);

// --- 6. http_hello.php @ concurrency=1 ---
echo "==> http_hello_1c\n";
$performance['http_hello_1c'] = runBenchCli(
    $root . '/benchmarks/http_hello.php',
    [$base_url . '/', (string) $total_requests, '1'],
    static function(string $stdout): array {
        $r = parseJsonLine($stdout, 'http_hello') ?? ['error' => 'no_json_line'];
        $r['concurrency'] = 1;
        if (isset($r['avg_ms'])) {
            $r['single_worker_max_rps'] = (int) round(1000.0 / max($r['avg_ms'], 0.001));
        }
        return $r;
    },
);

// --- 7. http_json.php @ concurrency=1 ---
echo "==> http_json_1c\n";
$performance['http_json_1c'] = runBenchCli(
    $root . '/benchmarks/http_json.php',
    [$base_url . '/json', (string) $total_requests, '1'],
    static function(string $stdout): array {
        $r = parseJsonLine($stdout, 'http_json') ?? ['error' => 'no_json_line'];
        $r['concurrency'] = 1;
        if (isset($r['avg_ms'])) {
            $r['single_worker_max_rps'] = (int) round(1000.0 / max($r['avg_ms'], 0.001));
        }
        return $r;
    },
);

// ========================================================================
// Aggregate & write
// ========================================================================
$record = [
    'timestamp'   => $timestamp,
    'git'         => $git,
    'env'         => $env,
    'config'      => [
        'total_requests'    => $total_requests,
        'concurrent'      => $concurrent,
        'concurrent_single' => 1,
        'base_url'        => $base_url,
    ],
    'correctness' => $correctness,
    'performance' => $performance,
    'summary'     => [
        'correctness_ok' => $correctness_ok,
        'performance_ok' => null, // never a hard fail; regression-warn only
    ],
];

$latest_path = $results_dir . '/latest.json';
$archive_path = $results_dir . '/' . $timestamp . '.json';

file_put_contents($archive_path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
file_put_contents($latest_path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

appendHistory($results_dir . '/history.jsonl', $record);

// --- print human summary ---
echo "\n";
echo str_repeat('=', 60) . "\n";
echo "SKIM Benchmark — {$timestamp} — commit {$git['short']}\n";
echo str_repeat('=', 60) . "\n";

$cg_status = $correctness_ok ? '  OK ' : 'FAIL ';
echo "{$cg_status}correctness_gate\n";
foreach ($correctness as $name => $ok) {
    $s = $ok ? '  OK ' : 'FAIL ';
    echo "  {$s}{$name}\n";
}

echo "\n";
foreach ($performance as $name => $b) {
    if (isset($b['error'])) {
        echo "FAIL {$name}  ({$b['error']})\n";
        continue;
    }
    $line = '     ' . str_pad($name, 18);
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
    $b = $performance[$name] ?? [];
    if (isset($b['single_worker_max_rps'])) {
        printf("  %-18s %4d rps/worker\n", $name, $b['single_worker_max_rps']);
    }
}

echo "\nResults written:\n  {$archive_path}\n  {$latest_path}\n  {$results_dir}/history.jsonl\n";

exit($correctness_ok ? 0 : 1);

// ============================================================================
// helpers
// ============================================================================

function runBenchInproc(string $script, callable $parse, array $envVars = []): array {
    $stdout = [];
    $rc     = 0;
    $envPrefix = '';
    foreach ($envVars as $k => $v) {
        $envPrefix .= escapeshellarg($k) . '=' . escapeshellarg((string) $v) . ' ';
    }
    exec(sprintf('%sphp %s 2>&1', $envPrefix, escapeshellarg($script)), $stdout, $rc);
    $out = implode("\n", $stdout);
    echo $out . "\n";
    $result           = $parse($out);
    $result['exit']   = $rc;
    return $result;
}

function runBenchCli(string $script, array $args, callable $parse): array {
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

function parseJsonLine(string $stdout, string $expectedName): ?array {
    foreach (explode("\n", $stdout) as $line) {
        if (str_starts_with($line, '__JSON__ ')) {
            $payload = json_decode(substr($line, 9), true);
            if (is_array($payload) && ($payload['name'] ?? null) === $expectedName) {
                return $payload;
            }
        }
    }
    return null;
}

function gitInfo(string $cwd): array {
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

function appendHistory(string $path, array $record): void {
    $flat = [
        'timestamp'    => $record['timestamp'],
        'commit'       => $record['git']['short'],
        'branch'       => $record['git']['branch'],
        'correctness_ok' => $record['summary']['correctness_ok'],
    ];
    foreach ($record['performance'] as $name => $b) {
        foreach (['ms', 'ms_per_call', 'avg_ms'] as $key) {
            if (isset($b[$key])) {
                $flat[$name] = $b[$key];
                break;
            }
        }
    }
    file_put_contents($path, json_encode($flat, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}
