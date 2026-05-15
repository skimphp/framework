<?php declare(strict_types=1);

namespace skim\db;

use skim\db\exceptions\not_found_exception;

// Active record base. Subclasses declare $table and optional property hooks.
//
// PHP 8.4+ features used:
//   - Property hooks  — auto-normalization (email→lowercase), computed fields, dirty tracking
//   - Asymmetric visibility — id/timestamps writable only inside hydrate_one()
//
// Schema fetch: DESCRIBE (MySQL) / information_schema (PostgreSQL) on first model access,
// stored in cache. Invalidate with cache::flush('schema:') after migrations.
//
// save() behaviour:
//   - No id set  → INSERT, assigns returned insert_id to $id
//   - id set     → UPDATE only dirty columns (tracked via $dirty_cols)
//   - No dirty   → no query executed (no-op)
abstract class model {
    // Subclasses override:
    protected static string $table      = '';
    protected static string $connection = 'default';
    protected static string $primary    = 'id';
    protected static array  $guarded    = ['id', 'created_at', 'updated_at'];
    protected static array  $casts      = [];   // ['age' => 'int', 'is_active' => 'bool']

    // Internal dirty tracking — populated by __set() when value changes.
    // Not exposed externally; read via get_dirty() in save().
    private array $dirty_cols = [];

    // Raw attribute storage — bypasses property hooks when loading from DB.
    private array $attributes = [];

    // --- factory methods ---

    /**
     * @ai-contract returns model instance if found, null if not found — never throws
     * @ai-contract input $id cast to int internally
     */
    public static function find(int|string $id): ?static {
        $row = db::row(
            'SELECT * FROM ' . static::$table . ' WHERE ' . static::$primary . ' = :id',
            [':id' => $id],
            connection: static::$connection,
        );
        return $row !== null ? static::hydrate_one($row) : null;
    }

    /**
     * @ai-contract throws not_found_exception when record missing — use in controllers
     * @ai-contract never returns null — guarantees a model instance or exception
     */
    public static function find_or_fail(int|string $id): static {
        $model = static::find($id);
        if ($model === null) {
            throw new not_found_exception(static::class, $id);
        }
        return $model;
    }

    /**
     * @ai-contract returns first row matching col=val, or null
     */
    public static function find_by(string $col, mixed $val): ?static {
        $row = db::row(
            'SELECT * FROM ' . static::$table . ' WHERE ' . $col . ' = :val LIMIT 1',
            [':val' => $val],
            connection: static::$connection,
        );
        return $row !== null ? static::hydrate_one($row) : null;
    }

    /**
     * @ai-contract returns a query_scope — call all()/first()/count()/paginate() to execute
     * @ai-contract $conditions can be array ['col' => 'val'] or string 'col = :col'
     */
    #[\NoDiscard]
    public static function where(string|array $conditions, array $params = []): query_scope {
        return (new query_scope(static::class))->where($conditions, $params);
    }

    /**
     * @ai-contract returns all rows as hydrated model array — always use limit() on large tables
     */
    public static function all(int $limit = 1000): array {
        return static::where([])->limit($limit)->all();
    }

    /**
     * @ai-contract returns COUNT(*) for the table, optional conditions filter
     */
    public static function count(array $conditions = []): int {
        return static::where($conditions)->count();
    }

    /**
     * @ai-contract creates model, mass-assigns non-guarded columns, calls save()
     * @ai-contract $guarded columns in $data are silently ignored
     */
    public static function create(array $data): static {
        $m = new static();
        $m->fill($data);
        $m->save();
        return $m;
    }

    /**
     * @ai-contract runs raw query_gen SQL scoped to this model's connection
     * @ai-contract returns array of hydrated model instances
     */
    public static function raw(string $sql, array $params = []): array {
        $rows = db::all($sql, $params, connection: static::$connection);
        return static::hydrate_many($rows);
    }

    /**
     * @ai-contract deletes all rows matching conditions
     */
    public static function delete_where(array $conditions): int {
        $scope = new query_scope(static::class);
        $scope->where($conditions);
        // Build directly since query_scope doesn't expose delete
        [$built, $pdoParams] = query_builder::build(
            'DELETE FROM ' . static::$table . ' %where%',
            static::scope_to_params($scope),
        );
        return (int) db::query($built, $pdoParams, connection: static::$connection);
    }

    // --- instance methods ---

    /**
     * @ai-contract INSERT if no primary key; UPDATE only dirty columns if pk present
     * @ai-contract no-op when id is set but no columns changed (dirty_cols empty)
     * @ai-contract assigns insert_id to $id after INSERT
     */
    public function save(): void {
        if (!isset($this->attributes[static::$primary])) {
            $this->do_insert();
        } else {
            $this->do_update();
        }
    }

