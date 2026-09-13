<?php declare(strict_types=1);

use Skim\Http\Client;
use Skim\Http\FakeClient;
use Skim\Http\HttpResponse;

describe('HttpResponse', function(): void {

    test('ok() returns true for 2xx status', function(): void {
        $r = new \Skim\Http\HttpResponse(200, '{}', []);
        expect($r->ok())->toBeTrue();
    });

    test('ok() returns false for 4xx status', function(): void {
        $r = new \Skim\Http\HttpResponse(404, 'not found', []);
        expect($r->ok())->toBeFalse();
    });

    test('json() parses body into array', function(): void {
        $r = new \Skim\Http\HttpResponse(200, '{"id":1,"name":"John"}', []);
        expect($r->json())->toBe(['id' => 1, 'name' => 'John']);
    });

    test('json() returns empty array for invalid JSON', function(): void {
        $r = new \Skim\Http\HttpResponse(200, 'not json', []);
        expect($r->json())->toBe([]);
    });

    test('header() returns named header value', function(): void {
        $r = new \Skim\Http\HttpResponse(200, '', ['Content-Type' => 'application/json']);
        expect($r->header('Content-Type'))->toBe('application/json');
    });

    test('header() returns null for absent header', function(): void {
        $r = new \Skim\Http\HttpResponse(200, '', []);
        expect($r->header('X-Missing'))->toBeNull();
    });

    test('fromStream() parses status from HTTP response header', function(): void {
        $meta = ['HTTP/1.1 201 Created', 'Content-Type: application/json'];
        $r    = \Skim\Http\HttpResponse::fromStream('{"ok":true}', $meta);
        expect($r->status)->toBe(201);
        expect($r->json())->toBe(['ok' => true]);
    });

});

describe('FakeClient — record and assert', function(): void {

    test('records GET requests', function(): void {
        $http = \Skim\Http\Client::fake();
        $http->get('https://api.example.com/users');
        $http->assertSent('GET', 'users');
        expect($http->recorded())->toHaveCount(1);
    });

    test('records POST requests', function(): void {
        $http = \Skim\Http\Client::fake();
        $http->post('https://api.example.com/items', ['name' => 'widget']);
        $http->assertSent('POST', 'items');
        expect($http->recorded())->toHaveCount(1);
    });

    test('returns stubbed response by method+url key', function(): void {
        $http = \Skim\Http\Client::fake([
            'GET https://api.example.com/users' => ['status' => 200, 'body' => ['id' => 1]],
        ]);
        $resp = $http->get('https://api.example.com/users');
        expect($resp->ok())->toBeTrue();
        expect($resp->json())->toBe(['id' => 1]);
    });

    test('returns 200 default when no stub matches', function(): void {
        $http = \Skim\Http\Client::fake();
        $resp = $http->get('https://any.example.com/no-stub');
        expect($resp->status)->toBe(200);
    });

    test('assertNothingSent() passes when no requests made', function(): void {
        $http = \Skim\Http\Client::fake();
        $http->assertNothingSent();
        expect(true)->toBeTrue();
    });

    test('assertSent() throws when request was not made', function(): void {
        $http = \Skim\Http\Client::fake();
        expect(fn() => $http->assertSent('GET', 'not-called'))
            ->toThrow(\RuntimeException::class);
    });

    test('assertNothingSent() throws when requests were made', function(): void {
        $http = \Skim\Http\Client::fake();
        $http->get('https://api.example.com/ping');
        expect(fn() => $http->assertNothingSent())
            ->toThrow(\RuntimeException::class);
    });

    test('recorded() returns all captured request metadata', function(): void {
        $http = \Skim\Http\Client::fake();
        $http->get('https://api.example.com/a');
        $http->post('https://api.example.com/b', ['x' => 1]);
        expect(count($http->recorded()))->toBe(2);
    });

});
