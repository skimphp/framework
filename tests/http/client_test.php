<?php declare(strict_types=1);

use skim\http\client;
use skim\http\fake_client;
use skim\http\http_response;

describe('http_response', function(): void {

    test('ok() returns true for 2xx status', function(): void {
        $r = new http_response(200, '{}', []);
        expect($r->ok())->toBeTrue();
    });

    test('ok() returns false for 4xx status', function(): void {
        $r = new http_response(404, 'not found', []);
        expect($r->ok())->toBeFalse();
    });

    test('json() parses body into array', function(): void {
        $r = new http_response(200, '{"id":1,"name":"John"}', []);
        expect($r->json())->toBe(['id' => 1, 'name' => 'John']);
    });

    test('json() returns empty array for invalid JSON', function(): void {
        $r = new http_response(200, 'not json', []);
        expect($r->json())->toBe([]);
    });

    test('header() returns named header value', function(): void {
        $r = new http_response(200, '', ['Content-Type' => 'application/json']);
        expect($r->header('Content-Type'))->toBe('application/json');
    });

    test('header() returns null for absent header', function(): void {
        $r = new http_response(200, '', []);
        expect($r->header('X-Missing'))->toBeNull();
    });

    test('from_stream() parses status from HTTP response header', function(): void {
        $meta = ['HTTP/1.1 201 Created', 'Content-Type: application/json'];
        $r    = http_response::from_stream('{"ok":true}', $meta);
        expect($r->status)->toBe(201);
        expect($r->json())->toBe(['ok' => true]);
    });

});

describe('fake_client — record and assert', function(): void {

    test('records GET requests', function(): void {
        $http = client::fake();
        $http->get('https://api.example.com/users');
        $http->assert_sent('GET', 'users');
        expect($http->recorded())->toHaveCount(1);
    });

    test('records POST requests', function(): void {
        $http = client::fake();
        $http->post('https://api.example.com/items', ['name' => 'widget']);
        $http->assert_sent('POST', 'items');
        expect($http->recorded())->toHaveCount(1);
    });

    test('returns stubbed response by method+url key', function(): void {
        $http = client::fake([
            'GET https://api.example.com/users' => ['status' => 200, 'body' => ['id' => 1]],
        ]);
        $resp = $http->get('https://api.example.com/users');
        expect($resp->ok())->toBeTrue();
        expect($resp->json())->toBe(['id' => 1]);
    });

    test('returns 200 default when no stub matches', function(): void {
        $http = client::fake();
        $resp = $http->get('https://any.example.com/no-stub');
        expect($resp->status)->toBe(200);
    });

    test('assert_nothing_sent() passes when no requests made', function(): void {
        $http = client::fake();
        $http->assert_nothing_sent();
        expect(true)->toBeTrue();
    });

    test('assert_sent() throws when request was not made', function(): void {
        $http = client::fake();
        expect(fn() => $http->assert_sent('GET', 'not-called'))
            ->toThrow(\RuntimeException::class);
    });

    test('assert_nothing_sent() throws when requests were made', function(): void {
        $http = client::fake();
        $http->get('https://api.example.com/ping');
        expect(fn() => $http->assert_nothing_sent())
            ->toThrow(\RuntimeException::class);
    });

    test('recorded() returns all captured request metadata', function(): void {
        $http = client::fake();
        $http->get('https://api.example.com/a');
        $http->post('https://api.example.com/b', ['x' => 1]);
        expect(count($http->recorded()))->toBe(2);
    });

});
