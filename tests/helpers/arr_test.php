<?php declare(strict_types=1);

use skim\helpers\arr;

describe('arr::map_by()', function(): void {

    test('re-indexes array by column value', function(): void {
        $input  = [['id' => 5, 'name' => 'John'], ['id' => 6, 'name' => 'Jane']];
        $result = arr::map_by('id', $input);
        expect($result)->toHaveKey(5)->toHaveKey(6);
        expect($result[5]['name'])->toBe('John');
        expect($result[6]['name'])->toBe('Jane');
    });

    test('last-write-wins on key collision', function(): void {
        $input  = [['id' => 1, 'v' => 'first'], ['id' => 1, 'v' => 'second']];
        $result = arr::map_by('id', $input);
        expect($result[1]['v'])->toBe('second');
    });

});

describe('arr::map_col()', function(): void {

    test('maps two columns into key→value pairs', function(): void {
        $rows   = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $result = arr::map_col('id', 'name', $rows);
        expect($result)->toBe([1 => 'Alice', 2 => 'Bob']);
    });

});

describe('arr::pluck()', function(): void {

    test('extracts single column into flat list', function(): void {
        $rows = [['id' => 1, 'name' => 'X'], ['id' => 2, 'name' => 'Y']];
        expect(arr::pluck('name', $rows))->toBe(['X', 'Y']);
    });

});

describe('arr::filter_by()', function(): void {

    test('returns only matching rows, re-indexed', function(): void {
        $rows   = [['s' => 'a'], ['s' => 'b'], ['s' => 'a']];
        $result = arr::filter_by('s', 'a', $rows);
        expect(count($result))->toBe(2);
        expect(array_key_exists(0, $result))->toBeTrue();
    });

});

describe('arr::find_all()', function(): void {

    test('returns all matching elements', function(): void {
        $items  = [['role' => 'admin'], ['role' => 'user'], ['role' => 'admin']];
        $result = arr::find_all(['role' => 'admin'], $items);
        expect(count($result))->toBe(2);
    });

    test('returns empty array when no match', function(): void {
        $items  = [['role' => 'user']];
        $result = arr::find_all(['role' => 'admin'], $items);
        expect($result)->toBe([]);
    });

});

describe('arr::normalize100()', function(): void {

    test('values sum to 100', function(): void {
        $result = arr::normalize100(['a' => 80, 'b' => 20]);
        expect(array_sum($result))->toBe(100);
    });

    test('handles zero sum gracefully', function(): void {
        $result = arr::normalize100(['a' => 0, 'b' => 0]);
        expect($result)->toBe(['a' => 0, 'b' => 0]);
    });

});

describe('arr::weighted_pick()', function(): void {

    test('always returns a key from the weights array', function(): void {
        $keys = ['red', 'blue', 'green'];
        for ($i = 0; $i < 50; $i++) {
            expect(arr::weighted_pick(['red' => 70, 'blue' => 20, 'green' => 10]))->toBeIn($keys);
        }
    });

});
