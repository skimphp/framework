# src/queue — Agent Contract

## What this module does
Redis-backed job queue. Jobs pushed via Queue::push(), consumed by the worker process.

## Critical behaviours
- Jobs must be fully serializable — no closures, no PDO handles, no resource handles
- base_job extends with sensible defaults (tries=3, delay=0)
- Delayed jobs go into a sorted set (zadd) by execute_at timestamp; worker promotes them each loop
- Retry: exponential back-off (attempts × 5 seconds); failed() called after all tries exhausted
- failed() must NOT rethrow — worker catches exceptions from failed() itself
- Queue::flush() in tests — always flush before/after to prevent cross-test state
- Worker signals: SIGTERM/SIGINT → graceful stop after current job (pcntl required)
- queue:restart Redis key → workers poll for it each iteration; set via `php skim queue:restart`

## Job class rules
- Store only primitive state in properties (IDs, not full objects)
- handle() must be idempotent when possible (network failures cause double-execution)
- Use Db::transaction() inside handle() for atomicity

## Common mistakes
- Storing a model instance in the job — model may be stale by the time worker runs; store $id instead
- Infinite retry (tries = PHP_INT_MAX) — fills queue on persistent DB failures
- Starting a worker without pcntl — SIGTERM kills mid-job, leaving data inconsistent
