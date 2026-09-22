<?php declare(strict_types=1);

use Skim\Core\Request;
use Skim\Core\Response;
use Skim\Middleware\ToolbarMiddleware;

function toolbarTestResponse(string $body = '<html><body>hi</body></html>'): Response {
    return (new Response())->status(200)->html($body);
}

describe('ToolbarMiddleware::handle() hypermedia skip', function (): void {

    test('skips injection for hypermedia requests', function (): void {
        \Skim\Core\Config::set('app.debug', true);
        \Skim\Core\Config::set('realtime.headers', ['htmx' => 'HX-Request']);
        $req = Request::make('GET', '/', headers: ['HX-Request' => 'true']);
        $res = toolbarTestResponse();
        $out = (new ToolbarMiddleware())->handle($req, $res, fn($q, $s) => $s);

        expect($out->getBody())->not->toContain('toolbar');
    });

    test('injects toolbar for plain HTML requests in debug mode', function (): void {
        \Skim\Core\Config::set('app.debug', true);
        $req = Request::make('GET', '/');
        $res = toolbarTestResponse();
        $out = (new ToolbarMiddleware())->handle($req, $res, fn($q, $s) => $s);

        expect($out->getBody())->toContain('</body>');
    });

    test('skips injection when debug is off', function (): void {
        \Skim\Core\Config::set('app.debug', false);
        $req = Request::make('GET', '/');
        $res = toolbarTestResponse();
        $out = (new ToolbarMiddleware())->handle($req, $res, fn($q, $s) => $s);

        expect($out->getBody())->not->toContain('toolbar-injected');
    });

});
