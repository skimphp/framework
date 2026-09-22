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
