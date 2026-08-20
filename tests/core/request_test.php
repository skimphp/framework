<?php declare(strict_types=1);

use skim\core\request;

describe('request — input accessors', function(): void {

    test('get() returns query string value', function(): void {
        $req = request::make('GET', '/?page=2', query: ['page' => '2']);
        expect($req->get('page'))->toBe('2');
    });

    test('get() returns default when key absent', function(): void {
        $req = request::make();
        expect($req->get('missing', 99))->toBe(99);
    });

    test('post() returns POST value', function(): void {
        $req = request::make('POST', '/', post: ['email' => 'a@b.com']);
        expect($req->post('email'))->toBe('a@b.com');
    });

    test('input() prefers GET over POST', function(): void {
        $req = request::make('POST', '/', query: ['x' => 'from_get'], post: ['x' => 'from_post']);
        expect($req->input('x'))->toBe('from_get');
    });

    test('input() falls back to POST when GET absent', function(): void {
        $req = request::make('POST', '/', post: ['x' => 'from_post']);
        expect($req->input('x'))->toBe('from_post');
    });

});

describe('request — json body', function(): void {

    test('json() returns parsed array when Content-Type is application/json', function(): void {
        $req = request::make(
            method:   'POST',
            headers:  ['Content-Type' => 'application/json'],
            raw_body: '{"name":"John","age":30}',
        );
        expect($req->json())->toBe(['name' => 'John', 'age' => 30]);
    });

    test('json() returns empty array when Content-Type is not application/json', function(): void {
        $req = request::make('POST', '/', raw_body: '{"x":1}');
        expect($req->json())->toBe([]);
    });

    test('json() returns empty array for invalid JSON', function(): void {
        $req = request::make(
            method:   'POST',
            headers:  ['Content-Type' => 'application/json'],
            raw_body: 'not-json',
        );
        expect($req->json())->toBe([]);
    });

});

describe('request — method and path', function(): void {

    test('method() returns uppercase method', function(): void {
        $req = request::make('post');
        expect($req->method())->toBe('POST');
    });

    test('path() strips query string', function(): void {
        $req = request::make('GET', '/users/5?page=1');
        expect($req->path())->toBe('/users/5');
    });

    test('ip() returns REMOTE_ADDR by default', function(): void {
        $req = request::make();
        // default server has no REMOTE_ADDR so falls back to 127.0.0.1
        expect($req->ip())->toBe('127.0.0.1');
    });

    test('ip() prefers first X-Forwarded-For value', function(): void {
        $req = request::make(headers: ['X-Forwarded-For' => '203.0.113.1, 10.0.0.1']);
        expect($req->ip())->toBe('203.0.113.1');
    });

});

describe('request — detection helpers', function(): void {

    test('is_hypermedia() returns true when ANY known hypermedia header present', function(): void {
        // Mock config to provide header mappings
        \skim\core\config::set('realtime.headers', [
            'htmx'     => 'HX-Request',
            'datastar' => 'datastar-request',
            'turbo'    => 'Turbo-Request',
        ]);

        $req = request::make(headers: ['HX-Request' => 'true']);
        expect($req->is_hypermedia())->toBeTrue();

        $req = request::make(headers: ['datastar-request' => '1']);
        expect($req->is_hypermedia())->toBeTrue();

        $req = request::make(headers: ['Turbo-Request' => 'true']);
        expect($req->is_hypermedia())->toBeTrue();
    });

    test('is_hypermedia() returns false when no hypermedia headers present', function(): void {
        \skim\core\config::set('realtime.headers', [
            'htmx'     => 'HX-Request',
            'datastar' => 'datastar-request',
            'turbo'    => 'Turbo-Request',
        ]);

        $req = request::make();
        expect($req->is_hypermedia())->toBeFalse();
    });

    test('is_hypermedia("htmx") returns true when HX-Request header present', function(): void {
        \skim\core\config::set('realtime.headers', [
            'htmx' => 'HX-Request',
        ]);

        $req = request::make(headers: ['HX-Request' => 'true']);
        expect($req->is_hypermedia('htmx'))->toBeTrue();
    });

    test('is_hypermedia("htmx") returns false when header absent', function(): void {
        \skim\core\config::set('realtime.headers', [
            'htmx' => 'HX-Request',
        ]);

        $req = request::make();
        expect($req->is_hypermedia('htmx'))->toBeFalse();
    });

    test('is_hypermedia("datastar") returns true when datastar-request header present', function(): void {
        \skim\core\config::set('realtime.headers', [
            'datastar' => 'datastar-request',
        ]);

        $req = request::make(headers: ['datastar-request' => '1']);
        expect($req->is_hypermedia('datastar'))->toBeTrue();
    });

    test('is_hypermedia("turbo") returns true when Turbo-Request header present', function(): void {
        \skim\core\config::set('realtime.headers', [
            'turbo' => 'Turbo-Request',
        ]);

        $req = request::make(headers: ['Turbo-Request' => 'true']);
        expect($req->is_hypermedia('turbo'))->toBeTrue();
    });

    test('is_hypermedia("unknown") returns false for unconfigured library', function(): void {
        \skim\core\config::set('realtime.headers', [
            'htmx' => 'HX-Request',
        ]);

        $req = request::make();
        expect($req->is_hypermedia('unknown'))->toBeFalse();
    });

    test('is_hypermedia() returns false when config is empty', function(): void {
        \skim\core\config::set('realtime.headers', []);

        $req = request::make();
        expect($req->is_hypermedia())->toBeFalse();
    });

    test('is_json() returns true when Accept contains application/json', function(): void {
        $req = request::make(headers: ['Accept' => 'application/json']);
        expect($req->is_json())->toBeTrue();
    });

});

describe('request — route params', function(): void {

    test('param() returns injected route segment', function(): void {
        $req = request::make('GET', '/users/42');
        $req->set_route_params(['id' => '42']);
        expect($req->param('id'))->toBe('42');
    });

    test('param() returns default when key absent', function(): void {
        $req = request::make();
        expect($req->param('id', 0))->toBe(0);
    });

});
