# tests/worker — worker-mode correctness gates

Standalone scripts that verify FrankenPHP worker-mode behaviour: cross-request
isolation, state reset, memory bounds. They are NOT Pest tests — most need a
live worker process or a real HTTP socket, so Pest must never load them
(no `*_test.php` suffix here).

## Scripts

| Script          | What it checks                                        | Runs |
|-----------------|-------------------------------------------------------|------|
| `isolation.php` | cross-request isolation against a live worker on :PORT | CI   |
| `correctness.php` | in-process worker gate — reset, config, env         | local/CI |
| `boot.php`      | App::instance() boot time in isolation                | local |
| `memory.php`    | simulated worker loop — memory growth bounds          | local |
| `soak.php`      | long multi-component workload through worker loop     | local |

## Usage

```bash
# in-process gates — inside the app container:
docker compose exec app php tests/worker/correctness.php
docker compose exec app php tests/worker/boot.php
docker compose exec app php tests/worker/memory.php
docker compose exec app php tests/worker/soak.php

# live worker isolation — needs frankenphp up:
docker compose -f docker-compose.yml -f docker-compose.frankenphp.yml up -d frankenphp
php tests/worker/isolation.php 8081
```

Throughput/latency benchmarks (bench_run, http_*, cache_hit) live in the
`skimphp/skim_benchmarks` repository.
