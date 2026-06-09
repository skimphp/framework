<?php declare(strict_types=1);

use skim\view\view;
use skim\view\fragment_extractor;
use skim\view\exceptions\view_exception;

beforeEach(function(): void {
    view::reset();
    view::set_path(dirname(__DIR__) . '/fixtures/views');
});

describe('fragment_extractor', function(): void {

    test('extracts simple fragment content', function(): void {
        $html = '<!-- @fragment widget --><div>Hello</div><!-- @end -->';
        expect(fragment_extractor::extract($html, 'widget'))->toBe('<div>Hello</div>');
    });

    test('extracts multiline fragment content', function(): void {
        $html = "<!-- @fragment widget -->\n<div>\n    <p>Hello</p>\n</div>\n<!-- @end -->";
        expect(fragment_extractor::extract($html, 'widget'))->toBe("<div>\n    <p>Hello</p>\n</div>");
    });

    test('throws on nested fragments', function(): void {
        $html = '<!-- @fragment outer --><!-- @fragment inner --><!-- @end --><!-- @end -->';
        expect(fn() => fragment_extractor::extract($html, 'outer'))
            ->toThrow(view_exception::class, 'Nested');
    });

    test('throws on unclosed fragment', function(): void {
        $html = '<!-- @fragment widget --><div>Hello</div>';
        expect(fn() => fragment_extractor::extract($html, 'widget'))
            ->toThrow(view_exception::class, 'Unclosed');
    });

    test('throws on unmatched @end marker', function(): void {
        $html = '<div>Hello</div><!-- @end -->';
        expect(fn() => fragment_extractor::extract($html, 'widget'))
            ->toThrow(view_exception::class, 'Unmatched');
    });

    test('throws when fragment name is not found', function(): void {
        $html = '<!-- @fragment widget --><div>Hello</div><!-- @end -->';
        expect(fn() => fragment_extractor::extract($html, 'missing'))
            ->toThrow(view_exception::class, 'not found');
    });

    test('HTML comments inside fragment do not break parsing', function(): void {
        $html = '<!-- @fragment widget --><div><!-- inner comment --></div><!-- @end -->';
        expect(fragment_extractor::extract($html, 'widget'))->toBe('<div><!-- inner comment --></div>');
    });

    test('multiple fragments can exist in same document', function(): void {
        $html = '<!-- @fragment a -->A<!-- @end --><!-- @fragment b -->B<!-- @end -->';
        expect(fragment_extractor::extract($html, 'a'))->toBe('A');
        expect(fragment_extractor::extract($html, 'b'))->toBe('B');
    });

});

describe('view::render() fragment integration', function(): void {

    test('renders only fragment content from template', function(): void {
        $html = view::render('pages/dashboard', ['name' => 'Alice', 'stats_value' => '42', 'users' => ['Bob', 'Charlie']], 'stats_widget');
        expect($html)->toContain('42')
                      ->not->toContain('<!DOCTYPE html>')
                      ->not->toContain('Dashboard');
    });

    test('renders user_list fragment from dashboard', function(): void {
        $html = view::render('pages/dashboard', ['name' => 'Alice', 'stats_value' => '42', 'users' => ['Bob', 'Charlie']], 'user_list');
        expect($html)->toContain('Bob')
                      ->toContain('Charlie')
                      ->not->toContain('stats_widget');
    });

    test('throws when fragment name is missing from template', function(): void {
        expect(fn() => view::render('pages/dashboard', ['name' => 'X', 'stats_value' => '0', 'users' => []], 'nonexistent'))
            ->toThrow(view_exception::class);
    });

    test('full render includes fragment markers in output', function(): void {
        $html = view::render('pages/dashboard', ['name' => 'Alice', 'stats_value' => '42', 'users' => ['Bob']]);
        expect($html)->toContain('stats_widget')
                      ->toContain('user_list')
                      ->toContain('Dashboard for Alice');
    });

});
