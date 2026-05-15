<?php declare(strict_types=1);

namespace skim\helpers;

// Array utility helper — thin wrappers and common patterns.
// arr::find() wraps native array_find() (PHP 8.4+) with a key=>value shorthand.
// arr::first() / arr::last() wrap native array_first() / array_last() (PHP 8.5+).
final class arr {
    /**
     * @ai-contract finds first element matching $criteria (key=>value shorthand or callable)
     * @ai-contract wraps PHP 8.4 array_find() — use array_find() directly for complex predicates
     * @ai-contract returns null if no match found
     */
    public static function find(array|callable $criteria, array $items): mixed {
        if (is_callable($criteria)) {
            return array_find($items, $criteria);
        }
        [$key, $val] = [array_key_first($criteria), array_values($criteria)[0]];
        return array_find($items, fn($item) => (is_array($item) ? $item[$key] : $item->$key) === $val);
    }

    /**
     * @ai-contract returns all elements matching $criteria — array_find() finds only first
     */
    public static function find_all(array|callable $criteria, array $items): array {
        if (is_callable($criteria)) {
            return array_values(array_filter($items, $criteria));
        }
        [$key, $val] = [array_key_first($criteria), array_values($criteria)[0]];
        return array_values(array_filter($items, fn($item) => (is_array($item) ? $item[$key] : $item->$key) === $val));
    }

    /**
     * @ai-contract re-indexes array by column value: [['id'=>5,'name'=>'J']] → [5 => ['id'=>5,'name'=>'J']]
     * @ai-contract last-write-wins on key collision
     */
    public static function map_by(string $key, array $items): array {
        $result = [];
        foreach ($items as $item) {
            $k          = is_array($item) ? $item[$key] : $item->$key;
            $result[$k] = $item;
        }
        return $result;
    }

    /**
     * @ai-contract maps two columns into key→value pairs: map_col('id','name',$rows) → [5=>'John']
     */
    public static function map_col(string $key, string $val, array $items): array {
        $result = [];
        foreach ($items as $item) {
            $k          = is_array($item) ? $item[$key] : $item->$key;
            $v          = is_array($item) ? $item[$val] : $item->$val;
            $result[$k] = $v;
        }
        return $result;
    }

    /**
     * @ai-contract extracts single column into a flat list: pluck('name',$rows) → ['John','Jane']
     */
    public static function pluck(string $key, array $items): array {
        return array_column($items, $key);
    }

    /**
     * @ai-contract filters items where $col === $val, returns re-indexed array
     */
    public static function filter_by(string $col, mixed $val, array $items): array {
        return array_values(array_filter($items, fn($item) => (is_array($item) ? $item[$col] : $item->$col) === $val));
    }

    /**
     * @ai-contract returns first element, null if empty — wraps PHP 8.5 array_first()
     */
    public static function first(array $items): mixed {
        return array_first($items);
    }

    /**
     * @ai-contract returns last element, null if empty — wraps PHP 8.5 array_last()
     */
    public static function last(array $items): mixed {
        return array_last($items);
    }

    /**
     * @ai-contract double-key index: [row['user_id']][ row['type']] = row
     */
    public static function map_nested(string $key1, string $key2, array $items): array {
        $result = [];
        foreach ($items as $item) {
            $k1 = is_array($item) ? $item[$key1] : $item->$key1;
            $k2 = is_array($item) ? $item[$key2] : $item->$key2;
            $result[$k1][$k2] = $item;
        }
        return $result;
    }

    /**
     * @ai-contract triple-key index: result[k1][k2][] = row (appends, not overwrites)
     */
    public static function map_keys(string $key1, string $key2, array $items): array {
        $result = [];
        foreach ($items as $item) {
            $k1 = is_array($item) ? $item[$key1] : $item->$key1;
            $k2 = is_array($item) ? $item[$key2] : $item->$key2;
            $result[$k1][$k2][] = $item;
        }
        return $result;
    }

    /**
     * @ai-contract normalizes numeric values so they sum to 100 (integer division, remainder added to last)
     */
    public static function normalize100(array $values): array {
        $sum = array_sum($values);
        if ($sum === 0) {
            return $values;
        }
        $result    = [];
        $remaining = 100;
        $keys      = array_keys($values);
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $result[$key] = $remaining;
            } else {
                $normalized   = (int) round($values[$key] / $sum * 100);
                $result[$key] = $normalized;
                $remaining   -= $normalized;
            }
        }
        return $result;
    }

    /**
     * @ai-contract picks a key at random with weights: ['red'=>80,'blue'=>20] → 'red' 80% of the time
     */
    public static function weighted_pick(array $weights): string|int {
        $rand = random_int(1, (int) array_sum($weights));
        $sum  = 0;
        foreach ($weights as $key => $weight) {
            $sum += $weight;
            if ($rand <= $sum) {
                return $key;
            }
        }
        return array_key_last($weights);
    }

    /**
     * @ai-contract returns human-readable string dump of an array (no HTML, for CLI/logs)
     */
    public static function to_string(array $data, int $depth = 0): string {
        $indent = str_repeat('  ', $depth);
        $out    = "[\n";
        foreach ($data as $k => $v) {
            $out .= $indent . '  ' . var_export($k, true) . ' => ';
            $out .= is_array($v) ? self::to_string($v, $depth + 1) : var_export($v, true) . ",\n";
        }
        return $out . $indent . "],\n";
    }
}
