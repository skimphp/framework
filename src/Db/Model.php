<?php declare(strict_types=1);

namespace Skim\Db;

use Skim\Db\Exceptions\NotFoundException;

/**
 * Active record base class with dirty tracking and schema caching. #AI:class
 *
 * Use as the base class for all database-backed models. Subclasses declare
 * $table and optional property hooks for auto-normalization. PHP 8.4+
 * asymmetric visibility protects id/timestamps from external writes.
 * Schema is fetched via DESCRIBE/information_schema on first access and cached.
 *
 * Example:
 *   class User extends Model {
 *       protected static string $table = 'users';
 *       public string $email { set(string $val) => strtolower(trim($val)); }
 *   }
 *   $user = User::find(1);           // model|null
 *   $user = User::findOrFail(1);   // model|throws
 *   User::create(['name' => 'John', 'email' => 'J@J.COM']);
 *
 * Testing: Use test_db() for SQLite :memory:, models work directly against it.
 *
 * #AI:class
 */
abstract class Model {
    protected static string $table      = '';
    protected static string $connection = 'default';
    protected static string $primary    = 'id';
    protected static array  $guarded    = ['id', 'created_at', 'updated_at'];
    protected static array  $casts      = [];

    private array $dirtyCols = [];
    private array $attributes = [];

    // --- factory methods ---

    /**
     * Finds a record by primary key, returning null if not found. #AI:find
     *
     * Never throws — always check for null.
     *
     * @param int|string $id Primary key value.
     */
    public static function find(int|string $id): ?static {
        $row = \Skim\Db\Db::row(
            'SELECT * FROM ' . static::$table . ' WHERE ' . static::$primary . ' = :id',
            [':id' => $id],
            connection: static::$connection,
        );
        return $row !== null ? static::hydrateOne($row) : null;
    }

    /**
     * Finds a record by primary key, throwing not_found_exception if missing. #AI:findOrFail
     *
     * Use in controllers where missing records should produce a 404 response.
     *
     * @param int|string $id Primary key value.
     * @throws \Skim\Db\Exceptions\NotFoundException When no record matches.
     */
    public static function findOrFail(int|string $id): static {
        $model = static::find($id);
        if ($model === null) {
            throw new \Skim\Db\Exceptions\NotFoundException(static::class, $id);
        }
        return $model;
    }

