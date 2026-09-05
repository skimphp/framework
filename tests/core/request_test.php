<?php declare(strict_types=1);

use Skim\Core\Request;

describe('request — input accessors', function(): void {

    test('get() returns query string value', function(): void {
        $req = Request::make('GET', '/?page=2', query: ['page' => '2']);
        expect($req->get('page'))->toBe('2');
    });

    test('get() returns default when key absent', function(): void {
        $req = Request::make();
        expect($req->get('missing', 99))->toBe(99);
    });

    test('post() returns POST value', function(): void {
        $req = Request::make('POST', '/', post: ['email' => 'a@b.com']);
        expect($req->post('email'))->toBe('a@b.com');
    });

    test('input() prefers GET over POST', function(): void {
        $req = Request::make('POST', '/', query: ['x' => 'from_get'], post: ['x' => 'from_post']);
        expect($req->input('x'))->toBe('from_get');
    });

    test('input() falls back to POST when GET absent', function(): void {
        $req = Request::make('POST', '/', post: ['x' => 'from_post']);
        expect($req->input('x'))->toBe('from_post');
    });

});

describe('request — json body', function(): void {

    test('json() returns parsed array when Content-Type is application/json', function(): void {
        $req = Request::make(
            method:   'POST',
            headers:  ['Content-Type' => 'application/json'],
            rawBody: '{"name":"John","age":30}',
        );
        expect($req->json())->toBe(['name' => 'John', 'age' => 30]);
    });

    test('json() returns empty array when Content-Type is not application/json', function(): void {
        $req = Request::make('POST', '/', rawBody: '{"x":1}');
        expect($req->json())->toBe([]);
    });

    test('json() returns empty array for invalid JSON', function(): void {
        $req = Request::make(
            method:   'POST',
            headers:  ['Content-Type' => 'application/json'],
            rawBody: 'not-json',
        );
        expect($req->json())->toBe([]);
    });

});

describe('request — method and path', function(): void {

    test('method() returns uppercase method', function(): void {
        $req = Request::make('post');
        expect($req->method())->toBe('POST');
    });

    test('path() strips query string', function(): void {
        $req = Request::make('GET', '/users/5?page=1');
        expect($req->path())->toBe('/users/5');
    });

    test('ip() returns REMOTE_ADDR by default', function(): void {
        $req = Request::make();
        // default server has no REMOTE_ADDR so falls back to 127.0.0.1
        expect($req->ip())->toBe('127.0.0.1');
    });

    test('ip() prefers first X-Forwarded-For value', function(): void {
        $req = Request::make(headers: ['X-Forwarded-For' => '203.0.113.1, 10.0.0.1']);
        expect($req->ip())->toBe('203.0.113.1');
    });

});

describe('request — detection helpers', function(): void {

    test('isHypermedia() returns true when ANY known hypermedia header present', function(): void {
        // Mock config to provide header mappings
        \Skim\Core\Config::set('realtime.headers', [
            'htmx'     => 'HX-Request',
            'datastar' => 'datastar-request',
            'turbo'    => 'Turbo-Request',
        ]);

        $req = Request::make(headers: ['HX-Request' => 'true']);
        expect($req->isHypermedia())->toBeTrue();

        $req = Request::make(headers: ['datastar-request' => '1']);
        expect($req->isHypermedia())->toBeTrue();

        $req = Request::make(headers: ['Turbo-Request' => 'true']);
        expect($req->isHypermedia())->toBeTrue();
    });

    test('isHypermedia() returns false when no hypermedia headers present', function(): void {
        \Skim\Core\Config::set('realtime.headers', [
            'htmx'     => 'HX-Request',
            'datastar' => 'datastar-request',
            'turbo'    => 'Turbo-Request',
        ]);

        $req = Request::make();
        expect($req->isHypermedia())->toBeFalse();
    });

    test('isHypermedia("htmx") returns true when HX-Request header present', function(): void {
        \Skim\Core\Config::set('realtime.headers', [
            'htmx' => 'HX-Request',
        ]);

        $req = Request::make(headers: ['HX-Request' => 'true']);
        expect($req->isHypermedia('htmx'))->toBeTrue();
    });

    test('isHypermedia("htmx") returns false when header absent', function(): void {
        \Skim\Core\Config::set('realtime.headers', [
            'htmx' => 'HX-Request',
        ]);

        $req = Request::make();
        expect($req->isHypermedia('htmx'))->toBeFalse();
    });

    test('isHypermedia("datastar") returns true when datastar-request header present', function(): void {
        \Skim\Core\Config::set('realtime.headers', [
            'datastar' => 'datastar-request',
        ]);

        $req = Request::make(headers: ['datastar-request' => '1']);
        expect($req->isHypermedia('datastar'))->toBeTrue();
    });

    test('isHypermedia("turbo") returns true when Turbo-Request header present', function(): void {
        \Skim\Core\Config::set('realtime.headers', [
            'turbo' => 'Turbo-Request',
        ]);

        $req = Request::make(headers: ['Turbo-Request' => 'true']);
        expect($req->isHypermedia('turbo'))->toBeTrue();
    });

    test('isHypermedia("unknown") returns false for unconfigured library', function(): void {
        \Skim\Core\Config::set('realtime.headers', [
            'htmx' => 'HX-Request',
        ]);

        $req = Request::make();
        expect($req->isHypermedia('unknown'))->toBeFalse();
    });

    test('isHypermedia() returns false when config is empty', function(): void {
        \Skim\Core\Config::set('realtime.headers', []);

        $req = Request::make();
        expect($req->isHypermedia())->toBeFalse();
    });

    test('is_json() returns true when Accept contains application/json', function(): void {
        $req = Request::make(headers: ['Accept' => 'application/json']);
        expect($req->isJson())->toBeTrue();
    });

});

describe('request — route params', function(): void {

    test('param() returns injected route segment', function(): void {
        $req = Request::make('GET', '/users/42');
        $req->setRouteParams(['id' => '42']);
        expect($req->param('id'))->toBe('42');
    });

    test('param() returns default when key absent', function(): void {
        $req = Request::make();
        expect($req->param('id', 0))->toBe(0);
    });

});
