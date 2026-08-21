<?php declare(strict_types=1);

use skim\view\view;
use skim\view\exceptions\view_exception;

beforeEach(function(): void {
    view::reset();
    view::set_path(dirname(__DIR__) . '/fixtures/views');
});

describe('view::render() — full template', function(): void {

    test('renders full template with data variables', function(): void {
        $html = view::render('simple', ['name' => 'John']);
        expect($html)->toContain('John')
                      ->toContain('<html>');
    });

    test('e() escapes HTML special characters', function(): void {
        $html = view::render('simple', ['name' => '<script>alert(1)</script>']);
        expect($html)->toContain('&lt;script&gt;')
                      ->not->toContain('<script>');
    });

    test('throws view_exception when template file not found', function(): void {
        expect(fn() => view::render('nonexistent_template', []))
            ->toThrow(view_exception::class);
    });

});

describe('view::render() — fragment extraction', function(): void {

    test('returns only fragment content when fragment name given', function(): void {
        $html = view::render('with_fragment', ['name' => 'Alice'], 'user-card');
        expect($html)->toContain('Alice')
                      ->not->toContain('<html>');
    });

    test('full render includes everything including fragment markers', function(): void {
        $html = view::render('with_fragment', ['name' => 'Bob']);
        expect($html)->toContain('<html>')
                      ->toContain('Bob');
    });

    test('throws view_exception when fragment name not found', function(): void {
        expect(fn() => view::render('with_fragment', ['name' => 'X'], 'nonexistent-fragment'))
            ->toThrow(view_exception::class);
    });

});

describe('view::share()', function(): void {

    test('shared data is available in every template', function(): void {
        view::share('name', 'SharedUser');
        $html = view::render('simple', []);
        expect($html)->toContain('SharedUser');
    });

    test('template-level data overrides shared data', function(): void {
        view::share('name', 'Shared');
        $html = view::render('simple', ['name' => 'Override']);
        expect($html)->toContain('Override')
                      ->not->toContain('Shared');
    });

});
