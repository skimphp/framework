<?php declare(strict_types=1);

use Skim\Dev\Docs\Value\ExtractedMethod;
use Skim\Dev\Docs\Value\ExtractedClass;

describe('ExtractedClass::toArray', function () {

    it('includes all new fields in serialized output', function () {
        $cls = new \Skim\Dev\Docs\Value\ExtractedClass(
            className:   'Cache',
            namespace:    'Skim\\Cache',
            file:         '/app/src/Cache/Cache.php',
            summary:      'Static cache facade.',
            lifecycle:    'driver resolved lazily',
            layer:        'cache',
            owns:         ['driver instance'],
            entryPoints: ['remember', 'get', 'set'],
            configReads: ['cache.driver', 'cache.ttl'],
            invariants:   ['driver reused until reset'],
            sideEffects: ['writes static driver instance'],
            nonGoals:    ['does not expose backend-specific APIs'],
        );
        $arr = $cls->toArray();
        expect($arr)->toHaveKey('layer')
            ->and($arr)->toHaveKey('owns')
            ->and($arr)->toHaveKey('entry_points')
            ->and($arr)->toHaveKey('config_reads')
            ->and($arr)->toHaveKey('invariants')
            ->and($arr)->toHaveKey('side_effects')
            ->and($arr)->toHaveKey('non_goals')
            ->and($arr['entry_points'])->toContain('remember');
    });

    it('annotatedMethodCount counts methods with at least one annotation', function () {
        $m1 = new \Skim\Dev\Docs\Value\ExtractedMethod('get', 'public function get(): mixed', 'ns\\cls', contracts: ['returns value']);
        $m2 = new \Skim\Dev\Docs\Value\ExtractedMethod('noop', 'public function noop(): void', 'ns\\cls');
        $cls = new \Skim\Dev\Docs\Value\ExtractedClass('cls', 'ns', '/f.php', methods: [$m1, $m2]);
        expect($cls->annotatedMethodCount())->toBe(1);
    });

    it('annotatedMethodCount() returns 0 when no methods annotated', function(): void {
        $m1    = new \Skim\Dev\Docs\Value\ExtractedMethod(name: 'a', signature: '', owner: '');
        $m2    = new \Skim\Dev\Docs\Value\ExtractedMethod(name: 'b', signature: '', owner: '');
        $class = new \Skim\Dev\Docs\Value\ExtractedClass('cls', '', '', methods: [$m1, $m2]);
        expect($class->annotatedMethodCount())->toBe(0);
    });

    it('annotatedMethodCount() returns 0 for class with no methods', function(): void {
        $class = new \Skim\Dev\Docs\Value\ExtractedClass('cls', '', '');
        expect($class->annotatedMethodCount())->toBe(0);
    });

});

describe('ExtractedMethod::toArray', function () {

    it('includes all new fields in serialized output', function () {
        $m = new \Skim\Dev\Docs\Value\ExtractedMethod(
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
        $arr = $m->toArray();
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
