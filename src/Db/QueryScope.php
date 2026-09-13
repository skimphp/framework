<?php declare(strict_types=1);

namespace Skim\Db;

/**
 * Fluent query builder scoped to a model class. #AI:class
 *
 * Use when building filtered, ordered, or paginated queries on a model.
 * Collects WHERE/ORDER/LIMIT/OFFSET clauses without touching the DB until
 * a terminal method (all, first, count, paginate) is called.
 *
 * Example:
 *   $users = User::where(['status' => 'active'])
 *       ->order('created_at DESC')
 *       ->limit(20)
 *       ->all();
 *
 * Testing: Use test_db() SQLite :memory: — query_scope executes real queries.
 *
 * #AI:class
 */
class QueryScope {
    private array  $conditions = [];
    private array  $pdoParams = [];
    private ?string $order      = null;
    private ?int    $limitVal  = null;
    private ?int    $offsetVal = null;

    public function __construct(private readonly string $modelClass) {}

    /**
     * Adds a WHERE condition — repeated calls join with AND. #AI:where
     *
     * Accepts array ['col' => 'val'] for simple equality or string
     * 'col = :col' with separate $params for complex expressions.
     *
     * @param string|array $condition SQL fragment or column=>value pairs.
     * @param array        $params    PDO params when $condition is a string.
     */
    #[\NoDiscard]
    public function where(string|array $condition, array $params = []): static {
        if (is_array($condition)) {
            foreach ($condition as $col => $val) {
                $placeholder           = ':qw_' . $col . '_' . count($this->pdoParams);
                $this->conditions[]    = "{$col} = {$placeholder}";
                $this->pdoParams[$placeholder] = $val;
            }
        } else {
            $this->conditions[] = $condition;
            foreach ($params as $k => $v) {
                $this->pdoParams[$k] = $v;
            }
        }
        return $this;
    }

    /**
     * Sets ORDER BY clause — last call wins. #AI:order
     *
     * @param string $clause SQL ORDER BY expression, e.g. 'created_at DESC'.
     */
    #[\NoDiscard]
    public function order(string $clause): static {
        $this->order = $clause;
        return $this;
    }

    /**
     * Sets LIMIT — last call wins. #AI:limit
     *
     * @param int $n Maximum rows to return.
     */
    #[\NoDiscard]
    public function limit(int $n): static {
        $this->limitVal = $n;
        return $this;
    }

    /**
     * Sets OFFSET — last call wins. #AI:offset
     *
     * @param int $n Number of rows to skip.
     */
    #[\NoDiscard]
    public function offset(int $n): static {
        $this->offsetVal = $n;
        return $this;
    }

    /**
     * Executes the query and returns hydrated model instances. #AI:all
     */
    public function all(): array {
        return $this->modelClass::hydrateMany($this->execute());
    }

    /**
     * Executes the query and returns the first model or null. #AI:first
     */
    public function first(): mixed {
        $rows = $this->limit(1)->execute();
        return $rows !== [] ? $this->modelClass::hydrateOne($rows[0]) : null;
    }

    /**
     * Executes COUNT(*) with current conditions — ignores limit/offset. #AI:count
     */
    public function count(): int {
        /** @var \Skim\Db\Model $class */
        $class = $this->modelClass;
        $table = $class::getTable();
        return (int) \Skim\Db\Db::val(
            "SELECT COUNT(*) FROM {$table} %where%",
            array_merge(['where' => $this->conditions], $this->pdoParams),
            connection: $class::getConnection(),
        );
    }

    /**
     * Paginates results, returning a pagination value object. #AI:paginate
     *
     * Example:
     *   $page = User::where(['role' => 'admin'])->paginate(page: 2, per_page: 25);
     *   // $page->items, $page->total, $page->pages, $page->hasNext
     *
     * @param int $page     Current page number (1-indexed).
     * @param int $perPage Items per page.
     */
    public function paginate(int $page = 1, int $perPage = 20): \Skim\Db\Pagination {
        $total = $this->count();
        $items = $this->limit($perPage)->offset(($page - 1) * $perPage)->all();
        return new \Skim\Db\Pagination(items: $items, total: $total, perPage: $perPage, current: $page);
    }

    /**
     * Exports collected params in QueryBuilder::build() format. #AI:toBuilderParams
     *
     * Used internally by Model::deleteWhere() to build scoped DELETE queries.
     */
    public function toBuilderParams(): array {
        return array_merge(['where' => $this->conditions], $this->pdoParams);
    }

    // --- internals ---

    private function execute(): array {
        /** @var \Skim\Db\Model $class */
        $class  = $this->modelClass;
        $table  = $class::getTable();
        $params = array_merge(['where' => $this->conditions], $this->pdoParams);

        if ($this->order !== null) {
            $params['order_by'] = $this->order;
        }
        if ($this->limitVal !== null) {
            $params['limit'] = $this->limitVal;
        }
        if ($this->offsetVal !== null) {
            $params['offset'] = $this->offsetVal;
        }

        $result = \Skim\Db\Db::query(
            "SELECT * FROM {$table} %where% %order_by% %limit% %offset%",
            $params,
            connection: $class::getConnection(),
        );

        return is_array($result) ? $result : [];
    }
}