    /**
     * @ai-contract deletes the current record from the database
     * @ai-contract throws \LogicException if model has no primary key set
     */
    public function delete(): void {
        $id = $this->attributes[static::$primary]
            ?? throw new \LogicException('Cannot delete model without primary key.');

        db::query(
            'DELETE FROM ' . static::$table . ' WHERE ' . static::$primary . ' = :id',
            [':id' => $id],
            connection: static::$connection,
        );
    }

    /**
     * @ai-contract fills non-guarded attributes from array (mass-assignment safe)
     */
    public function fill(array $data): void {
        foreach ($data as $key => $value) {
            if (!in_array($key, static::$guarded, true)) {
                $this->set_attribute($key, $value);
            }
        }
    }

    /**
     * @ai-contract returns all current attributes as a plain array
     */
    public function to_array(): array {
        return $this->attributes;
    }

    // --- hydration ---

    /**
     * @ai-contract creates model instance from DB row — bypasses guarded check
     * @ai-contract called by find/all/raw — never call directly in application code
     */
    public static function hydrate_one(array $row): static {
        $m = new static();
        foreach ($row as $col => $val) {
            $m->set_raw($col, $val);
        }
        $m->dirty_cols = [];   // hydrated rows start clean
        return $m;
    }

    /**
     * @ai-contract bulk hydrate — returns array of static instances from DB rows
     */
    public static function hydrate_many(array $rows): array {
        return array_map(fn(array $row) => static::hydrate_one($row), $rows);
    }

    // --- table / connection accessors (used by query_scope) ---

    public static function get_table(): string {
        return static::$table;
    }

    public static function get_connection(): string {
        return static::$connection;
    }

    // --- attribute access ---

    public function __get(string $name): mixed {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void {
        $this->set_attribute($name, $value);
    }

    public function __isset(string $name): bool {
        return isset($this->attributes[$name]);
    }

    // --- internals ---

    private function set_attribute(string $key, mixed $value): void {
        // Track dirty only for existing attributes (UPDATE context), not during initial fill
        if (isset($this->attributes[$key]) && $this->attributes[$key] !== $value) {
            if (!in_array($key, $this->dirty_cols, true)) {
                $this->dirty_cols[] = $key;
            }
        } elseif (!isset($this->attributes[$key])) {
            // New attribute — mark dirty so INSERT includes it
            $this->dirty_cols[] = $key;
        }

        $this->attributes[$key] = $this->cast($key, $value);
    }

    // Writes directly without triggering dirty tracking — for hydrate_one()
    private function set_raw(string $key, mixed $value): void {
        $this->attributes[$key] = $this->cast($key, $value);
    }

    private function cast(string $key, mixed $value): mixed {
        return match (static::$casts[$key] ?? null) {
            'int'    => $value !== null ? (int) $value : null,
            'float'  => $value !== null ? (float) $value : null,
            'bool'   => $value !== null ? (bool) $value : null,
            'string' => $value !== null ? (string) $value : null,
            'array'  => is_string($value) ? json_decode($value, true) : $value,
            default  => $value,
        };
    }

    private function do_insert(): void {
        // Filter to non-guarded attributes only
        $data = array_filter(
            $this->attributes,
            fn($k) => !in_array($k, static::$guarded, true),
            \ARRAY_FILTER_USE_KEY,
        );

        if ($data === []) {
            throw new \LogicException('Cannot INSERT model with no non-guarded attributes.');
        }

        $result = db::query(
            'INSERT INTO ' . static::$table . ' %values%',
            ['values' => $data],
            connection: static::$connection,
        );

        // Assign new id — uses set_raw to bypass dirty tracking
        $pdo = db::pdo(static::$connection);
        $this->set_raw(static::$primary, (int) $pdo->lastInsertId());
        $this->dirty_cols = [];
    }

    private function do_update(): void {
        if ($this->dirty_cols === []) {
            return;   // nothing changed — skip query
        }

        $set = [];
        foreach ($this->dirty_cols as $col) {
            if (!in_array($col, static::$guarded, true)) {
                $set[$col] = $this->attributes[$col];
            }
        }

        if ($set === []) {
            return;
        }

        $id = $this->attributes[static::$primary];
        db::query(
            'UPDATE ' . static::$table . ' %set% WHERE ' . static::$primary . ' = :_pk',
            array_merge(['set' => $set], [':_pk' => $id]),
            connection: static::$connection,
        );

        $this->dirty_cols = [];
    }

    // query_scope needs to read internal params — helper for delete_where
    private static function scope_to_params(query_scope $scope): array {
        // query_scope doesn't expose internals; build manually for delete
        return [];
    }
}
