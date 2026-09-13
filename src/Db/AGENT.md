# src/db — Agent Contract

## What this module does
PDO wrapper, query_gen, active record (model), merry ORM (merry_model).
Supports MySQL, PostgreSQL, and SQLite. Schema cached on first connect.

## query_gen — critical behaviours LLM must know
- %where% with empty array or all-null conditions → removed silently, query runs
- %where% never present with populated conditions → WHERE injected automatically
- null values in %set% → that column is SKIPPED, not set to NULL
- to explicitly set NULL use: 'set' => ['col' => Db::null()]
- debug:true → returns interpolated SQL string, does NOT execute
- :named params always — never interpolate user input into SQL string
- limit/offset accept both 'limit' and ':limit' key formats
- or/and nesting in %where% → generates (a AND b) OR (c AND d) grouping

## active record — critical behaviours
- find($id) returns null if not found — never throws
- findOrFail($id) throws not_found_exception — use in controllers
- schema is fetched once via driver-specific queries (DESCRIBE / information_schema / PRAGMA table_info), stored in cache driver
- invalidate schema cache after migrations: Cache::flush('schema:')
- $guarded columns are never mass-assigned even if present in input array
- save() runs INSERT if no primary key, UPDATE if primary key set
- save() with dirty tracking: UPDATE only changed columns, not all columns
- property hooks run on every assignment — hooks fire even inside hydrate()
- id/created_at/updated_at use asymmetric visibility — writable only inside model
- computed properties (get-only hooks) are never included in INSERT/UPDATE

## merry ORM — critical behaviours
- with('relation') eager loads — always prefer over lazy on list pages
- with('posts.comments') loads nested — dot notation for depth
- attach/detach/sync only available on many_to_many relations
- relations defined in static arrays, not annotations

## common mistakes to avoid
- calling all() without limit on large tables → always paginate
- forgetting to call Db::transaction() when doing multi-table writes
- using find() result without null check when not using findOrFail()

## dependencies
- PDO (PHP built-in)
- No external ORM packages in active-record mode
- Eloquent (illuminate/database) only if installed via module:add eloquent
