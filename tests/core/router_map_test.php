<?php declare(strict_types=1);

use skim\core\app;
use skim\core\router;
use skim\core\route_entry;

describe('router::map() — Slim/Laravel-style alias', function(): void {

    test('dispatches a single-method string map() to the registered handler', function(): void {
        $handler = fn(): string => 'a';
        $r = new router();
        $r->map('GET', '/a', $handler);

        $result = $r->dispatch('GET', '/a');

        expect($result)->toBeArray()
            ->toHaveKey('handler')
            ->and($result['handler'])->toBe($handler);
    });

    test('registers every method from an array — POST dispatches to the same handler', function(): void {
        $r = new router();
        $r->map(['GET', 'POST'], '/b', fn(): string => 'b');

        expect($r->dispatch('GET', '/b'))->toBeArray();
        expect($r->dispatch('POST', '/b'))->toBeArray();
        expect($r->dispatch('PUT', '/b'))->toBeFalse();
    });

    test('returns a chainable route_entry — middleware() applies to the registered route', function(): void {
        $r = new router();
        $entry = $r->map('GET', '/c', fn() => null)->middleware('SomeMiddleware');

        expect($entry)->toBeInstanceOf(route_entry::class);

        $result = $r->dispatch('GET', '/c');
        expect($result['middleware'])->toContain('SomeMiddleware');
    });

    test('named route registered via map() is resolvable through the app container url() helper', function(): void {
        $app = app::test_instance();
        $app->router->map('GET', '/d', fn() => null)->name('d');

        $generated = $app->router->build_url('d');
        expect($generated)->toBe('/d');
    });

    test('return type of map() is route_entry (static type contract)', function(): void {
        $r = new router();

        $return = $r->map('GET', '/e', fn() => null);

        expect($return)->toBeInstanceOf(route_entry::class);
    });

    test('map() behaves identically to add() for the same inputs', function(): void {
        $r1 = new router();
        $r1->map(['GET', 'POST'], '/x', fn(): string => 'x');

        $r2 = new router();
        $r2->add(['GET', 'POST'], '/x', fn(): string => 'x');

        $d1 = $r1->dispatch('GET', '/x');
        $d2 = $r2->dispatch('GET', '/x');

        expect($d1)->toBeArray();
        expect($d2)->toBeArray();
        expect(($d1['handler'])())->toBe(($d2['handler'])());
        expect($r1->dispatch('GET', '/x')['pattern'])
            ->toBe($r2->dispatch('GET', '/x')['pattern']);
    });

});
