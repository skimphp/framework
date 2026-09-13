<?php declare(strict_types=1);

use Skim\View\View;
use Skim\View\FragmentExtractor;
use Skim\View\Exceptions\ViewException;

beforeEach(function(): void {
    \Skim\View\View::reset();
    \Skim\View\View::setPath(dirname(__DIR__) . '/Fixtures/Views');
});

describe('FragmentExtractor', function(): void {

    test('extracts simple fragment content', function(): void {
        $html = '<!-- @fragment widget --><div>Hello</div><!-- @end -->';
        expect(\Skim\View\FragmentExtractor::extract($html, 'widget'))->toBe('<div>Hello</div>');
    });

    test('extracts multiline fragment content', function(): void {
        $html = "<!-- @fragment widget -->\n<div>\n    <p>Hello</p>\n</div>\n<!-- @end -->";
        expect(\Skim\View\FragmentExtractor::extract($html, 'widget'))->toBe("<div>\n    <p>Hello</p>\n</div>");
    });

    test('throws on nested fragments', function(): void {
        $html = '<!-- @fragment outer --><!-- @fragment inner --><!-- @end --><!-- @end -->';
        expect(fn() => \Skim\View\FragmentExtractor::extract($html, 'outer'))
            ->toThrow(\Skim\View\Exceptions\ViewException::class, 'Nested');
    });

    test('throws on unclosed fragment', function(): void {
        $html = '<!-- @fragment widget --><div>Hello</div>';
        expect(fn() => \Skim\View\FragmentExtractor::extract($html, 'widget'))
            ->toThrow(\Skim\View\Exceptions\ViewException::class, 'Unclosed');
    });

    test('throws on unmatched @end marker', function(): void {
        $html = '<div>Hello</div><!-- @end -->';
        expect(fn() => \Skim\View\FragmentExtractor::extract($html, 'widget'))
            ->toThrow(\Skim\View\Exceptions\ViewException::class, 'Unmatched');
    });

    test('throws when fragment name is not found', function(): void {
        $html = '<!-- @fragment widget --><div>Hello</div><!-- @end -->';
        expect(fn() => \Skim\View\FragmentExtractor::extract($html, 'missing'))
            ->toThrow(\Skim\View\Exceptions\ViewException::class, 'not found');
    });

    test('HTML comments inside fragment do not break parsing', function(): void {
        $html = '<!-- @fragment widget --><div><!-- inner comment --></div><!-- @end -->';
        expect(\Skim\View\FragmentExtractor::extract($html, 'widget'))->toBe('<div><!-- inner comment --></div>');
    });

    test('multiple fragments can exist in same document', function(): void {
        $html = '<!-- @fragment a -->A<!-- @end --><!-- @fragment b -->B<!-- @end -->';
        expect(\Skim\View\FragmentExtractor::extract($html, 'a'))->toBe('A');
        expect(\Skim\View\FragmentExtractor::extract($html, 'b'))->toBe('B');
    });

});

describe('View::render() fragment integration', function(): void {

    test('renders only fragment content from template', function(): void {
        $html = \Skim\View\View::render('pages/dashboard', ['name' => 'Alice', 'stats_value' => '42', 'users' => ['Bob', 'Charlie']], 'stats_widget');
        expect($html)->toContain('42')
                      ->not->toContain('<!DOCTYPE html>')
                      ->not->toContain('Dashboard');
    });

    test('renders user_list fragment from dashboard', function(): void {
        $html = \Skim\View\View::render('pages/dashboard', ['name' => 'Alice', 'stats_value' => '42', 'users' => ['Bob', 'Charlie']], 'user_list');
        expect($html)->toContain('Bob')
                      ->toContain('Charlie')
                      ->not->toContain('stats_widget');
    });

    test('throws when fragment name is missing from template', function(): void {
        expect(fn() => \Skim\View\View::render('pages/dashboard', ['name' => 'X', 'stats_value' => '0', 'users' => []], 'nonexistent'))
            ->toThrow(\Skim\View\Exceptions\ViewException::class);
    });

    test('full render includes fragment markers in output', function(): void {
        $html = \Skim\View\View::render('pages/dashboard', ['name' => 'Alice', 'stats_value' => '42', 'users' => ['Bob']]);
        expect($html)->toContain('stats_widget')
                      ->toContain('user_list')
                      ->toContain('Dashboard for Alice');
    });

});
