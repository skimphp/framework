<?php declare(strict_types=1);

namespace Skim\Db;

/**
 * Internal SQL template processor for query_gen %placeholder% substitution. #AI:class
 *
 * Not part of the public SKIM API — used exclusively by Db::query(), Db::val(),
 * Db::row(), Db::all(). Handles %where%, %set%, %values%, %order_by%, %group_by%,
 * %limit%, %offset% substitution. Unused placeholders are stripped silently.
 *
 * Example (internal — called by Db:: methods):
 *   [$sql, $params] = QueryBuilder::build(
 *       'SELECT * FROM users %where% %limit%',
 *       ['where' => ['status = :s'], ':s' => 'active', 'limit' => 20],
 *   );
 *
 * Testing: Use Db::query(..., debug: true) to get interpolated SQL without executing.
 *
 * #AI:class
 */
final class QueryBuilder {
    /**
     * Processes a SQL template with %placeholders% into executable SQL + PDO params. #AI:build
     *
     * Unused %placeholders% are stripped silently — build dynamic queries without
     * conditionals. Null values in :named params are excluded from the PDO param array.
     *
     * @param string $sql    SQL template with %placeholders%.
     * @param array  $params Placeholder values and :named params.
     * @return array{string, array<string, mixed>} [built SQL, PDO params] ready for prepare + execute.
     */
    public static function build(string $sql, array $params): array {
        $pdoParams = [];

        if (isset($params['set'])) {
            [$clause, $extra] = self::buildSet($params['set']);
            $sql = str_replace('%set%', $clause, $sql);
            $pdoParams += $extra;
            unset($params['set']);
        }

        if (isset($params['values'])) {
            [$clause, $extra] = self::buildValues($params['values']);
            $sql = str_replace('%values%', $clause, $sql);
            $pdoParams += $extra;
            unset($params['values']);
        }

        if (isset($params['where'])) {
            $clause = self::buildWhere($params['where']);
            $sql = str_replace('%where%', $clause !== '' ? "WHERE {$clause}" : '', $sql);
            unset($params['where']);
        }

        if (isset($params['order_by'])) {
            $sql = str_replace('%order_by%', 'ORDER BY ' . $params['order_by'], $sql);
            unset($params['order_by']);
        }

        if (isset($params['group_by'])) {
            $sql = str_replace('%group_by%', 'GROUP BY ' . $params['group_by'], $sql);
            unset($params['group_by']);
        }

        if (isset($params['limit'])) {
            $sql = str_replace('%limit%', 'LIMIT ' . (int) $params['limit'], $sql);
            unset($params['limit']);
        }

        if (isset($params['offset'])) {
            $sql = str_replace('%offset%', 'OFFSET ' . (int) $params['offset'], $sql);
            unset($params['offset']);
        }

        $sql = (string) preg_replace('/%\w+%/', '', $sql);
        $sql = (string) preg_replace('/\s{2,}/', ' ', trim($sql));

        foreach ($params as $key => $val) {
            if (str_starts_with((string) $key, ':') && $val !== null) {
                $pdoParams[$key] = $val;
            }
        }

        return [$sql, $pdoParams];
    }

    /**
     * Builds a WHERE clause from flat or nested (and/or) conditions. #AI:buildWhere
     *
     * Flat list: ['a = :a', 'b = :b'] → joined with AND.
     * Nested: ['or' => [[...], [...]], 'and' => [[...], [...]]] → grouped and wrapped.
     * Returns empty string when conditions are empty — %where% is removed from SQL.
     *
     * @param array $conditions Flat list or nested and/or structure.
     */
    public static function buildWhere(array $conditions): string {
        if ($conditions === []) {
            return '';
        }

        if (array_is_list($conditions)) {
            return implode(' AND ', array_filter($conditions));
        }

        $parts = [];

        if (!empty($conditions['or'])) {
            $orGroups = [];
            foreach ($conditions['or'] as $group) {
                $filtered = array_filter((array) $group);
                if ($filtered !== []) {
                    $orGroups[] = '(' . implode(' AND ', $filtered) . ')';
                }
            }
            if ($orGroups !== []) {
                $parts[] = '(' . implode(' OR ', $orGroups) . ')';
            }
        }

        if (!empty($conditions['and'])) {
            foreach ($conditions['and'] as $group) {
                $filtered = array_filter((array) $group);
                if ($filtered !== []) {
                    $parts[] = '(' . implode(' AND ', $filtered) . ')';
                }
            }
        }

        return implode(' AND ', $parts);
    }

    /**
     * Builds a SET clause for UPDATE statements. #AI:buildSet
     *
     * Null values skip the column (partial update). null_marker instances
     * produce SET col = NULL (explicit erasure via Db::null()).
     *
     * @param array $data Column => value pairs for the SET clause.
     * @return array{string, array<string, mixed>} [SET clause, PDO params].
     */
    public static function buildSet(array $data): array {
        $parts      = [];
        $pdoParams = [];

        foreach ($data as $col => $val) {
            if ($val === null) {
                continue;
            }
            if ($val instanceof \Skim\Db\NullMarker) {
                $parts[] = "{$col} = NULL";
                continue;
            }
            $parts[]               = "{$col} = :{$col}";
            $pdoParams[":{$col}"] = $val;
        }

        return ['SET ' . implode(', ', $parts), $pdoParams];
    }

    /**
     * Builds INSERT column list and VALUES placeholders. #AI:buildValues
     *
     * @param array $data Column => value pairs for the INSERT.
     * @return array{string, array<string, mixed>} [(col1, col2) VALUES (:col1, :col2), PDO params].
     */
    public static function buildValues(array $data): array {
        $cols       = array_keys($data);
        $pdoParams = [];

        foreach ($data as $col => $val) {
            $pdoParams[":{$col}"] = $val;
        }

        return [
            '(' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')',
            $pdoParams,
        ];
    }

