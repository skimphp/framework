<?php declare(strict_types=1);

use skim\view\view;

beforeEach(function(): void {
    view::reset();
    view::set_path(dirname(__DIR__) . '/fixtures/views');
});

describe('layout system', function(): void {

    test('child template content is injected into layout blocks', function(): void {
        $html = view::render('pages/home', ['name' => 'Alice']);
        expect($html)->toContain('<h1>Home Page</h1>')
                      ->toContain('Welcome, Alice!')
                      ->toContain('Default Footer');
    });

    test('layout wraps the entire page with doctype', function(): void {
        $html = view::render('pages/home', ['name' => 'Bob']);
        expect($html)->toContain('<!DOCTYPE html>')
                      ->toContain('<html>')
                      ->toContain('</html>');
    });

    test('section alias works the same as start', function(): void {
        // pages/home already uses section/end_section aliases
        $html = view::render('pages/home', ['name' => 'Charlie']);
        expect($html)->toContain('<h1>Home Page</h1>')
                      ->toContain('Welcome, Charlie!');
    });

    test('block returns default when section is not defined', function(): void {
        $html = view::render('pages/home', ['name' => 'Dana']);
        // footer uses default 'Default Footer' since no footer section was captured
        expect($html)->toContain('Default Footer');
    });

    test('slot backward compat alias still works', function(): void {
        // This is tested indirectly: the layout uses block() which is the canonical name.
        // We verify the template class still exposes slot() by calling it directly.
        $template = new \skim\view\template(
            dirname(__DIR__) . '/fixtures/views',
            [],
            null
        );
        $ref = new ReflectionClass($template);
        expect($ref->hasMethod('slot'))->toBeTrue();
        expect($ref->hasMethod('block'))->toBeTrue();
    });

});
