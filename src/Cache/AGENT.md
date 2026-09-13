# src/cache — Agent Contract

## What this module does
PSR-16 cache facade over Redis, file, and array drivers.
`Cache::remember()` is the primary API — set/get/delete for edge cases only.

## Critical behaviours
- APCu is intentionally excluded — inconsistent state across PHP-FPM workers
- fallback chain: Redis → file (config `cache.fallback`) — prevents cache failure cascading
- flush('prefix:') scans matching keys — Redis uses SCAN not KEYS (avoids blocking)
- tags() requires Redis driver — throws on file/array drivers
- Profiler::cache() called on every operation — appears in toolbar cache tab
- Cache::setDriver() in tests — always inject array_driver, never use real Redis

## Key naming convention
- 'model:id' — single record
- 'model:scope:hash' — query results
- 'schema:tablename' — schema cache (invalidated by migrator)
- 'user:{id}' — user-specific data (flush('user:5') clears all keys for user 5)

## Common mistakes
- calling Cache::get() in a tight loop without remember() — cache miss per iteration
- not flushing 'schema:' after migrations — stale column lists in model hydration
- using Cache::flushAll() in production — nukes everything including sessions
