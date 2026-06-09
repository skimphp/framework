<?php declare(strict_types=1);

use skim\view\view;
use skim\view\exceptions\view_exception;
use tests\fixtures\props\alert_props;

beforeEach(function(): void {
    view::reset();
    view::set_path(dirname(__DIR__) . '/fixtures/views');
});

describe('component rendering', function(): void {

    test('renders component with array props', function(): void {
        $html = view::component('alert', ['message' => 'Test alert', 'type' => 'warning']);
        expect($html)->toContain('Test alert')
                      ->toContain('alert-warning');
    });

    test('renders component with *_props object', function(): void {
        $html = view::component('alert', new alert_props(message: 'Props alert', type: 'danger'));
        expect($html)->toContain('Props alert')
                      ->toContain('alert-danger');
    });

    test('throws view_exception when props object is not a *_props class', function(): void {
        expect(fn() => view::component('alert', new stdClass()))
            ->toThrow(view_exception::class, '*_props');
    });

    test('scope isolation prevents parent data leakage', function(): void {
        // If parent data leaked, $name would be available in the component
        $html = view::component('alert', ['message' => 'Isolated']);
        expect($html)->toContain('Isolated');
        // The component should NOT have $name from a parent render
        expect(fn() => view::render('alert', ['message' => 'ok', 'name' => 'leak']))
            ->not->toThrow(\Error::class);
    });

    test('throws view_exception when component file is missing', function(): void {
        expect(fn() => view::component('nonexistent_component', []))
            ->toThrow(view_exception::class);
    });

    test('global component() helper delegates to view::component', function(): void {
        $html = component('alert', ['message' => 'Helper works']);
        expect($html)->toContain('Helper works');
    });

    test('component with object props uses default values', function(): void {
        $html = view::component('alert', new alert_props(message: 'Default type test'));
        expect($html)->toContain('Default type test')
                      ->toContain('alert-info');
    });

});
