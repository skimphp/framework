<?php declare(strict_types=1);

use Skim\Core\Request;
use Skim\Core\Response;
use Skim\View\View;

describe('Response view methods', function(): void {

    beforeEach(function(): void {
        View::reset();
        View::setPath(dirname(__DIR__) . '/Fixtures/Views');
    });

    test('view() renders template and sets html content type', function(): void {
        $res = (new Response())->view('simple', ['name' => 'John']);

        expect($res->getBody())->toContain('John');
        expect($res->getHeader('Content-Type'))->toContain('text/html');
    });

    test('fragment() renders only the named fragment', function(): void {
        $full = (new Response())->view('with_fragment', ['name' => 'Alice']);
        $frag = (new Response())->fragment('with_fragment', ['name' => 'Alice'], 'user-card');

        expect($frag->getBody())->toContain('Alice');
        expect(strlen($frag->getBody()))->toBeLessThan(strlen($full->getBody()));
    });

    test('smartView picks fragment when HX-Target present, full view otherwise', function(): void {
        $reqHtmx = Request::make('GET', '/', [], [], ['HX-Target' => 'user-card']);
        $reqPlain = Request::make('GET', '/');

        $frag = (new Response())->smartView('with_fragment', ['name' => 'B'], $reqHtmx);
        $full = (new Response())->smartView('with_fragment', ['name' => 'B'], $reqPlain);

        expect(strlen($frag->getBody()))->toBeLessThan(strlen($full->getBody()));
    });

});

describe('Response send helpers', function(): void {

    test('back() redirects to Referer header or fallback', function(): void {
        $req = Request::make('GET', '/', [], [], ['Referer' => '/from-page']);
        $res = (new Response())->back($req);

        expect($res->getStatus())->toBe(302);
        expect($res->getHeader('Location'))->toBe('/from-page');

        $noRef = Request::make('GET', '/');
        expect((new Response())->back($noRef, '/home')->getHeader('Location'))->toBe('/home');
    });

    test('download sets attachment headers and file sentinel', function(): void {
        $file = tempnam(sys_get_temp_dir(), 'dl');
        file_put_contents($file, 'file-body');

        $res = (new Response())->download($file, 'report.txt');
        expect($res->getHeader('Content-Disposition'))->toContain('report.txt');
        expect($res->getHeader('Content-Type'))->toBe('application/octet-stream');

        ob_start();
        $res->send();
        $out = ob_get_clean();
        unlink($file);

        expect($out)->toBe('file-body');
    });

    test('download throws on missing file', function(): void {
        (new Response())->download('/nonexistent/file.zip');
    })->throws(\RuntimeException::class);

    test('send() echoes body once', function(): void {
        $res = (new Response())->setBody('hello');
        ob_start();
        $res->send();
        $res->send();
        $out = ob_get_clean();

        expect($out)->toBe('hello');
    });


});

describe('Response::json()', function(): void {

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

describe('Response::status()', function(): void {

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

describe('Response::redirect()', function(): void {

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

describe('Response::withHeader()', function(): void {

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
