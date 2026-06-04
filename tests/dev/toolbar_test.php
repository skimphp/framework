<?php declare(strict_types=1);

use skim\dev\toolbar;
use skim\dev\profiler;
use skim\core\config;
use skim\core\request;

beforeEach(function(): void {
    config::reset();
    profiler::reset();
    profiler::disable();
    config::set('app.debug', true);
    $_GET    = [];
    $_POST   = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_URI'     => '/test/path',
        'QUERY_STRING'    => '',
        'REQUEST_METHOD'  => 'GET',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'HTTP_HOST'       => 'localhost',
        'SERVER_NAME'     => 'localhost',
        'SERVER_PORT'     => '80',
        'REMOTE_ADDR'     => '127.0.0.1',
        'SCRIPT_FILENAME' => '/var/www/index.php',
        'DOCUMENT_ROOT'   => '/var/www',
        'SERVER_SOFTWARE' => 'PHP built-in server',
    ];
});

afterEach(function(): void {
    config::reset();
    profiler::reset();
    profiler::disable();
});

function make_request(): request {
    return new request(
        query:    $_GET,
        post:     $_POST,
        server:   $_SERVER,
        cookies:  $_COOKIE,
        files:    [],
        raw_body: '',
    );
}

describe('toolbar::render() — debug off', function(): void {

    test('returns empty string when app.debug=false', function(): void {
        config::set('app.debug', false);
        $html = toolbar::render(make_request());
        expect($html)->toBe('');
    });

});

describe('toolbar::render() — debug on', function(): void {

    test('wraps output in #skim-tb root element', function(): void {
        $html = toolbar::render(make_request());
        expect($html)->toContain('<div id="skim-tb">');
    });

    test('does not contain var_dump artifacts', function(): void {
        profiler::enable();
        profiler::db('SELECT 1', 1.0);
        $html = toolbar::render(make_request());
        expect($html)->not->toMatch('/string\(\d+\)/');
        expect($html)->not->toContain("array(");
    });

    test('does not leak absolute template paths', function(): void {
        $html = toolbar::render(make_request());
        expect($html)->not->toContain('/src/dev/views/');
        expect($html)->not->toContain('toolbar.php');
    });

    test('contains HTTP method and path from request', function(): void {
        $html = toolbar::render(make_request());
        expect($html)->toContain('GET');
        expect($html)->toContain('/test/path');
    });

    test('contains toolbar CSS variables (--tb-)', function(): void {
        $html = toolbar::render(make_request());
        expect($html)->toContain('--tb-bg:');
        expect($html)->toContain('--tb-accent:');
    });

    test('shows query tab with count from profiler', function(): void {
        profiler::enable();
        profiler::db('SELECT 1', 5.0);
        profiler::db('SELECT 2', 10.0);
        $html = toolbar::render(make_request());
        expect($html)->toContain('skimTab(this,\'db\'');
        expect($html)->toContain('queries');
        expect($html)->toMatch('/tab-count[^>]*>2</');
    });

    test('shows cache hit/miss ratio from profiler', function(): void {
        profiler::enable();
        profiler::cache('get', 'a', hit: true,  driver: 'array');
        profiler::cache('get', 'b', hit: false, driver: 'array');
        profiler::cache('get', 'c', hit: true,  driver: 'array');
        $html = toolbar::render(make_request());
        expect($html)->toContain('2h/1m');
    });

    test('shows empty-state messages when profiler is empty', function(): void {
        profiler::enable();
        $html = toolbar::render(make_request());
        expect($html)->toContain('No queries');
        expect($html)->toContain('No cache events');
        expect($html)->toContain('No views rendered');
    });

    test('escapes HTML in request path', function(): void {
        $_SERVER['REQUEST_URI'] = '/search?q=<script>alert(1)</script>';
        $html = toolbar::render(make_request());
        expect($html)->not->toContain('<script>alert(1)</script>');
        expect($html)->toContain('&lt;script&gt;');
    });

    test('contains PHP version chip in header', function(): void {
        $html = toolbar::render(make_request());
        expect($html)->toContain(PHP_VERSION);
    });

    test('contains icon font stylesheet', function(): void {
        $html = toolbar::render(make_request());
        expect($html)->toContain('tabler-icons');
    });

});
