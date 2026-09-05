<?php declare(strict_types=1);

namespace Skim\Helpers;

/**
 * Array utility methods — find, index, pluck, and common collection patterns. #AI:class
 *
 * Use for common array transformations across controllers, models, and services.
 * Wraps PHP 8.4+ array_find() with key=>value shorthand and provides multi-key
 * indexing for eager-loading relation maps.
 *
 * Example:
 *   Arr::find(['id' => 5], $users);           // first user with id=5
 *   Arr::mapBy('id', $users);                // [5 => user, 6 => user, ...]
 *   Arr::pluck('name', $users);               // ['John', 'Jane', ...]
 *
 * Testing: All methods are pure functions — test directly, no mocking needed.
 *
 * #AI:class
 */
final class Arr {
    /**
     * Finds the first element matching a key=>value pair or callable. #AI:find
     *
     * Wraps PHP 8.4 array_find() with a shorthand for column matching.
     *
     * @param array|callable $criteria ['col' => 'val'] pair or predicate function.
     * @param array          $items    Array to search.
     */
    public static function find(array|callable $criteria, array $items): mixed {
        if (is_callable($criteria)) {
            return array_find($items, $criteria);
        }
        [$key, $val] = [array_key_first($criteria), array_values($criteria)[0]];
        return array_find($items, fn($item) => (is_array($item) ? $item[$key] : $item->$key) === $val);
    }

    /**
     * Returns all elements matching a key=>value pair or callable. #AI:findAll
     *
     * Unlike find() which returns only the first match, this returns all matches.
     *
     * @param array|callable $criteria ['col' => 'val'] pair or predicate function.
     * @param array          $items    Array to filter.
     */
    public static function findAll(array|callable $criteria, array $items): array {
        if (is_callable($criteria)) {
            return array_values(array_filter($items, $criteria));
        }
        [$key, $val] = [array_key_first($criteria), array_values($criteria)[0]];
        return array_values(array_filter($items, fn($item) => (is_array($item) ? $item[$key] : $item->$key) === $val));
    }

    /**
     * Re-indexes an array by a column value — last-write-wins on collision. #AI:mapBy
     *
     * Example:
     *   Arr::mapBy('id', [['id'=>5,'name'=>'J']]); // [5 => ['id'=>5,'name'=>'J']]
     *
     * @param string $key   Column to use as the new key.
     * @param array  $items Array of arrays or objects.
     */
    public static function mapBy(string $key, array $items): array {
        $result = [];
        foreach ($items as $item) {
            $k          = is_array($item) ? $item[$key] : $item->$key;
            $result[$k] = $item;
        }
        return $result;
    }

    /**
     * Maps two columns into key→value pairs. #AI:mapCol
     *
     * Example:
     *   Arr::mapCol('id', 'name', $rows); // [5 => 'John', 6 => 'Jane']
     *
     * @param string $key   Column for the resulting array key.
     * @param string $val   Column for the resulting array value.
     * @param array  $items Array of arrays or objects.
     */
    public static function mapCol(string $key, string $val, array $items): array {
        $result = [];
        foreach ($items as $item) {
            $k = is_array($item) ? $item[$key] : $item->$key;
            $v = is_array($item) ? $item[$val] : $item->$val;
            $result[$k] = $v;
        }
        return $result;
    }

    /**
     * Extracts a single column into a flat list. #AI:pluck
     *
     * Example:
     *   Arr::pluck('name', $users); // ['John', 'Jane', ...]
     *
     * @param string $key   Column to extract.
     * @param array  $items Array of arrays.
     */
    public static function pluck(string $key, array $items): array {
        return array_column($items, $key);
    }

    /**
     * Filters items where a column equals a value, re-indexed. #AI:filterBy
     *
     * @param string $col   Column to match.
     * @param mixed  $val   Value to match (strict ===).
     * @param array  $items Array of arrays or objects.
     */
    public static function filterBy(string $col, mixed $val, array $items): array {
        return array_values(array_filter($items, fn($item) => (is_array($item) ? $item[$col] : $item->$col) === $val));
    }

    /**
     * Returns the first element, or null if empty. #AI:first
     *
     * Wraps PHP 8.5 array_first().
     *
     * @param array $items Input array.
     */
    public static function first(array $items): mixed {
        return array_first($items);
    }

    /**
     * Returns the last element, or null if empty. #AI:last
     *
     * Wraps PHP 8.5 array_last().
     *
     * @param array $items Input array.
     */
    public static function last(array $items): mixed {
        return array_last($items);
    }

    /**
     * Creates a double-key index: result[k1][k2] = item. #AI:mapNested
     *
     * Last-write-wins on key collision at the second level.
     *
     * @param string $key1  First-level key column.
     * @param string $key2  Second-level key column.
     * @param array  $items Array of arrays or objects.
     */
    public static function mapNested(string $key1, string $key2, array $items): array {
        $result = [];
        foreach ($items as $item) {
            $k1 = is_array($item) ? $item[$key1] : $item->$key1;
            $k2 = is_array($item) ? $item[$key2] : $item->$key2;
            $result[$k1][$k2] = $item;
        }
        return $result;
    }

