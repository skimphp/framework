<?php declare(strict_types=1);

use Skim\Core\Response;

describe('response::json()', function(): void {

    test('sets Content-Type to application/json', function(): void {
        $res = new \Skim\Core\Response();
        $res->json(['ok' => true]);
        expect($res->getHeaders()['Content-Type'])->toBe('application/json');
    });

    test('serializes array to JSON body', function(): void {
        $res = new \Skim\Core\Response();
        $res->json(['name' => 'John', 'age' => 30]);
        expect($res->getBody())->toBe('{"name":"John","age":30}');
    });

    test('accepts explicit status code as second argument', function(): void {
        $res = new \Skim\Core\Response();
        $res->json(['error' => 'not found'], 404);
        expect($res->getStatus())->toBe(404);
    });

});

describe('response::status()', function(): void {

    test('sets status code and returns $this for chaining', function(): void {
        $res = new \Skim\Core\Response();
        $chained = $res->status(422)->json(['errors' => []]);
        expect($res->getStatus())->toBe(422);
        expect($chained)->toBeInstanceOf(\Skim\Core\Response::class);
    });

    test('status 200 is default', function(): void {
        $res = new \Skim\Core\Response();
        expect($res->getStatus())->toBe(200);
    });

});

describe('response::redirect()', function(): void {

    test('sets Location header', function(): void {
        $res = new \Skim\Core\Response();
        $res->redirect('/login');
        expect($res->getHeaders()['Location'])->toBe('/login');
    });

    test('defaults status to 302', function(): void {
        $res = new \Skim\Core\Response();
        $res->redirect('/login');
        expect($res->getStatus())->toBe(302);
    });

    test('preserves status when set before redirect', function(): void {
        $res = new \Skim\Core\Response();
        $res->status(301)->redirect('/new-location');
        expect($res->getStatus())->toBe(301);
    });

});

describe('response::withHeader()', function(): void {

    test('adds a custom header', function(): void {
        $res = new \Skim\Core\Response();
        $res->withHeader('X-Custom', 'value');
        expect($res->getHeaders()['X-Custom'])->toBe('value');
    });

    test('overwrites existing header with same name', function(): void {
        $res = new \Skim\Core\Response();
        $res->withHeader('X-Token', 'first');
        $res->withHeader('X-Token', 'second');
        expect($res->getHeaders()['X-Token'])->toBe('second');
    });

});