    /**
     * Interpolates PDO params into SQL for debug display only. #AI:interpolate
     *
     * WARNING: The result is NOT safe to execute — values are not driver-escaped.
     * Used exclusively for profiler output and debug:true mode.
     *
     * @param string $sql    Built SQL with :named placeholders.
     * @param array  $params PDO param values.
     */
    public static function interpolate(string $sql, array $params): string {
        $search  = [];
        $replace = [];

        uksort($params, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($params as $key => $val) {
            $search[]  = (string) $key;
            $replace[] = match (true) {
                $val === null  => 'NULL',
                is_bool($val)  => $val ? '1' : '0',
                is_int($val),
                is_float($val) => (string) $val,
                default        => "'" . addslashes((string) $val) . "'",
            };
        }

        return str_replace($search, $replace, $sql);
    }
}

#AI:class
#AI symbol: Skim\Db\QueryBuilder
#AI source_path: src/Db/QueryBuilder.php
#AI title: QueryBuilder
#AI description: Internal SQL template processor for query_gen %placeholder% substitution.
#AI role: SQL template processor
#AI layer: db
#AI badges: [internal; query_gen; sql-template; null-safe]
#AI intro: `QueryBuilder` processes SQL templates with `%placeholder%` tokens into executable SQL and PDO parameter arrays. It is internal to the `db` facade — not part of the public SKIM API. Unused placeholders are stripped silently, enabling dynamic queries without conditionals.
#AI lifecycle: stateless — called per query by Db:: methods
#AI fallback: none
#AI test_seam: use Db::query(..., debug: true) to inspect generated SQL
#AI invariants: [unused %placeholders% are stripped silently; null values in %set% skip the column; NullMarker in %set% produces literal NULL; limit/offset are inlined as int (not PDO-bound); null :named params are excluded from PDO array]
#AI core_behaviors: [build() processes placeholders in fixed order: set, values, where, order_by, group_by, limit, offset; buildWhere() supports flat and nested and/or structures; interpolate() sorts by key length to avoid partial replacements]
#AI warnings: [interpolate() output is NOT safe to execute — for debug display only]
#AI notes: This class is internal. Application code should use Db::query/val/row/all which delegate to QueryBuilder.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [build; buildWhere; buildSet; buildValues; interpolate]
#AI config_reads: []
#AI non_goals: [Not a query builder ORM — it is a template pre-processor; Does not validate SQL syntax; Does not escape identifiers]
#AI side_effects: []
#AI flow: Db::method() -> QueryBuilder::build(sql, params) -> [built_sql, pdoParams] -> PDO prepare/execute
#AI lifecycle_steps: [Db::query/val/row/all(); -> QueryBuilder::build(sql, params); -> process %set%/%values%/%where%/%order_by%/%group_by%/%limit%/%offset%; -> strip unused placeholders; -> collect :named params; -> return [sql, pdoParams]]
#AI section_order: [Core Processing; Clause Builders; Debug]
#AI architectural_notes: QueryBuilder is the engine behind query_gen. It processes templates in a fixed order to avoid key conflicts between %set% and %where% params.

#AI:build
#AI group: Core Processing
#AI frequency: internal
#AI signature: public static function build(string $sql, array $params): array
#AI contract: Processes a SQL template with %placeholders% into executable SQL and PDO params. Unused placeholders are stripped. Null :named params are excluded.
#AI param_details: [{name: $sql | type: string | required: true | desc: SQL template with %placeholders%.}; {name: $params | type: array | required: true | desc: Placeholder values and :named params.}]
#AI return_detail: {type: array{string, array} | desc: [built SQL, PDO params] ready for PDO::prepare + execute.}

#AI:buildWhere
#AI group: Clause Builders
#AI frequency: internal
#AI signature: public static function buildWhere(array $conditions): string
#AI contract: Builds a WHERE clause from flat or nested and/or conditions. Returns empty string when conditions are empty.
#AI param_details: [{name: $conditions | type: array | required: true | desc: Flat list ['a = :a'] or nested ['or' => [...], 'and' => [...]].}]
#AI return_detail: {type: string | desc: WHERE clause body (without WHERE keyword) or empty string.}

#AI:buildSet
#AI group: Clause Builders
#AI frequency: internal
#AI signature: public static function buildSet(array $data): array
#AI contract: Builds a SET clause for UPDATE. Null skips the column, NullMarker forces SET col = NULL.
#AI param_details: [{name: $data | type: array | required: true | desc: Column => value pairs.}]
#AI return_detail: {type: array{string, array} | desc: [SET clause string, PDO params].}

#AI:buildValues
#AI group: Clause Builders
#AI frequency: internal
#AI signature: public static function buildValues(array $data): array
#AI contract: Builds INSERT column list and VALUES placeholders.
#AI param_details: [{name: $data | type: array | required: true | desc: Column => value pairs.}]
#AI return_detail: {type: array{string, array} | desc: [(col1, col2) VALUES (:col1, :col2), PDO params].}

#AI:interpolate
#AI group: Debug
#AI frequency: internal
#AI signature: public static function interpolate(string $sql, array $params): string
#AI contract: Substitutes PDO params into SQL for debug display. NOT safe to execute — values are not driver-escaped.
#AI param_details: [{name: $sql | type: string | required: true | desc: Built SQL with :named placeholders.}; {name: $params | type: array | required: true | desc: PDO param values.}]
#AI return_detail: {type: string | desc: Human-readable SQL with values substituted.}
#AI warnings: [Output is NOT safe to execute — for profiler and debug:true display only]
