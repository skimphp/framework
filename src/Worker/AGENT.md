# src/worker — Agent Contract

## What this module does
Worker-mode lifecycle and isolation guarantees for FrankenPHP (and any other
long-lived SAPI). `WorkerReset` discovers and resets all `resettable` facades
between requests so per-request static state cannot leak across the worker loop.

## Three-tier testing strategy

| Tier | What | Where | Hard fail? |
|------|------|-------|------------|
| T1 | Unit tests (Pest) | `./vendor/bin/pest` | Yes — blocks PR |
| T2 | In-process lifecycle gate | `tests/worker/worker_lifecycle_test.php` | Yes — blocks PR |
| T3 | Real FrankenPHP worker E2E | `.github/workflows/ci.yml` `worker-isolation` job | Yes — blocks PR |

**Gate vs benchmark:** correctness (isolation, memory, post-error, events) is a
hard pass/fail gate. Performance (RPS, p95, p99) is a recorded artifact +
regression-warn — shared runners are too noisy for a hard latency target.

## Critical behaviours
- `WorkerReset::apply()` runs once per request, after `endRequest()`
- `WorkerReset::discover()` incrementally scans `get_declared_classes()` for
  new `resettable` implementations — catches facades autoloaded lazily after
  the first request
- `Event::captureBootSnapshot()` must be called once after `freeze()` so
  boot-time listeners survive while request-time listeners are dropped
- `ComponentCollector` is `resettable` — its stack is cleared even when a
  component throws and `pop()` never runs

## leak_detector (Layer 3)

Runtime safety net for the bug class the other layers cannot catch: request
state that survives a request regardless of any annotation — a singleton that
quietly accumulates per-request data, an unbounded static cache, a listener
that piles up, a transaction left open.

**Modes:** `off` (default in prod), `warn` (dev worker, auto-on), `strict` (CI).
Resolution: explicit config wins; `null` → `warn` when `WORKER_MODE && debug`.

**Checks (after `WorkerReset::apply()`):**
- Hard invariants (immediate): user scope empty, OB level restored, no open DB
  transactions, request-scoped bindings dropped from resolved cache.
- Soft growth trends (windowed, after warmup=10): monotonic growth in
  memory_get_usage(true), resolved singleton count, total event-listener count,
  DB connection count.

**Reporting:** `Log::warning` + `RequestTrace::event('leak.detected')` +
`Profiler::panel('leaks')`. Strict mode accumulates in
`LeakDetector::findings()`; the `/__leaks` route exposes them for the T3 CI job.

**Never throws mid-request** — that would corrupt the live response.

## Common mistakes
- Skipping `Event::captureBootSnapshot()` in a custom worker entry point —
  request listeners accumulate silently
- Adding per-request state to a class without implementing `resettable` — the
  contract-guard test (`worker_reset_test.php`) will fail and block the PR
- Expecting `memory_get_usage(true)` to be flat from request 1 — warmup
  allocations (autoload, first caches) are legitimate; baseline at ~request 5
