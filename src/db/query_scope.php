<?php declare(strict_types=1);

namespace skim\db;

// Fluent query builder scoped to a model class.
// Collects where/order/limit/offset clauses without touching the DB.
// Terminal methods (all, count, paginate, first) compile and execute.
//
// #[\NoDiscard] on every clause method: ignoring the return loses the clause.
// Why: unlike Eloquent, query_scope is NOT the model — it builds a one-shot query.
class query_scope {
    private array  $conditions = [];
    private array  $pdo_params = [];
    private ?string $order      = null;
    private ?int    $limit_val  = null;
    private ?int    $offset_val = null;

    public function __construct(private readonly string $model_class) {}

    /**
     * @ai-contract adds a WHERE condition — repeated calls join with AND
     * @ai-contract $condition can be string 'col = :col' or array ['col' => 'val']
     */
    #[\NoDiscard]
    public function where(string|array $condition, array $params = []): static {
        if (is_array($condition)) {
            foreach ($condition as $col => $val) {
                $placeholder           = ':qw_' . $col . '_' . count($this->pdo_params);
                $this->conditions[]    = "{$col} = {$placeholder}";
                $this->pdo_params[$placeholder] = $val;
            }
        } else {
            $this->conditions[] = $condition;
            foreach ($params as $k => $v) {
                $this->pdo_params[$k] = $v;
            }
        }
        return $this;
    }

    /**
     * @ai-contract sets ORDER BY clause — last call wins
     */
    #[\NoDiscard]
    public function order(string $clause): static {
        $this->order = $clause;
        return $this;
    }

    /**
     * @ai-contract sets LIMIT — last call wins
     */
    #[\NoDiscard]
    public function limit(int $n): static {
        $this->limit_val = $n;
        return $this;
    }

    /**
     * @ai-contract sets OFFSET — last call wins
     */
    #[\NoDiscard]
    public function offset(int $n): static {
        $this->offset_val = $n;
        return $this;
    }

    /**
     * @ai-contract executes query, returns array of hydrated model instances
     */
    public function all(): array {
        return $this->model_class::hydrate_many($this->execute());
    }

    /**
     * @ai-contract executes query, returns first hydrated model or null
     */
    public function first(): mixed {
        $rows = $this->limit(1)->execute();
        return $rows !== [] ? $this->model_class::hydrate_one($rows[0]) : null;
    }

    /**
     * @ai-contract executes COUNT(*) with current conditions — ignores limit/offset
     */
    public function count(): int {
        /** @var model $class */
        $class = $this->model_class;
        $table = $class::get_table();
        return (int) db::val(
            "SELECT COUNT(*) FROM {$table} %where%",
            array_merge(['where' => $this->conditions], $this->pdo_params),
            connection: $class::get_connection(),
        );
    }

    /**
     * @ai-contract paginates results, returns pagination value object
     */
    public function paginate(int $page = 1, int $per_page = 20): pagination {
        $total = $this->count();
        $items = $this->limit($per_page)->offset(($page - 1) * $per_page)->all();
        return new pagination(items: $items, total: $total, per_page: $per_page, current: $page);
    }

    /**
     * @ai-contract returns params array in query_builder::build() format for external use
     * @ai-contract used by model::delete_where() to build scoped DELETE queries
     */
    public function to_builder_params(): array {
        return array_merge(['where' => $this->conditions], $this->pdo_params);
    }

    // --- internals ---

    private function execute(): array {
        /** @var model $class */
        $class  = $this->model_class;
        $table  = $class::get_table();
        $params = array_merge(['where' => $this->conditions], $this->pdo_params);

        if ($this->order !== null) {
            $params['order_by'] = $this->order;
        }
        if ($this->limit_val !== null) {
            $params['limit'] = $this->limit_val;
        }
        if ($this->offset_val !== null) {
            $params['offset'] = $this->offset_val;
        }

        $result = db::query(
            "SELECT * FROM {$table} %where% %order_by% %limit% %offset%",
            $params,
            connection: $class::get_connection(),
        );

        return is_array($result) ? $result : [];
    }
}
