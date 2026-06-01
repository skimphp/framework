# SKIM Benchmarks — User Guide

Reproducible performance benchmarks for SKIM Framework. All scripts run inside
the Docker container — no local PHP install required.

---

## Quick Start

```bash
# 1. Build compiled cache (production mode)
docker compose exec app php bin/skim --agent cache:build

# 2. Run all three benchmarks
docker compose exec app php benchmarks/boot_isolation.php
docker compose exec app php benchmarks/cache_hit.php
docker compose exec app php benchmarks/http_warm.php http://localhost:8080/ 1000 10
```

---

## The Three Benchmarks

### 1. `boot_isolation.php` — `app::instance()` overhead

Measures the time to create the application singleton. With compiled cache
and OPcache warm, this should be **well under 1 ms on bare metal**.
In Docker (with bind-mount filesystem overhead) expect 2-3 ms.

```bash
docker compose exec app php benchmarks/boot_isolation.php
```

**Output:**
```
boot_isolation: 2.45 ms
WARNING: boot time exceeds 1ms target
```

The WARNING is expected in Docker — it triggers on bare metal.

---

### 2. `cache_hit.php` — `env::get()` + `config::get()` per call

Runs 10,000 iterations of `env::get('APP_NAME')` and `config::get('app.name')`
with the compiled cache. **Target: < 0.05 ms per call**.

```bash
docker compose exec app php benchmarks/cache_hit.php
```

**Output:**
```
cache_hit: 0.0004 ms per call (10000 iterations, 2 calls each)
```

That is **~125x faster** than the 0.05 ms target. Pure OPcache memory hit.

---

### 3. `http_warm.php` — HTTP request throughput

Uses `curl_multi_exec` to fire concurrent requests at the dev server. No
external dependencies (no `wrk`, no `ab`). Measures avg latency, p95, RPS.

```bash
# Syntax: php http_warm.php [url] [total_requests] [concurrent]
docker compose exec app php benchmarks/http_warm.php http://localhost:8080/ 1000 10
```

**Output:**
```
http_warm: avg: 4.83 ms | p95: 9.33 ms | rps: 1,121.4
  requests: 1000 ok, 0 errors, 0.89s total
```

**Target (php -S):** < 10 ms avg, ~50-80 RPS.
**Actual:** 4-7 ms avg, **1,100-1,500 RPS** (15-20x over target).

#### Tuning concurrency

| Concurrent | Total | Avg | p95 | RPS |
|------------|-------|-----|-----|-----|
| 10  | 1000 | 4.83 ms  | 9.33 ms  | 1,121 |
| 20  | 2000 | 7.21 ms  | 12.54 ms | 1,472 |
| 50  | 5000 | 16.64 ms | 29.74 ms | 1,513 |

Higher concurrency pushes RPS higher but increases tail latency. Pick what
matches your production traffic profile.

---

## Verifying the RPS Claim

The RPS number is measured **inside Docker** against the php-built-in server.
The same hardware on bare metal will be faster. To reproduce:

```bash
# Make sure the dev server is up
docker compose up -d

# Build cache
docker compose exec app php bin/skim --agent cache:build

# Run a 1000-request benchmark with 10 concurrent connections
docker compose exec app php benchmarks/http_warm.php http://localhost:8080/ 1000 10

# Expected: avg ~5ms, p95 ~10ms, RPS ~1100-1500
```

**What the number includes:**
- Cold PHP process boot (per request, in php -S mode)
- Autoloader resolution
- `env::get()` + `config::get()` (with compiled cache)
- Router dispatch
- Response send

**What the number does NOT include:**
- Database queries (the test route returns 404 with no DB hit)
- Real middleware execution (only `cors` global middleware)
- Network latency to a real client (loopback is ~0.1 ms)

For a "real-world" estimate, multiply by 2-3x to account for typical
controller work (1 DB query, 1 cache lookup, JSON serialization).

---

## Troubleshooting

**"boot time exceeds 1ms target"** — Expected in Docker. The `realpath()` and
`stat()` calls on bind-mounted volumes add 1-2 ms. On bare metal with
OPcache, this is well under 1 ms.

**"avg latency exceeds 10ms target"** — Means your controller is doing
real work (DB, HTTP calls, etc.). The benchmark only tests the framework
overhead on a 404 route.

**"no successful requests"** — The dev server is not running. Start it:
```bash
docker compose up -d
# Wait ~3 seconds for the PHP server to be ready
```

**Cache hit shows high numbers** — Make sure you built the cache first:
```bash
docker compose exec app php bin/skim --agent cache:build
# Check the file exists:
docker compose exec app ls -la storage/cache/
```

---

## Files

```
benchmarks/
├── use.md               ← this file
├── boot_isolation.php   ← app::instance() overhead
├── cache_hit.php        ← env + config read latency
└── http_warm.php        ← HTTP throughput
```
