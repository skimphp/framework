# src/helpers — Agent Contract

## arr helper
- arr::map_by('id', $rows) — re-index by column; last-write-wins on collision
- arr::find() wraps PHP 8.4 array_find() — returns first match or null
- arr::first() / arr::last() wrap PHP 8.5 array_first() / array_last()
- arr::pluck('name', $rows) — equivalent to array_column($rows, 'name')
- arr::weighted_pick() — uses random_int (crypto-safe), suitable for A/B tests

## filter helper
- All methods return the typed value or false (never null)
- filter::int() with min/max enforces range; filter::int_positive()/int_natural() are shortcuts
- filter::bool() accepts '1','true','yes','on' / '0','false','no','off' — rejects ambiguous strings
- filter::email() lowercases the result — always store as lowercase
- filter::arr_int()/arr_in() — batch variants for filtering form checkbox arrays

## str helper
- str::random() uses random_int — cryptographically safe, safe for tokens/secrets
- str::uuid() is RFC 4122 v4 — collision probability negligible for app-scale use
- str::slug() strips non-Unicode letters/numbers — safe for multi-language slugs

## Common mistakes
- Checking `if (filter::int($v))` instead of `if (filter::int($v) !== false)` — int 0 is falsy!
- Using str::random() for passwords — use password_hash() instead
