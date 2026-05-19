<?php declare(strict_types=1);

use skim\dev\docs\value\extracted_method;
use skim\dev\docs\value\extracted_class;

describe('extracted_method', function(): void {

    test('to_array() contains all fields', function(): void {
        $method = new extracted_method(
            name:         'find',
            signature:    'public function find(int $id): ?user',
            owner:        'skim\\db\\model',
            contracts:    ['returns null when not found'],
            invariants:   ['id is always positive'],
            non_goals:    ['does not cache'],
            side_effects: ['queries the database'],
            lifecycle:    'per-request',
            perf:         'O(1)',
            throws:       ['db_exception'],
        );

        $arr = $method->to_array();
        expect($arr['name'])->toBe('find');
        expect($arr['signature'])->toBe('public function find(int $id): ?user');
        expect($arr['owner'])->toBe('skim\\db\\model');
        expect($arr['contracts'])->toBe(['returns null when not found']);
        expect($arr['invariants'])->toBe(['id is always positive']);
        expect($arr['non_goals'])->toBe(['does not cache']);
        expect($arr['side_effects'])->toBe(['queries the database']);
        expect($arr['lifecycle'])->toBe('per-request');
        expect($arr['perf'])->toBe('O(1)');
        expect($arr['throws'])->toBe(['db_exception']);
    });

    test('arrays default to empty when not provided', function(): void {
        $method = new extracted_method(name: 'foo', signature: 'public function foo()', owner: 'bar');
        expect($method->contracts)->toBe([]);
        expect($method->invariants)->toBe([]);
        expect($method->non_goals)->toBe([]);
        expect($method->side_effects)->toBe([]);
        expect($method->throws)->toBe([]);
        expect($method->lifecycle)->toBe('');
        expect($method->perf)->toBe('');
    });

});

describe('extracted_class', function(): void {

    test('to_array() serializes all fields including methods', function(): void {
        $method = new extracted_method(name: 'handle', signature: 'public function handle(): int', owner: 'my_command');
        $class  = new extracted_class(
            class_name: 'my_command',
            namespace:  'app\\commands',
            file:       '/app/commands/my_command.php',
            summary:    'Does the thing.',
            lifecycle:  'cli',
            owner:      'platform',
            methods:    [$method],
        );

        $arr = $class->to_array();
        expect($arr['class_name'])->toBe('my_command');
        expect($arr['namespace'])->toBe('app\\commands');
        expect($arr['file'])->toBe('/app/commands/my_command.php');
        expect($arr['summary'])->toBe('Does the thing.');
        expect($arr['methods'])->toHaveCount(1);
        expect($arr['methods'][0]['name'])->toBe('handle');
    });

    test('annotated_method_count() counts methods with at least one @ai.* tag', function(): void {
        $annotated   = new extracted_method(name: 'a', signature: '', owner: '', contracts: ['contract one']);
        $unannotated = new extracted_method(name: 'b', signature: '', owner: '');
        $class       = new extracted_class('cls', '', '', methods: [$annotated, $unannotated]);

        expect($class->annotated_method_count())->toBe(1);
    });

    test('annotated_method_count() returns 0 when no methods annotated', function(): void {
        $m1    = new extracted_method(name: 'a', signature: '', owner: '');
        $m2    = new extracted_method(name: 'b', signature: '', owner: '');
        $class = new extracted_class('cls', '', '', methods: [$m1, $m2]);
        expect($class->annotated_method_count())->toBe(0);
    });

    test('annotated_method_count() returns 0 for class with no methods', function(): void {
        $class = new extracted_class('cls', '', '');
        expect($class->annotated_method_count())->toBe(0);
    });

});