    /**
     * Finds the first record matching a column=value condition. #AI:findBy
     *
     * Returns null if no match. Column name is validated against injection.
     *
     * @param string $col Column name (must be a valid identifier).
     * @param mixed  $val Value to match.
     * @throws \InvalidArgumentException If column name contains invalid characters.
     */
    public static function findBy(string $col, mixed $val): ?static {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
            throw new \InvalidArgumentException("Invalid column name '{$col}'.");
        }
        $row = \Skim\Db\Db::row(
            'SELECT * FROM ' . static::$table . ' WHERE ' . $col . ' = :val LIMIT 1',
            [':val' => $val],
            connection: static::$connection,
        );
        return $row !== null ? static::hydrateOne($row) : null;
    }

    /**
     * Returns a query_scope for building filtered queries. #AI:where
     *
     * Chain ->order(), ->limit(), ->paginate() before calling a terminal method.
     *
     * Example:
     *   $users = User::where(['status' => 'active'])->order('name')->limit(20)->all();
     *
     * @param string|array $conditions SQL fragment or column=>value pairs.
     * @param array        $params     PDO params when $conditions is a string.
     */
    #[\NoDiscard]
    public static function where(string|array $conditions, array $params = []): \Skim\Db\QueryScope {
        return (new \Skim\Db\QueryScope(static::class))->where($conditions, $params);
    }

    /**
     * Returns all rows as hydrated models — always use limit on large tables. #AI:all
     *
     * Default limit is 1000 to prevent accidental full-table scans.
     *
     * @param int $limit Maximum rows to return.
     */
    public static function all(int $limit = 1000): array {
        return static::where([])->limit($limit)->all();
    }

    /**
     * Returns COUNT(*) for the table, optionally filtered by conditions. #AI:count
     *
     * @param array $conditions Column=>value filter pairs.
     */
    public static function count(array $conditions = []): int {
        return static::where($conditions)->count();
    }

    /**
     * Creates a model, mass-assigns non-guarded columns, and saves. #AI:create
     *
     * Guarded columns in $data are silently ignored.
     *
     * @param array $data Column=>value pairs to assign.
     */
    public static function create(array $data): static {
        $m = new static();
        $m->fill($data);
        $m->save();
        return $m;
    }

    /**
     * Runs raw query_gen SQL scoped to this model's connection. #AI:raw
     *
     * Returns hydrated model instances. Use for complex queries that
     * query_scope cannot express (JOINs, subqueries, etc.).
     *
     * @param string $sql    SQL template with %placeholders%.
     * @param array  $params Placeholder values and :named params.
     */
    public static function raw(string $sql, array $params = []): array {
        $rows = \Skim\Db\Db::all($sql, $params, connection: static::$connection);
        return static::hydrateMany($rows);
    }

    /**
     * Deletes all rows matching the given conditions. #AI:deleteWhere
     *
     * WARNING: No limit — deletes ALL matching rows. Use with specific conditions.
     *
     * @param array $conditions Column=>value filter pairs.
     * @return int Number of rows deleted.
     */
    public static function deleteWhere(array $conditions): int {
        $scope = (new \Skim\Db\QueryScope(static::class))->where($conditions);
        [$built, $pdoParams] = \Skim\Db\QueryBuilder::build(
            'DELETE FROM ' . static::$table . ' %where%',
            static::scopeToParams($scope),
        );
        return (int) \Skim\Db\Db::query($built, $pdoParams, connection: static::$connection);
    }

    // --- instance methods ---

    /**
     * Persists the model — INSERT if no primary key, UPDATE only dirty columns if set. #AI:save
     *
     * No-op when id is set but no columns changed (dirty_cols empty).
     * After INSERT, assigns the auto-generated insert_id to $id.
     */
    public function save(): void {
        if (!isset($this->attributes[static::$primary])) {
            $this->doInsert();
        } else {
            $this->doUpdate();
        }
    }

    /**
     * Deletes the current record from the database. #AI:delete
     *
     * @throws \LogicException If model has no primary key set.
     */
    public function delete(): void {
        $id = $this->attributes[static::$primary]
            ?? throw new \LogicException('Cannot delete model without primary key.');

        \Skim\Db\Db::query(
            'DELETE FROM ' . static::$table . ' WHERE ' . static::$primary . ' = :id',
            [':id' => $id],
            connection: static::$connection,
        );
    }

    /**
     * Fills non-guarded attributes from an array (mass-assignment safe). #AI:fill
     *
     * Guarded columns (id, created_at, updated_at by default) are silently skipped.
     *
     * @param array $data Column=>value pairs to assign.
     */
    public function fill(array $data): void {
        foreach ($data as $key => $value) {
            if (!in_array($key, static::$guarded, true)) {
                $this->setAttribute($key, $value);
            }
        }
    }

    /**
     * Returns all current attributes as a plain array. #AI:toArray
     */
    public function toArray(): array {
        return $this->attributes;
    }

    // --- hydration ---

    /**
     * Creates a model instance from a DB row — bypasses guarded check. #AI:hydrateOne
     *
     * Called internally by find/all/raw — never call directly in application code.
     *
     * @param array $row Associative array from DB fetch.
     */
    public static function hydrateOne(array $row): static {
        $m = new static();
        foreach ($row as $col => $val) {
            $m->setRaw($col, $val);
        }
        $m->dirtyCols = [];
        return $m;
    }

    /**
     * Bulk hydrates an array of DB rows into model instances. #AI:hydrateMany
     *
     * @param array $rows Array of associative arrays from DB fetch.
     */
    public static function hydrateMany(array $rows): array {
        return array_map(fn(array $row) => static::hydrateOne($row), $rows);
    }

    // --- table / connection accessors (used by query_scope) ---

    /**
     * Returns the table name for this model. #AI:getTable
     */
    public static function getTable(): string {
        return static::$table;
    }

    /**
     * Returns the connection name for this model. #AI:getConnection
     */
    public static function getConnection(): string {
        return static::$connection;
    }

    /**
     * Returns column definitions from DB schema, cached for 1 hour. #AI:schema
     *
     * Supports MySQL (DESCRIBE), PostgreSQL (information_schema), SQLite (PRAGMA).
     * Cache key: schema:{table}:{connection} — flush with Cache::flush('schema:').
     */
    public static function schema(): array {
        $cacheKey = 'schema:' . static::$table . ':' . static::$connection;

        $cached = \Skim\Cache\Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $pdo    = \Skim\Db\Db::pdo(static::$connection);
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $cols = match ($driver) {
            'mysql'  => static::fetchMysqlSchema($pdo),
            'pgsql'  => static::fetchPgsqlSchema($pdo),
            'sqlite' => static::fetchSqliteSchema($pdo),
            default  => [],
        };

        \Skim\Cache\Cache::set($cacheKey, $cols, 3600);
        return $cols;
    }

    private static function fetchMysqlSchema(\PDO $pdo): array {
        $stmt = $pdo->query('DESCRIBE `' . static::$table . '`');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function fetchPgsqlSchema(\PDO $pdo): array {
        $stmt = $pdo->prepare(
            'SELECT column_name AS "Field", data_type AS "Type", is_nullable AS "Null"
             FROM information_schema.columns
             WHERE table_name = ? AND table_schema = \'public\'
             ORDER BY ordinal_position'
        );
        $stmt->execute([static::$table]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function fetchSqliteSchema(\PDO $pdo): array {
        $stmt = $pdo->query('PRAGMA table_info(`' . static::$table . '`)');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map(fn($r) => [
            'Field' => $r['name'],
            'Type'  => $r['type'],
            'Null'  => $r['notnull'] ? 'NO' : 'YES',
        ], $rows);
    }

    /**
     * Returns a flat array of column names from the cached schema. #AI:columnNames
     */
    public static function columnNames(): array {
        return array_column(static::schema(), 'Field');
    }

    // --- attribute access ---

    public function __get(string $name): mixed {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void {
        $this->setAttribute($name, $value);
    }

    public function __isset(string $name): bool {
        return isset($this->attributes[$name]);
    }

    // --- internals ---

    private function setAttribute(string $key, mixed $value): void {
        if (isset($this->attributes[$key]) && $this->attributes[$key] !== $value) {
            if (!in_array($key, $this->dirtyCols, true)) {
                $this->dirtyCols[] = $key;
            }
        } elseif (!isset($this->attributes[$key])) {
            $this->dirtyCols[] = $key;
        }

        $this->attributes[$key] = $this->cast($key, $value);
    }

    private function setRaw(string $key, mixed $value): void {
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

    private function doInsert(): void {
        $data = array_filter(
            $this->attributes,
            fn($k) => !in_array($k, static::$guarded, true),
            \ARRAY_FILTER_USE_KEY,
        );

        if ($data === []) {
            throw new \LogicException('Cannot INSERT model with no non-guarded attributes.');
        }

        $result = \Skim\Db\Db::query(
            'INSERT INTO ' . static::$table . ' %values%',
            ['values' => $data],
            connection: static::$connection,
        );

        $pdo = \Skim\Db\Db::pdo(static::$connection);
        $this->setRaw(static::$primary, (int) $pdo->lastInsertId());
        $this->dirtyCols = [];
    }

    private function doUpdate(): void {
        if ($this->dirtyCols === []) {
            return;
        }

        $set = [];
        foreach ($this->dirtyCols as $col) {
            if (!in_array($col, static::$guarded, true)) {
                $set[$col] = $this->attributes[$col];
            }
        }

        if ($set === []) {
            return;
        }

        $id = $this->attributes[static::$primary];
        \Skim\Db\Db::query(
            'UPDATE ' . static::$table . ' %set% WHERE ' . static::$primary . ' = :_pk',
            array_merge(['set' => $set], [':_pk' => $id]),
            connection: static::$connection,
        );

        $this->dirtyCols = [];
    }

    private static function scopeToParams(\Skim\Db\QueryScope $scope): array {
        return $scope->toBuilderParams();
    }
}

#AI:class
#AI symbol: Skim\Db\Model
#AI source_path: src/Db/Model.php
#AI title: model
#AI description: Active record base class with dirty tracking, schema caching, guarded mass-assignment, and type casting.
#AI role: active record base
#AI layer: db
#AI badges: [orm; active-record; dirty-tracking; schema-cache; guarded]
#AI intro: `model` is the active record base class for all database-backed models. It provides find/create/save/delete operations with automatic dirty tracking for efficient UPDATE queries, schema caching for introspection, guarded mass-assignment protection, and configurable type casting.
#AI lifecycle: instantiated via find/create/new, persisted via save(), schema cached per table for 1 hour
#AI fallback: none
#AI test_seam: test_db() for SQLite :memory:, models work directly against it
#AI invariants: [find() returns null on missing — never throws; findOrFail() throws NotFoundException; save() is INSERT when no PK, UPDATE only dirty columns when PK set; guarded columns silently skipped in fill() and INSERT; schema cached for 1 hour with key schema:{table}:{connection}]
#AI core_behaviors: [dirty tracking via setAttribute() records changed columns; hydrateOne() uses setRaw() to bypass dirty tracking; casts apply on both set and hydrate; QueryScope provides fluent WHERE/ORDER/LIMIT chaining]
#AI warnings: [all() defaults to limit 1000 — always paginate large tables; deleteWhere() has no limit — deletes ALL matching rows]
#AI notes: Subclasses should use PHP 8.4 property hooks for normalization and asymmetric visibility for id/timestamps.
#AI scope_items: [{name: $table | mutable: false | desc: Database table name.}; {name: $connection | mutable: false | desc: Named DB connection from config/db.php.}; {name: $primary | mutable: false | desc: Primary key column name.}; {name: $guarded | mutable: false | desc: Columns excluded from mass-assignment.}; {name: $casts | mutable: false | desc: Column type casting rules.}]
#AI owns: attributes array, dirtyCols tracking
#AI entry_points: [find; findOrFail; findBy; where; all; count; create; raw; deleteWhere; save; delete; fill; toArray; hydrateOne; hydrateMany; schema; columnNames]
#AI config_reads: [db.*.connection]
#AI non_goals: [Does not support relations — use MerryModel; Does not provide query builder — use QueryScope via where(); Does not handle validation — use Validate::make()]
#AI side_effects: [save() executes INSERT or UPDATE; delete() executes DELETE; schema() reads and caches DB schema]
#AI flow: Model::find(id) -> Db::row() -> hydrateOne() -> model instance; model->save() -> doInsert()/doUpdate() -> Db::query()
#AI lifecycle_steps: [Model::find/create/new; -> hydration or fill(); -> attribute assignment with dirty tracking; -> save() checks PK presence; -> doInsert() or doUpdate(); -> Db::query() with %values% or %set%; -> dirtyCols reset]
#AI section_order: [Finders; Query Building; Creation; Persistence; Deletion; Mass Assignment; Hydration; Schema; Accessors]
#AI architectural_notes: Active record pattern with dirty tracking for efficient updates. Schema caching avoids repeated DESCRIBE calls. Guarded columns protect against mass-assignment vulnerabilities.

#AI:find
#AI group: Finders
#AI frequency: high
#AI signature: public static function find(int|string $id): ?static
#AI contract: Finds a record by primary key. Returns null if not found — never throws.
#AI param_details: [{name: $id | type: int|string | required: true | desc: Primary key value.}]
#AI return_detail: {type: ?static | desc: Hydrated model instance or null.}

#AI:findOrFail
#AI group: Finders
#AI frequency: high
#AI signature: public static function findOrFail(int|string $id): static
#AI contract: Finds a record by primary key. Throws NotFoundException if missing — use in controllers for 404 responses.
#AI param_details: [{name: $id | type: int|string | required: true | desc: Primary key value.}]
#AI return_detail: {type: static | desc: Hydrated model instance.}
#AI throws_details: [{type: NotFoundException | desc: When no record matches the primary key.}]

#AI:findBy
#AI group: Finders
#AI frequency: medium
#AI signature: public static function findBy(string $col, mixed $val): ?static
#AI contract: Finds the first record matching a column=value condition. Validates column name against injection. Returns null if no match.
#AI param_details: [{name: $col | type: string | required: true | desc: Column name (validated as identifier).}; {name: $val | type: mixed | required: true | desc: Value to match.}]
#AI return_detail: {type: ?static | desc: Hydrated model instance or null.}
#AI throws_details: [{type: \InvalidArgumentException | desc: If column name contains invalid characters.}]

#AI:where
#AI group: Query Building
#AI frequency: high
#AI signature: public static function where(string|array $conditions, array $params = []): QueryScope
#AI contract: Returns a QueryScope for building filtered queries. Chain order/limit/paginate before terminal methods.
#AI param_details: [{name: $conditions | type: string|array | required: true | desc: SQL fragment or column=>value pairs.}; {name: $params | type: array | required: false | desc: PDO params when conditions is a string.}]
#AI return_detail: {type: QueryScope | desc: Fluent query builder scoped to this model.}
#AI notes: #[\NoDiscard] — always capture or chain the return value.

#AI:all
#AI group: Finders
#AI frequency: high
#AI signature: public static function all(int $limit = 1000): array
#AI contract: Returns all rows as hydrated models. Default limit of 1000 prevents accidental full-table scans.
#AI param_details: [{name: $limit | type: int | required: false | desc: Maximum rows to return. Default 1000.}]
#AI return_detail: {type: array | desc: Array of hydrated model instances.}

#AI:count
#AI group: Query Building
#AI frequency: medium
#AI signature: public static function count(array $conditions = []): int
#AI contract: Returns COUNT(*) for the table, optionally filtered by conditions.
#AI param_details: [{name: $conditions | type: array | required: false | desc: Column=>value filter pairs.}]
#AI return_detail: {type: int | desc: Number of matching rows.}

#AI:create
#AI group: Creation
#AI frequency: high
#AI signature: public static function create(array $data): static
#AI contract: Creates a new Model, mass-assigns non-guarded columns, and persists via save(). Guarded columns in $data are silently ignored.
#AI param_details: [{name: $data | type: array | required: true | desc: Column=>value pairs to assign.}]
#AI return_detail: {type: static | desc: Persisted model instance with assigned id.}
#AI side_effects: Executes INSERT query.

#AI:raw
#AI group: Query Building
#AI frequency: medium
#AI signature: public static function raw(string $sql, array $params = []): array
#AI contract: Runs raw query_gen SQL scoped to this model's connection. Returns hydrated model instances. Use for JOINs and subqueries.
#AI param_details: [{name: $sql | type: string | required: true | desc: SQL template with %placeholders%.}; {name: $params | type: array | required: false | desc: Placeholder values and :named params.}]
#AI return_detail: {type: array | desc: Array of hydrated model instances.}

#AI:deleteWhere
#AI group: Deletion
#AI frequency: low
#AI signature: public static function deleteWhere(array $conditions): int
#AI contract: Deletes all rows matching the conditions. No limit — deletes ALL matching rows.
#AI param_details: [{name: $conditions | type: array | required: true | desc: Column=>value filter pairs.}]
#AI return_detail: {type: int | desc: Number of rows deleted.}
#AI warnings: [No limit — deletes ALL matching rows. Use with specific conditions.]
#AI side_effects: Executes DELETE query.

#AI:save
#AI group: Persistence
#AI frequency: high
#AI signature: public function save(): void
#AI contract: INSERT if no primary key set; UPDATE only dirty columns if PK present. No-op when id is set but no columns changed.
#AI side_effects: Executes INSERT or UPDATE query.

#AI:delete
#AI group: Deletion
#AI frequency: medium
#AI signature: public function delete(): void
#AI contract: Deletes the current record from the database.
#AI throws_details: [{type: \LogicException | desc: If model has no primary key set.}]
#AI side_effects: Executes DELETE query.

#AI:fill
#AI group: Mass Assignment
#AI frequency: high
#AI signature: public function fill(array $data): void
#AI contract: Assigns non-guarded attributes from an array. Guarded columns are silently skipped.
#AI param_details: [{name: $data | type: array | required: true | desc: Column=>value pairs to assign.}]

#AI:toArray
#AI group: Accessors
#AI frequency: medium
#AI signature: public function toArray(): array
#AI contract: Returns all current attributes as a plain associative array.
#AI return_detail: {type: array | desc: Model attributes.}

#AI:hydrateOne
#AI group: Hydration
#AI frequency: internal
#AI signature: public static function hydrateOne(array $row): static
#AI contract: Creates a model instance from a DB row. Bypasses guarded check and dirty tracking. Never call directly in application code.
#AI param_details: [{name: $row | type: array | required: true | desc: Associative array from DB fetch.}]
#AI return_detail: {type: static | desc: Clean hydrated model instance.}

#AI:hydrateMany
#AI group: Hydration
#AI frequency: internal
#AI signature: public static function hydrateMany(array $rows): array
#AI contract: Bulk hydrates an array of DB rows into model instances.
#AI param_details: [{name: $rows | type: array | required: true | desc: Array of associative arrays.}]
#AI return_detail: {type: array | desc: Array of hydrated model instances.}

#AI:getTable
#AI group: Accessors
#AI frequency: internal
#AI signature: public static function getTable(): string
#AI contract: Returns the table name for this model.
#AI return_detail: {type: string | desc: Table name.}

#AI:getConnection
#AI group: Accessors
#AI frequency: internal
#AI signature: public static function getConnection(): string
#AI contract: Returns the connection name for this model.
#AI return_detail: {type: string | desc: Connection name.}

#AI:schema
#AI group: Schema
#AI frequency: low
#AI signature: public static function schema(): array
#AI contract: Returns column definitions from the DB schema. Cached for 1 hour. Supports MySQL, PostgreSQL, and SQLite.
#AI return_detail: {type: array | desc: Array of column definition arrays with Field, Type, Null keys.}
#AI side_effects: Reads DB schema on first call, caches result.

#AI:columnNames
#AI group: Schema
#AI frequency: low
#AI signature: public static function columnNames(): array
#AI contract: Returns a flat array of column names from the cached schema.
#AI return_detail: {type: array | desc: Array of column name strings.}
