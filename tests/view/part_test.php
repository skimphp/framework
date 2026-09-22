<?php declare(strict_types=1);

use Skim\View\View;
use Tests\Fixtures\Props\CardProps;

beforeEach(function(): void {
    \Skim\View\View::reset();
    \Skim\View\View::setPath(dirname(__DIR__) . '/Fixtures/Views');
});

describe('component parts system', function(): void {

    test('main body is captured outside named parts', function(): void {
        $html = componentWithParts('card', new \Tests\Fixtures\Props\CardProps(title: 'Profile'), function($c) {
            echo '<p>Main content here</p>';
        });
        expect($html)->toContain('Main content here')
                      ->toContain('Profile');
    });

    test('named parts are captured and rendered', function(): void {
        $html = componentWithParts('card', new \Tests\Fixtures\Props\CardProps(title: 'Profile'), function($c) {
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

    test('hasPart returns false when part is not defined', function(): void {
        $html = componentWithParts('card', new \Tests\Fixtures\Props\CardProps(title: 'No Parts'), function($c) {
            echo '<p>Just body</p>';
        });
        // card.php shows default title when no header part, no footer div when no footer part
        expect($html)->toContain('No Parts')  // default title rendered
                      ->toContain('Just body')
                      ->not->toContain('card-footer');  // footer div absent when no footer part
    });

    test('default title is used when no header part is provided', function(): void {
        $html = componentWithParts('card', new \Tests\Fixtures\Props\CardProps(title: 'Default Title'), function($c) {
            echo '<p>Body only</p>';
        });
        expect($html)->toContain('Default Title');
    });

    test('nested components with parts do not leak', function(): void {
        $html = componentWithParts('card', new \Tests\Fixtures\Props\CardProps(title: 'Outer'), function($c) {
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

    test('hasPart() global helper returns false when no component is active', function(): void {
        expect(hasPart('test'))->toBeFalse();
    });

});
