<?php declare(strict_types=1);

use skim\core\response;

describe('response::json()', function(): void {

    test('sets Content-Type to application/json', function(): void {
        $res = new response();
        $res->json(['ok' => true]);
        expect($res->get_headers()['Content-Type'])->toBe('application/json');
    });

    test('serializes array to JSON body', function(): void {
        $res = new response();
        $res->json(['name' => 'John', 'age' => 30]);
        expect($res->get_body())->toBe('{"name":"John","age":30}');
    });

    test('accepts explicit status code as second argument', function(): void {
        $res = new response();
        $res->json(['error' => 'not found'], 404);
        expect($res->get_status())->toBe(404);
    });

});

describe('response::status()', function(): void {

    test('sets status code and returns $this for chaining', function(): void {
        $res = new response();
        $chained = $res->status(422)->json(['errors' => []]);
        expect($res->get_status())->toBe(422);
        expect($chained)->toBeInstanceOf(response::class);
    });

    test('status 200 is default', function(): void {
        $res = new response();
        expect($res->get_status())->toBe(200);
    });

});

describe('response::redirect()', function(): void {

    test('sets Location header', function(): void {
        $res = new response();
        $res->redirect('/login');
        expect($res->get_headers()['Location'])->toBe('/login');
    });

    test('defaults status to 302', function(): void {
        $res = new response();
        $res->redirect('/login');
        expect($res->get_status())->toBe(302);
    });

    test('preserves status when set before redirect', function(): void {
        $res = new response();
        $res->status(301)->redirect('/new-location');
        expect($res->get_status())->toBe(301);
    });

});

describe('response::with_header()', function(): void {

    test('adds a custom header', function(): void {
        $res = new response();
        $res->with_header('X-Custom', 'value');
        expect($res->get_headers()['X-Custom'])->toBe('value');
    });

    test('overwrites existing header with same name', function(): void {
        $res = new response();
        $res->with_header('X-Token', 'first');
        $res->with_header('X-Token', 'second');
        expect($res->get_headers()['X-Token'])->toBe('second');
    });

});
