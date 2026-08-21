<?php declare(strict_types=1);

use skim\dev\docs\value\extracted_method;
use skim\dev\docs\value\extracted_class;

describe('extracted_class::to_array', function () {

    it('includes all new fields in serialized output', function () {
        $cls = new extracted_class(
            class_name:   'cache',
            namespace:    'skim\\cache',
            file:         '/app/src/cache/cache.php',
            summary:      'Static cache facade.',
            lifecycle:    'driver resolved lazily',
            layer:        'cache',
            owns:         ['driver instance'],
            entry_points: ['remember', 'get', 'set'],
            config_reads: ['cache.driver', 'cache.ttl'],
            invariants:   ['driver reused until reset'],
            side_effects: ['writes static driver instance'],
            non_goals:    ['does not expose backend-specific APIs'],
        );
        $arr = $cls->to_array();
        expect($arr)->toHaveKey('layer')
            ->and($arr)->toHaveKey('owns')
            ->and($arr)->toHaveKey('entry_points')
            ->and($arr)->toHaveKey('config_reads')
            ->and($arr)->toHaveKey('invariants')
            ->and($arr)->toHaveKey('side_effects')
            ->and($arr)->toHaveKey('non_goals')
            ->and($arr['entry_points'])->toContain('remember');
    });

    it('annotated_method_count counts methods with at least one annotation', function () {
        $m1 = new extracted_method('get', 'public function get(): mixed', 'ns\\cls', contracts: ['returns value']);
        $m2 = new extracted_method('noop', 'public function noop(): void', 'ns\\cls');
        $cls = new extracted_class('cls', 'ns', '/f.php', methods: [$m1, $m2]);
        expect($cls->annotated_method_count())->toBe(1);
    });

    it('annotated_method_count() returns 0 when no methods annotated', function(): void {
        $m1    = new extracted_method(name: 'a', signature: '', owner: '');
        $m2    = new extracted_method(name: 'b', signature: '', owner: '');
        $class = new extracted_class('cls', '', '', methods: [$m1, $m2]);
        expect($class->annotated_method_count())->toBe(0);
    });

    it('annotated_method_count() returns 0 for class with no methods', function(): void {
        $class = new extracted_class('cls', '', '');
        expect($class->annotated_method_count())->toBe(0);
    });

});

describe('extracted_method::to_array', function () {

    it('includes all new fields in serialized output', function () {
        $m = new extracted_method(
            name:         'remember',
            signature:    'public static function remember(string $key, int $ttl, callable $default): mixed',
            owner:        'skim\\cache\\cache',
            group:        'Read API',
            frequency:    'high',
            contracts:    ['returns existing value or stores callback result on miss'],
            inputs:       ['key is the backend lookup key'],
            returns:      'cached or computed value',
            calls:        ['has', 'get', 'set'],
            warnings:     [],
            examples:     ['cache miss computes and stores value'],
        );
        $arr = $m->to_array();
        expect($arr)->toHaveKey('group')
            ->and($arr)->toHaveKey('frequency')
            ->and($arr)->toHaveKey('inputs')
            ->and($arr)->toHaveKey('returns')
            ->and($arr)->toHaveKey('calls')
            ->and($arr)->toHaveKey('warnings')
            ->and($arr)->toHaveKey('examples')
            ->and($arr['group'])->toBe('Read API')
            ->and($arr['calls'])->toContain('has');
    });

});
