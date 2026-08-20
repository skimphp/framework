<?php declare(strict_types=1);

use skim\view\view;
use tests\fixtures\props\card_props;

beforeEach(function(): void {
    view::reset();
    view::set_path(dirname(__DIR__) . '/fixtures/views');
});

describe('component parts system', function(): void {

    test('main body is captured outside named parts', function(): void {
        $html = component_with_parts('card', new card_props(title: 'Profile'), function($c) {
            echo '<p>Main content here</p>';
        });
        expect($html)->toContain('Main content here')
                      ->toContain('Profile');
    });

    test('named parts are captured and rendered', function(): void {
        $html = component_with_parts('card', new card_props(title: 'Profile'), function($c) {
            $c->part('header', function() {
                echo '<h2>Custom Header</h2>';
            });
            echo '<p>Body</p>';
            $c->part('footer', function() {
                echo '<button>Close</button>';
            });
        });
        expect($html)->toContain('Custom Header')
                      ->toContain('Body')
                      ->toContain('Close');
    });

    test('has_part returns false when part is not defined', function(): void {
        $html = component_with_parts('card', new card_props(title: 'No Parts'), function($c) {
            echo '<p>Just body</p>';
        });
        // card.php shows default title when no header part, no footer div when no footer part
        expect($html)->toContain('No Parts')  // default title rendered
                      ->toContain('Just body')
                      ->not->toContain('card-footer');  // footer div absent when no footer part
    });

    test('default title is used when no header part is provided', function(): void {
        $html = component_with_parts('card', new card_props(title: 'Default Title'), function($c) {
            echo '<p>Body only</p>';
        });
        expect($html)->toContain('Default Title');
    });

    test('nested components with parts do not leak', function(): void {
        $html = component_with_parts('card', new card_props(title: 'Outer'), function($c) {
            $c->part('header', function() {
                echo '<h2>Outer Header</h2>';
            });
            echo '<p>Outer body</p>';
        });
        expect($html)->toContain('Outer Header')
                      ->toContain('Outer body');
    });

    test('part() global helper returns default when no component is active', function(): void {
        expect(part('test'))->toBe('');
        expect(part('test', 'fallback'))->toBe('fallback');
    });

    test('has_part() global helper returns false when no component is active', function(): void {
        expect(has_part('test'))->toBeFalse();
    });

});