#AI:class
#AI symbol: Skim\Db\QueryScope
#AI source_path: src/Db/QueryScope.php
#AI title: QueryScope
#AI description: Fluent query builder scoped to a model class with WHERE/ORDER/LIMIT/OFFSET collection and terminal execution.
#AI role: fluent query builder
#AI layer: db
#AI badges: [fluent; builder; orm; no-discard]
#AI intro: `QueryScope` collects WHERE, ORDER BY, LIMIT, and OFFSET clauses without executing any SQL. Terminal methods (`all()`, `first()`, `count()`, `paginate()`) compile the collected state into a query_gen SQL template and execute it via `Db::query()`.
#AI lifecycle: created by Model::where(), consumed by terminal method call
#AI fallback: none
#AI test_seam: test via Model::where() against test_db() SQLite :memory:
#AI invariants: [clause methods are #[\NoDiscard] — discarding the return silently loses the clause; repeated where() calls join with AND; order/limit/offset use last-call-wins; count() ignores limit/offset]
#AI core_behaviors: [Collects conditions without DB access until terminal method; Array conditions auto-generate unique placeholders to avoid collisions; Terminal methods compile to query_gen SQL and execute]
#AI warnings: [Discarding the return of where()/order()/limit()/offset() loses that clause — always capture or chain]
#AI notes: Unlike Eloquent, QueryScope is NOT the model — it builds a one-shot query. Each terminal call executes independently.
#AI scope_items: []
#AI owns: conditions array, pdoParams, order/limit/offset state
#AI entry_points: [where; order; limit; offset; all; first; count; paginate]
#AI config_reads: []
#AI non_goals: [Does not support JOINs; Does not support GROUP BY or HAVING; Does not cache results]
#AI side_effects: [Terminal methods execute real DB queries]
#AI flow: Model::where() -> new QueryScope -> chain clauses -> terminal method -> execute() -> Db::query()
#AI lifecycle_steps: [Model::where(conditions); -> new QueryScope(class); -> chain where/order/limit/offset; -> terminal method (all/first/count/paginate); -> execute() compiles SQL; -> Db::query() with query_gen placeholders]
#AI section_order: [Clause Methods; Terminal Methods; Internal]
#AI architectural_notes: QueryScope is a thin collector over query_gen placeholders. All SQL generation happens in QueryBuilder::build() at execution time.

#AI:where
#AI group: Clause Methods
#AI frequency: high
#AI signature: public function where(string|array $condition, array $params = []): static
#AI contract: Adds a WHERE condition. Array form ['col' => 'val'] generates unique placeholders automatically. String form 'col = :col' requires matching $params. Repeated calls join with AND.
#AI param_details: [{name: $condition | type: string|array | required: true | desc: SQL fragment or column=>value equality pairs.}; {name: $params | type: array | required: false | desc: PDO params when $condition is a string fragment.}]
#AI return_detail: {type: static | desc: Returns $this for chaining.}
#AI notes: #[\NoDiscard] — always capture or chain the return value.

#AI:order
#AI group: Clause Methods
#AI frequency: medium
#AI signature: public function order(string $clause): static
#AI contract: Sets ORDER BY clause. Last call wins — does not accumulate.
#AI param_details: [{name: $clause | type: string | required: true | desc: SQL ORDER BY expression like 'created_at DESC'.}]
#AI return_detail: {type: static | desc: Returns $this for chaining.}

#AI:limit
#AI group: Clause Methods
#AI frequency: high
#AI signature: public function limit(int $n): static
#AI contract: Sets LIMIT. Last call wins.
#AI param_details: [{name: $n | type: int | required: true | desc: Maximum rows to return.}]
#AI return_detail: {type: static | desc: Returns $this for chaining.}

#AI:offset
#AI group: Clause Methods
#AI frequency: medium
#AI signature: public function offset(int $n): static
#AI contract: Sets OFFSET. Last call wins.
#AI param_details: [{name: $n | type: int | required: true | desc: Number of rows to skip.}]
#AI return_detail: {type: static | desc: Returns $this for chaining.}

#AI:all
#AI group: Terminal Methods
#AI frequency: high
#AI signature: public function all(): array
#AI contract: Executes the collected query and returns an array of hydrated model instances.
#AI return_detail: {type: array | desc: Array of hydrated model instances.}
#AI side_effects: Executes a SELECT query.

#AI:first
#AI group: Terminal Methods
#AI frequency: high
#AI signature: public function first(): mixed
#AI contract: Executes the query with LIMIT 1 and returns the first hydrated model or null.
#AI return_detail: {type: mixed | desc: Hydrated model instance or null.}
#AI side_effects: Executes a SELECT query.

#AI:count
#AI group: Terminal Methods
#AI frequency: high
#AI signature: public function count(): int
#AI contract: Executes COUNT(*) with current WHERE conditions. Ignores limit and offset.
#AI return_detail: {type: int | desc: Number of matching rows.}
#AI side_effects: Executes a SELECT COUNT(*) query.

#AI:paginate
#AI group: Terminal Methods
#AI frequency: high
#AI signature: public function paginate(int $page = 1, int $perPage = 20): pagination
#AI contract: Executes count() and a limited all() to produce a pagination value object with items, total, and navigation properties.
#AI param_details: [{name: $page | type: int | required: false | desc: Current page number (1-indexed). Default 1.}; {name: $perPage | type: int | required: false | desc: Items per page. Default 20.}]
#AI return_detail: {type: pagination | desc: Immutable pagination value object.}
#AI side_effects: Executes two queries — COUNT(*) and SELECT with LIMIT/OFFSET.

#AI:toBuilderParams
#AI group: Internal
#AI frequency: internal
#AI signature: public function toBuilderParams(): array
#AI contract: Exports collected conditions and params in QueryBuilder::build() format. Used by Model::deleteWhere().
#AI return_detail: {type: array | desc: Params array compatible with QueryBuilder::build().}
