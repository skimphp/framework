<?php declare(strict_types=1);

use Skim\Helpers\Arr;

describe('Arr::mapBy()', function(): void {

    test('re-indexes array by column value', function(): void {
        $input  = [['id' => 5, 'name' => 'John'], ['id' => 6, 'name' => 'Jane']];
        $result = \Skim\Helpers\Arr::mapBy('id', $input);
        expect($result)->toHaveKey(5)->toHaveKey(6);
        expect($result[5]['name'])->toBe('John');
        expect($result[6]['name'])->toBe('Jane');
    });

    test('last-write-wins on key collision', function(): void {
        $input  = [['id' => 1, 'v' => 'first'], ['id' => 1, 'v' => 'second']];
        $result = \Skim\Helpers\Arr::mapBy('id', $input);
        expect($result[1]['v'])->toBe('second');
    });

});

describe('Arr::mapCol()', function(): void {

    test('maps two columns into key→value pairs', function(): void {
        $rows   = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];
        $result = \Skim\Helpers\Arr::mapCol('id', 'name', $rows);
        expect($result)->toBe([1 => 'Alice', 2 => 'Bob']);
    });

});

describe('Arr::pluck()', function(): void {

    test('extracts single column into flat list', function(): void {
        $rows = [['id' => 1, 'name' => 'X'], ['id' => 2, 'name' => 'Y']];
        expect(\Skim\Helpers\Arr::pluck('name', $rows))->toBe(['X', 'Y']);
    });

});

describe('Arr::filterBy()', function(): void {

    test('returns only matching rows, re-indexed', function(): void {
        $rows   = [['s' => 'a'], ['s' => 'b'], ['s' => 'a']];
        $result = \Skim\Helpers\Arr::filterBy('s', 'a', $rows);
        expect(count($result))->toBe(2);
        expect(array_key_exists(0, $result))->toBeTrue();
    });

});

describe('Arr::findAll()', function(): void {

    test('returns all matching elements', function(): void {
        $items  = [['role' => 'admin'], ['role' => 'user'], ['role' => 'admin']];
        $result = \Skim\Helpers\Arr::findAll(['role' => 'admin'], $items);
        expect(count($result))->toBe(2);
    });

    test('returns empty array when no match', function(): void {
        $items  = [['role' => 'user']];
        $result = \Skim\Helpers\Arr::findAll(['role' => 'admin'], $items);
        expect($result)->toBe([]);
    });

});

describe('Arr::normalize100()', function(): void {

    test('values sum to 100', function(): void {
        $result = \Skim\Helpers\Arr::normalize100(['a' => 80, 'b' => 20]);
        expect(array_sum($result))->toBe(100);
    });

    test('handles zero sum gracefully', function(): void {
        $result = \Skim\Helpers\Arr::normalize100(['a' => 0, 'b' => 0]);
        expect($result)->toBe(['a' => 0, 'b' => 0]);
    });

});

describe('Arr::weightedPick()', function(): void {

    test('always returns a key from the weights array', function(): void {
        $keys = ['red', 'blue', 'green'];
        for ($i = 0; $i < 50; $i++) {
            expect(\Skim\Helpers\Arr::weightedPick(['red' => 70, 'blue' => 20, 'green' => 10]))->toBeIn($keys);
        }
    });

});
