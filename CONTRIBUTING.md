# Contributing to SKIM

## Before you start

Read the [AGENT.md](AGENT.md) at the repo root — it defines every global rule.  
For the module you are touching, read `src/<module>/AGENT.md` first.

---

## Annotating new code

Every public method in `src/` must have a PHPDoc block with at least one `@ai.*` tag.  
These annotations feed `docs:extract` → `llm.json` → the MCP server and `llm.md`.

### Tags

| Tag | Purpose | Example |
|-----|---------|---------|
| `@ai-contract` | What the method does / what callers can rely on | `@ai-contract returns null when key is absent — never throws` |
| `@ai.invariant` | Invariants that must always hold | `@ai.invariant driver is set before first call` |
| `@ai.non_goal` | What the method deliberately does NOT do | `@ai.non_goal does not validate input — caller must validate` |
| `@ai.side_effect` | Observable side effects beyond the return value | `@ai.side_effect writes to Redis` |
| `@ai.lifecycle` | When in the request/boot lifecycle this runs | `@ai.lifecycle called once during app::boot()` |
| `@ai.perf` | Performance characteristics callers should know | `@ai.perf O(1) — result cached after first call` |
| `@ai.throws` | Exception types this method may throw | `@ai.throws not_found_exception when record is missing` |

> **`@ai-contract`** (hyphen) is the legacy single-tag form used throughout the existing source. Use it for simple method contracts.  
> **`@ai.*`** (dot) tags are the structured multi-value form parsed by `annotation_parser`.  
> Both work — the extractor reads both.

### Before (unannotated)

```php
public function find(int $id): ?user {
    return user::find($id);
}
```

### After (annotated)

```php
/**
 * @ai-contract finds user by primary key — returns null when not found, never throws
 * @ai.non_goal does not eager-load relations — call with() separately if needed
 * @ai.perf single SELECT by primary key; result NOT cached — wrap with cache::remember() if hot path
 */
public function find(int $id): ?user {
    return user::find($id);
}
```

---

## Verify coverage before pushing

```bash
php skim docs:validate
```

Exits non-zero when coverage of public methods falls below the threshold in `config/docs.php` (`validate.coverage_threshold`, default 80%).

### Recommended pre-push hook

```bash
#!/bin/sh
# .git/hooks/pre-push
php skim docs:validate
```

```bash
chmod +x .git/hooks/pre-push
```

---

## Regenerate llm.md after changes

`llm.md` is committed to the repo root. CI fails if it is stale.  
Run after touching any `src/` file:

```bash
php skim docs
```

This runs `docs:extract` → `docs:llm` → `docs:site` in sequence.

---

## Tests

Every new public method ships with a Pest test. Tests live in `tests/` mirroring `src/`:

```
src/cache/cache.php  →  tests/cache/cache_test.php
```

Run tests:

```bash
./vendor/bin/pest
```

Never modify existing tests to make them pass — fix the implementation instead.

---

## Code style quick reference

- `snake_case` everywhere — classes, methods, files, namespace segments
- Braces on same line: `if ($x) {` — `elseif` on its own line after `}`
- Full types on every function signature
- `return $res->...()` in controllers — never `echo` or `exit`
- No superglobals — use `$req->get()`, `$req->post()`, etc.
- No YAML/INI — PHP arrays only in `config/`
