# SKIM Benchmarks — User Guide

Reproducible performance benchmarks for SKIM Framework. All scripts run inside
the Docker container — no local PHP install required.

---

## Quick Start

```bash
# 1. Start the dev server (already configured for benchmark mode)
docker compose up -d

# 2. Run the full suite — all five benchmarks, results saved as JSON
docker compose exec app php benchmarks/bench_run.php

# Optional: override request count and concurrency
docker compose exec app php benchmarks/bench_run.php 5000 20
```

The runner writes three files into `benchmarks/results/`:

- `latest.json` — most recent run (always overwritten)
- `<timestamp>.json` — timestamped archive of each run
- `history.jsonl` — one line per run, perfect for `tail -f` or plotting

Each archive contains the full record: timestamp, git commit (short + full +
branch + dirty flag), PHP/SAPI/uname, per-benchmark timings, and per-benchmark
`ok` flag against the target.

---

## The Five Benchmarks

### 1. `boot_isolation.php` — `App::instance()` overhead

Measures the time to create the application singleton (autoload + container
construction, no request dispatched). **Target: < 1 ms on bare metal**;
2-3 ms is expected in Docker with bind-mount filesystem overhead.

```bash
docker compose exec app php benchmarks/boot_isolation.php
```

### 2. `cache_hit.php` — `Env::get()` + `Config::get()` per call

Runs 10,000 iterations of `Env::get('APP_NAME')` and `Config::get('app.name')`
with the compiled cache. **Target: < 0.05 ms per call**. Pure OPcache memory
hit once warm.

```bash
docker compose exec app php benchmarks/cache_hit.php
```

### 3. `http_warm.php` — HTTP throughput on an unmatched route

Uses `curl_multi_exec` to fire concurrent requests at the dev server on a
**404 route**. Measures framework overhead without any controller work.
**Target (php -S with 8 workers):** < 10 ms avg.

```bash
docker compose exec app php benchmarks/http_warm.php http://localhost:8080/ 1000 10
```

### 4. `http_hello.php` — full-stack Hello World closure

```php
$app->router->get('/', function(): void {
    echo 'Hello World';
});
```

End-to-end: PHP cold boot → autoload → router dispatch → closure → send.
**Target: < 2 ms avg per request.**

```bash
docker compose exec app php benchmarks/http_hello.php http://localhost:8080/ 1000 10
```

### 5. `http_json.php` — full-stack JSON closure

```php
$app->router->get('/json', function(): void {
    header('Content-Type: application/json');
    echo json_encode(['time' => microtime(true)]);
});
```

End-to-end: same as Hello World plus `json_encode` of a float. **Target:
< 7 ms avg per request.**

```bash
docker compose exec app php benchmarks/http_json.php http://localhost:8080/json 1000 10
```

The two new benchmarks output a structured `__JSON__` line on stdout when
`BENCH_JSON=1` is set, which `bench_run.php` uses to capture results.

---

## Realistic Targets in the Docker Dev Environment

The 2 ms / 7 ms targets are **framework-floor targets**: the cost of running
the full request lifecycle when every layer (autoload, router, dispatch,
response) is already warm in OPcache. On bare metal with `php -S` or with
`php-fpm` in a real web server, SKIM can hit them.

In Docker on macOS with bind-mounted source code, the PHP process boot alone
takes 3-5 ms (filesystem `stat()` calls on the bind mount dominate). Expect
the following realistic floors in this environment:

| Benchmark        | Bare metal target | Docker dev (realistic) | Bottleneck              |
|------------------|-------------------|------------------------|-------------------------|
| boot_isolation   | < 1 ms            | 3-6 ms                 | bind-mount `stat()`     |
| cache_hit        | < 0.05 ms         | < 0.001 ms             | (passes — pure OPcache) |
| http_warm        | < 10 ms           | 15-25 ms               | PHP cold boot per req   |
| http_hello       | < 2 ms            | 5-8 ms (1 concurrent)  | PHP cold boot per req   |
| http_json        | < 7 ms            | 6-9 ms (1 concurrent)  | PHP cold boot + encode  |

To squeeze closer to bare-metal performance in the dev container, the
included `docker-compose.yml` already sets:

- `PHP_CLI_SERVER_WORKERS=8` — parallel `php -S` workers (default is single-threaded)
- `PHP_INI_FLAGS` — `output_buffering=4096` (lets `echo`+`header()` in routes work
  with `Response::send()`), `display_errors=stderr` (no warnings leak into the
  response body), `implicit_flush=Off`, and `opcache.preload_user=www-data` so
  `storage/preload.php` warms the framework core into shared memory
- `storage/preload.php` — preloads `src/core/`, `src/cache/`, `src/db/`, `src/view/`,
  `src/helpers/`, `src/validation/`, `src/events/`, `src/queue/`, `src/realtime/`,
  `src/websocket/`, `src/http/`, `src/extension/` into OPcache

For real benchmarks, use `php-fpm` behind `nginx` on the host (not in Docker),
or run `php -S` on bare metal. The framework itself is the bottleneck only when
the runtime isn't.

---

## Reading the Results

A single benchmark run produces output like:

```
============================================================
SKIM Benchmark — 2026-06-04T16-15-20Z — commit d2ca225
============================================================
FAIL boot_isolation       5.616 ms
  OK cache_hit            0.000 ms
FAIL http_warm           20.170 ms    272.4 rps
FAIL http_hello          22.364 ms    243.6 rps
FAIL http_json           22.402 ms    243.3 rps
```

`FAIL` means the run exceeded the target documented above. Numbers are in
milliseconds; RPS is requests per second for the HTTP benchmarks.

The full machine-readable record is in `benchmarks/results/<timestamp>.json` —
suitable for diffing across commits, plotting in `gnuplot`/Excel, or piping
into a regression CI check.

---

## Files

```
benchmarks/
├── use.md                   ← this file
├── boot_isolation.php       ← App::instance() overhead
├── cache_hit.php            ← env + config read latency
├── http_warm.php            ← 404 route HTTP throughput
├── http_hello.php           ← Hello World closure
├── http_json.php            ← JSON closure
├── bench_run.php            ← runs all five, writes results/*.json
└── results/
    ├── latest.json
    ├── <timestamp>.json
    └── history.jsonl
```
