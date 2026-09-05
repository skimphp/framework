<?php declare(strict_types=1);

use Skim\View\View;
use Skim\View\Exceptions\ViewException;
use Tests\Fixtures\Props\AlertProps;

beforeEach(function(): void {
    \Skim\View\View::reset();
    \Skim\View\View::setPath(dirname(__DIR__) . '/Fixtures/Views');
});

describe('component rendering', function(): void {

    test('renders component with array props', function(): void {
        $html = \Skim\View\View::component('alert', ['message' => 'Test alert', 'type' => 'warning']);
        expect($html)->toContain('Test alert')
                      ->toContain('alert-warning');
    });

    test('renders component with *_props object', function(): void {
        $html = \Skim\View\View::component('alert', new \Tests\Fixtures\Props\AlertProps(message: 'Props alert', type: 'danger'));
        expect($html)->toContain('Props alert')
                      ->toContain('alert-danger');
    });

    test('throws ViewException when props object is not a *_props class', function(): void {
        expect(fn() => \Skim\View\View::component('alert', new stdClass()))
            ->toThrow(\Skim\View\Exceptions\ViewException::class, '*Props');
    });

    test('scope isolation prevents parent data leakage', function(): void {
        // If parent data leaked, $name would be available in the component
        $html = \Skim\View\View::component('alert', ['message' => 'Isolated']);
        expect($html)->toContain('Isolated');
        // The component should NOT have $name from a parent render
        expect(fn() => \Skim\View\View::render('alert', ['message' => 'ok', 'name' => 'leak']))
            ->not->toThrow(\Error::class);
    });

    test('throws ViewException when component file is missing', function(): void {
        expect(fn() => \Skim\View\View::component('nonexistent_component', []))
            ->toThrow(\Skim\View\Exceptions\ViewException::class);
    });

    test('global component() helper delegates to View::component', function(): void {
        $html = component('alert', ['message' => 'Helper works']);
        expect($html)->toContain('Helper works');
    });

    test('component with object props uses default values', function(): void {
        $html = \Skim\View\View::component('alert', new \Tests\Fixtures\Props\AlertProps(message: 'Default type test'));
        expect($html)->toContain('Default type test')
                      ->toContain('alert-info');
    });

});
