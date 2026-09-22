# src/helpers — Agent Contract

## arr helper
- Arr::mapBy('id', $rows) — re-index by column; last-write-wins on collision
- Arr::find() wraps PHP 8.4 array_find() — returns first match or null
- Arr::first() / Arr::last() wrap PHP 8.5 array_first() / array_last()
- Arr::pluck('name', $rows) — equivalent to array_column($rows, 'name')
- Arr::weightedPick() — uses random_int (crypto-safe), suitable for A/B tests

## filter helper
- All methods return the typed value or false (never null)
- Filter::int() with min/max enforces range; Filter::intPositive()/intNatural() are shortcuts
- Filter::bool() accepts '1','true','yes','on' / '0','false','no','off' — rejects ambiguous strings
- Filter::email() lowercases the result — always store as lowercase
- Filter::arrInt()/arrIn() — batch variants for filtering form checkbox arrays

## str helper
- Str::random() uses random_int — cryptographically safe, safe for tokens/secrets
- Str::uuid() is RFC 4122 v4 — collision probability negligible for app-scale use
- Str::slug() strips non-Unicode letters/numbers — safe for multi-language slugs

## Common mistakes
- Checking `if (Filter::int($v))` instead of `if (Filter::int($v) !== false)` — int 0 is falsy!
- Using Str::random() for passwords — use password_hash() instead
