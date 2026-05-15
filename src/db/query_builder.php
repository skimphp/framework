<?php declare(strict_types=1);

namespace skim\db;

// Internal SQL template builder — not part of the public SKIM API.
// Used exclusively by db::query(), db::val(), db::row(), db::all().
//
// Handles substitution of %placeholders% in SQL templates:
//   %where%    → WHERE clause from flat or nested and/or conditions
//   %set%      → SET col = :col, ... for UPDATE (skips null, handles null_marker)
//   %values%   → (col1, col2) VALUES (:col1, :col2) for INSERT
//   %order_by% → ORDER BY ...
//   %group_by% → GROUP BY ...
//   %limit%    → LIMIT n   (inlined as int — PDO binding unreliable for LIMIT in all drivers)
//   %offset%   → OFFSET n  (same reason)
//
// Unused placeholders are stripped silently — build dynamic queries without conditionals.
final class query_builder {
    /**
     * @ai-contract input   $sql string with %placeholders%, $params array (see module docs)
     * @ai-contract returns [string $sql, array $pdo_params] ready for PDO::prepare + execute
     * @ai-contract null-safe unused %placeholders% stripped, never throws on absent keys
     * @ai-contract null-safe null PDO param values are excluded from the prepared statement
     *
     * @return array{string, array<string, mixed>}
     */
    public static function build(string $sql, array $params): array {
        $pdo_params = [];

        // %set% — UPDATE SET col = :col, ... (order matters: before %where% to avoid key conflicts)
        if (isset($params['set'])) {
            [$clause, $extra] = self::build_set($params['set']);
            $sql = str_replace('%set%', $clause, $sql);
            $pdo_params += $extra;
            unset($params['set']);
        }

        // %values% — INSERT (col1, col2) VALUES (:col1, :col2)
        if (isset($params['values'])) {
            [$clause, $extra] = self::build_values($params['values']);
            $sql = str_replace('%values%', $clause, $sql);
            $pdo_params += $extra;
            unset($params['values']);
        }

        // %where% — removed entirely when conditions resolve to empty string
        if (isset($params['where'])) {
            $clause = self::build_where($params['where']);
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

        // limit/offset inlined as int — PDO param binding for LIMIT/OFFSET fails in some MySQL configs
        if (isset($params['limit'])) {
            $sql = str_replace('%limit%', 'LIMIT ' . (int) $params['limit'], $sql);
            unset($params['limit']);
        }

        if (isset($params['offset'])) {
            $sql = str_replace('%offset%', 'OFFSET ' . (int) $params['offset'], $sql);
            unset($params['offset']);
        }

        // Strip any remaining unused %placeholders% (optional clauses not provided)
        $sql = (string) preg_replace('/%\w+%/', '', $sql);
        $sql = (string) preg_replace('/\s{2,}/', ' ', trim($sql));

        // Remaining :name => value pairs become PDO prepared statement params
        foreach ($params as $key => $val) {
            if (str_starts_with((string) $key, ':') && $val !== null) {
                $pdo_params[$key] = $val;
            }
        }

        return [$sql, $pdo_params];
    }

    /**
     * Build WHERE clause from flat or nested (and/or) conditions.
     *
     * @ai-contract input   flat list ['a = :a', 'b = :b'] → joined with AND
     * @ai-contract input   nested ['or' => [[...], [...]], 'and' => [[...], [...]]]
     * @ai-contract returns empty string when $conditions is empty → %where% removed from SQL
     * @ai-contract null-safe empty/null/false entries within a group are silently filtered
     *
     * Nested format:
     *   'or' groups  → each group AND-joined, groups OR-joined, whole block wrapped in ()
     *   'and' groups → each group AND-joined, each wrapped in (), blocks AND-joined
     *   Combined:      (or-block) AND (and-group1) AND (and-group2)
     */
    public static function build_where(array $conditions): string {
        if ($conditions === []) {
            return '';
        }

        // Flat list: ['status = :s', 'role = :r']
        if (array_is_list($conditions)) {
            return implode(' AND ', array_filter($conditions));
        }

        $parts = [];

        // 'or' key: inner groups are AND-joined, then OR-joined, whole result wrapped in ()
        if (!empty($conditions['or'])) {
            $or_groups = [];
            foreach ($conditions['or'] as $group) {
                $filtered = array_filter((array) $group);
                if ($filtered !== []) {
                    $or_groups[] = '(' . implode(' AND ', $filtered) . ')';
                }
            }
            if ($or_groups !== []) {
                $parts[] = '(' . implode(' OR ', $or_groups) . ')';
            }
        }

        // 'and' key: each group AND-joined, each wrapped in (), all joined with AND
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
     * Build SET clause for UPDATE.
     *
     * @ai-contract null-safe null → column skipped (partial update, don't clobber DB value)
     * @ai-contract null_marker instance → SET col = NULL (explicit erasure via db::null())
     * @return array{string, array<string, mixed>}
     */
    public static function build_set(array $data): array {
        $parts      = [];
        $pdo_params = [];

        foreach ($data as $col => $val) {
            if ($val === null) {
                continue;
            }
            if ($val instanceof null_marker) {
                $parts[] = "{$col} = NULL";
                continue;
            }
            $parts[]               = "{$col} = :{$col}";
            $pdo_params[":{$col}"] = $val;
        }

        return ['SET ' . implode(', ', $parts), $pdo_params];
    }

    /**
     * Build INSERT column list and VALUES placeholders.
     *
     * @ai-contract returns "(col1, col2) VALUES (:col1, :col2)" string + matching pdo_params
     * @return array{string, array<string, mixed>}
     */
    public static function build_values(array $data): array {
        $cols       = array_keys($data);
        $pdo_params = [];

        foreach ($data as $col => $val) {
            $pdo_params[":{$col}"] = $val;
        }

        return [
            '(' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')',
            $pdo_params,
        ];
    }

    /**
     * Interpolate PDO params into SQL — for debug output and profiler display only.
     * The result is NOT safe to execute — values are not driver-escaped.
     *
     * @ai-contract returns human-readable SQL string with bound values substituted
     * @ai-contract side-effect none — pure string transformation
     */
    public static function interpolate(string $sql, array $params): string {
        $search  = [];
        $replace = [];

        // Sort by key length descending to avoid partial replacements (:user_id before :user)
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
