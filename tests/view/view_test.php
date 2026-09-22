<?php declare(strict_types=1);

use Skim\View\View;
use Skim\View\Exceptions\ViewException;

beforeEach(function(): void {
    \Skim\View\View::reset();
    \Skim\View\View::setPath(dirname(__DIR__) . '/Fixtures/Views');
});

describe('View::render() — full template', function(): void {

    test('renders full template with data variables', function(): void {
        $html = \Skim\View\View::render('simple', ['name' => 'John']);
        expect($html)->toContain('John')
                      ->toContain('<html>');
    });

    test('e() escapes HTML special characters', function(): void {
        $html = \Skim\View\View::render('simple', ['name' => '<script>alert(1)</script>']);
        expect($html)->toContain('&lt;script&gt;')
                      ->not->toContain('<script>');
    });

    test('throws ViewException when template file not found', function(): void {
        expect(fn() => \Skim\View\View::render('nonexistent_template', []))
            ->toThrow(\Skim\View\Exceptions\ViewException::class);
    });

});

describe('View::render() — fragment extraction', function(): void {

    test('returns only fragment content when fragment name given', function(): void {
        $html = \Skim\View\View::render('with_fragment', ['name' => 'Alice'], 'user-card');
        expect($html)->toContain('Alice')
                      ->not->toContain('<html>');
    });

    test('full render includes everything including fragment markers', function(): void {
        $html = \Skim\View\View::render('with_fragment', ['name' => 'Bob']);
        expect($html)->toContain('<html>')
                      ->toContain('Bob');
    });

    test('throws ViewException when fragment name not found', function(): void {
        expect(fn() => \Skim\View\View::render('with_fragment', ['name' => 'X'], 'nonexistent-fragment'))
            ->toThrow(\Skim\View\Exceptions\ViewException::class);
    });

});

describe('View::share()', function(): void {

    test('shared data is available in every template', function(): void {
        \Skim\View\View::share('name', 'SharedUser');
        $html = \Skim\View\View::render('simple', []);
        expect($html)->toContain('SharedUser');
    });

    test('template-level data overrides shared data', function(): void {
        \Skim\View\View::share('name', 'Shared');
        $html = \Skim\View\View::render('simple', ['name' => 'Override']);
        expect($html)->toContain('Override')
                      ->not->toContain('Shared');
    });

});