    /**
     * Creates a triple-key index: result[k1][k2][] = item (appends). #AI:mapKeys
     *
     * Unlike map_nested, appends to an array at the second level instead of overwriting.
     *
     * @param string $key1  First-level key column.
     * @param string $key2  Second-level key column.
     * @param array  $items Array of arrays or objects.
     */
    public static function mapKeys(string $key1, string $key2, array $items): array {
        $result = [];
        foreach ($items as $item) {
            $k1 = is_array($item) ? $item[$key1] : $item->$key1;
            $k2 = is_array($item) ? $item[$key2] : $item->$key2;
            $result[$k1][$k2][] = $item;
        }
        return $result;
    }

    /**
     * Normalizes numeric values so they sum to exactly 100. #AI:normalize100
     *
     * Uses integer rounding with remainder added to the last element to
     * guarantee the sum is exactly 100.
     *
     * @param array $values Associative array of numeric values.
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
     * Picks a random key weighted by values. #AI:weightedPick
     *
     * Example:
     *   Arr::weightedPick(['red' => 80, 'blue' => 20]); // 'red' ~80% of the time
     *
     * @param array $weights Associative array of key => weight.
     */
    public static function weightedPick(array $weights): string|int {
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
     * Returns a human-readable string dump of an array for CLI/logs. #AI:toString
     *
     * No HTML output — safe for terminal and log file use.
     *
     * @param array $data  Array to dump.
     * @param int   $depth Current indentation depth (internal recursion).
     */
    public static function toString(array $data, int $depth = 0): string {
        $indent = str_repeat('  ', $depth);
        $out    = "[\n";
        foreach ($data as $k => $v) {
            $out .= $indent . '  ' . var_export($k, true) . ' => ';
            $out .= is_array($v) ? self::toString($v, $depth + 1) : var_export($v, true) . ",\n";
        }
        return $out . $indent . "],\n";
    }
}

#AI:class
#AI symbol: Skim\Helpers\Arr
#AI source_path: src/Helpers/Arr.php
#AI title: arr
#AI description: Array utility methods for finding, indexing, plucking, and common collection patterns.
#AI role: array utility helper
#AI layer: helpers
#AI badges: [helper; array; stateless; collections]
#AI intro: `arr` provides common array transformations used across controllers, models, and services. It wraps PHP 8.4+ `array_find()` with key=>value shorthand and provides multi-key indexing methods essential for eager-loading relation maps.
#AI lifecycle: stateless — all methods are pure functions
#AI fallback: none
#AI test_seam: test directly — no mocking needed
#AI invariants: [all methods accept both arrays and objects as items; mapBy uses last-write-wins on key collision; mapKeys appends (not overwrites) at second level; normalize100 guarantees sum === 100]
#AI core_behaviors: [find/findAll support both callable predicates and key=>value shorthand; mapBy/mapCol/mapNested/mapKeys work with arrays and objects; pluck wraps array_column]
#AI warnings: []
#AI notes: find() wraps PHP 8.4 array_find(); first()/last() wrap PHP 8.5 array_first()/array_last().
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [find; findAll; mapBy; mapCol; pluck; filterBy; first; last; mapNested; mapKeys; normalize100; weightedPick; toString]
#AI config_reads: []
#AI non_goals: [Does not provide lazy evaluation; Does not support nested dot-notation keys]
#AI side_effects: []
#AI flow: caller -> Arr::method() -> pure return value
#AI lifecycle_steps: [caller invokes static method; -> pure computation; -> return value]
#AI section_order: [Search; Indexing; Extraction; Navigation; Aggregation; Debug]
#AI architectural_notes: Thin utility layer. All methods work with both array and object items for flexibility with DB rows and model instances.

#AI:find
#AI group: Search
#AI frequency: high
#AI signature: public static function find(array|callable $criteria, array $items): mixed
#AI contract: Finds the first element matching a key=>value pair or callable predicate. Returns null if no match.
#AI param_details: [{name: $criteria | type: array|callable | required: true | desc: ['col' => 'val'] shorthand or predicate function.}; {name: $items | type: array | required: true | desc: Array to search.}]
#AI return_detail: {type: mixed | desc: First matching element or null.}

#AI:findAll
#AI group: Search
#AI frequency: medium
#AI signature: public static function findAll(array|callable $criteria, array $items): array
#AI contract: Returns all elements matching a key=>value pair or callable. Re-indexes the result.
#AI param_details: [{name: $criteria | type: array|callable | required: true | desc: ['col' => 'val'] shorthand or predicate function.}; {name: $items | type: array | required: true | desc: Array to filter.}]
#AI return_detail: {type: array | desc: All matching elements, re-indexed.}

#AI:mapBy
#AI group: Indexing
#AI frequency: high
#AI signature: public static function mapBy(string $key, array $items): array
#AI contract: Re-indexes an array by a column value. Last-write-wins on key collision.
#AI param_details: [{name: $key | type: string | required: true | desc: Column to use as the new key.}; {name: $items | type: array | required: true | desc: Array of arrays or objects.}]
#AI return_detail: {type: array | desc: Associative array keyed by column value.}

#AI:mapCol
#AI group: Indexing
#AI frequency: high
#AI signature: public static function mapCol(string $key, string $val, array $items): array
#AI contract: Maps two columns into key→value pairs.
#AI param_details: [{name: $key | type: string | required: true | desc: Column for resulting key.}; {name: $val | type: string | required: true | desc: Column for resulting value.}; {name: $items | type: array | required: true | desc: Array of arrays or objects.}]
#AI return_detail: {type: array | desc: Key→value associative array.}

#AI:pluck
#AI group: Extraction
#AI frequency: high
#AI signature: public static function pluck(string $key, array $items): array
#AI contract: Extracts a single column into a flat list. Wraps array_column().
#AI param_details: [{name: $key | type: string | required: true | desc: Column to extract.}; {name: $items | type: array | required: true | desc: Array of arrays.}]
#AI return_detail: {type: array | desc: Flat list of column values.}

#AI:filterBy
#AI group: Search
#AI frequency: medium
#AI signature: public static function filterBy(string $col, mixed $val, array $items): array
#AI contract: Filters items where column equals value (strict ===). Returns re-indexed array.
#AI param_details: [{name: $col | type: string | required: true | desc: Column to match.}; {name: $val | type: mixed | required: true | desc: Value to match.}; {name: $items | type: array | required: true | desc: Array to filter.}]
#AI return_detail: {type: array | desc: Filtered and re-indexed array.}

#AI:first
#AI group: Navigation
#AI frequency: medium
#AI signature: public static function first(array $items): mixed
#AI contract: Returns the first element or null if empty. Wraps PHP 8.5 array_first().
#AI param_details: [{name: $items | type: array | required: true | desc: Input array.}]
#AI return_detail: {type: mixed | desc: First element or null.}

#AI:last
#AI group: Navigation
#AI frequency: medium
#AI signature: public static function last(array $items): mixed
#AI contract: Returns the last element or null if empty. Wraps PHP 8.5 array_last().
#AI param_details: [{name: $items | type: array | required: true | desc: Input array.}]
#AI return_detail: {type: mixed | desc: Last element or null.}

#AI:mapNested
#AI group: Indexing
#AI frequency: medium
#AI signature: public static function mapNested(string $key1, string $key2, array $items): array
#AI contract: Creates a double-key index: result[k1][k2] = item. Last-write-wins at second level.
#AI param_details: [{name: $key1 | type: string | required: true | desc: First-level key column.}; {name: $key2 | type: string | required: true | desc: Second-level key column.}; {name: $items | type: array | required: true | desc: Array of arrays or objects.}]
#AI return_detail: {type: array | desc: Two-level nested associative array.}

#AI:mapKeys
#AI group: Indexing
#AI frequency: medium
#AI signature: public static function mapKeys(string $key1, string $key2, array $items): array
#AI contract: Creates a triple-key index: result[k1][k2][] = item. Appends at second level (not overwrites).
#AI param_details: [{name: $key1 | type: string | required: true | desc: First-level key column.}; {name: $key2 | type: string | required: true | desc: Second-level key column.}; {name: $items | type: array | required: true | desc: Array of arrays or objects.}]
#AI return_detail: {type: array | desc: Two-level nested array with appended lists.}

#AI:normalize100
#AI group: Aggregation
#AI frequency: low
#AI signature: public static function normalize100(array $values): array
#AI contract: Normalizes numeric values so they sum to exactly 100. Remainder from rounding is added to the last element.
#AI param_details: [{name: $values | type: array | required: true | desc: Associative array of numeric values.}]
#AI return_detail: {type: array | desc: Normalized values summing to 100.}

#AI:weightedPick
#AI group: Aggregation
#AI frequency: low
#AI signature: public static function weightedPick(array $weights): string|int
#AI contract: Picks a random key weighted by values. Uses random_int() for cryptographic randomness.
#AI param_details: [{name: $weights | type: array | required: true | desc: Associative array of key => weight.}]
#AI return_detail: {type: string|int | desc: The randomly selected key.}

#AI:toString
#AI group: Debug
#AI frequency: low
#AI signature: public static function toString(array $data, int $depth = 0): string
#AI contract: Returns a human-readable string dump of an array. No HTML — safe for CLI and log files.
#AI param_details: [{name: $data | type: array | required: true | desc: Array to dump.}; {name: $depth | type: int | required: false | desc: Internal recursion depth.}]
#AI return_detail: {type: string | desc: Formatted string representation.}
