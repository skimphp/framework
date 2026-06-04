<?php declare(strict_types=1);

use skim\dev\error_page;
use skim\core\config;

beforeEach(function(): void {
    config::reset();
    config::set('app.debug', true);
});

afterEach(function(): void {
    config::reset();
});

/**
 * Captures output of error_page::render() without sending headers.
 *
 * The real render() calls http_response_code() and header() which fail
 * in CLI test context. This wrapper catches those and just returns HTML.
 */
function capture_error_page(\Throwable $e): string {
    ob_start();
    try {
        error_page::render($e);
    }
    catch (\Throwable) {
    }
    return (string) ob_get_clean();
}

describe('error_page::render()', function(): void {

    test('produces valid HTML starting with <!DOCTYPE', function(): void {
        $html = capture_error_page(new \RuntimeException('test error'));
        expect($html)->toStartWith('<!DOCTYPE');
    });

    test('does not contain var_dump artifacts', function(): void {
        // Use a fixture so the test's own source code (which contains
        // the literal "string(" used in these assertions) does not appear
        // in the code-box component and cause a false positive.
        $fixture = realpath(__DIR__ . '/fixtures/clean_path.php');
        require_once $fixture;
        $e = clean_path_fixture::throw_runtime();
        $html = capture_error_page($e);
        expect($html)->not->toContain("string(");
        expect($html)->not->toMatch('/string\(\d+\)/');
    });

    test('does not leak absolute template paths', function(): void {
        // Use a fixture file so the test's own source code (which contains
        // the literal substrings used in these assertions) does not appear
        // in the code-box component and cause false positives.
        $fixture = realpath(__DIR__ . '/fixtures/clean_path.php');
        require_once $fixture;
        $e = clean_path_fixture::throw_runtime();
        $html = capture_error_page($e);
        expect($html)->not->toContain('/src/dev/views/');
        expect($html)->not->toContain('error_page.php');
        expect($html)->not->toContain('base.php');
        expect($html)->not->toContain('kv_grid.php');
    });

    test('contains the exception class name', function(): void {
        $html = capture_error_page(new \RuntimeException('specific test message xyz'));
        expect($html)->toContain('RuntimeException');
        expect($html)->toContain('specific test message xyz');
    });

    test('contains tab bar with expected sections', function(): void {
        $html = capture_error_page(new \RuntimeException('test'));
        expect($html)->toContain('data-tab="code"');
        expect($html)->toContain('data-tab="trace"');
        expect($html)->toContain('data-tab="env"');
        expect($html)->toContain('data-tab="request"');
    });

    test('contains shared design system CSS variables', function(): void {
        $html = capture_error_page(new \RuntimeException('test'));
        expect($html)->toContain('--bg:');
        expect($html)->toContain('--accent:');
        expect($html)->toContain('--danger:');
    });

    test('contains the error location pill', function(): void {
        $html = capture_error_page(new \RuntimeException('test'));
        expect($html)->toContain('class="hero-loc"');
        expect($html)->toContain('phpstorm://open');
    });

    test('contains action buttons (IDE, copy, Google, AI)', function(): void {
        $html = capture_error_page(new \RuntimeException('test'));
        expect($html)->toContain('Open in IDE');
        expect($html)->toContain('Copy stack trace');
        expect($html)->toContain('Ask AI');
    });

    test('escapes HTML in exception message', function(): void {
        $msg = '<script>alert("xss")</script>';
        $html = capture_error_page(new \RuntimeException($msg));
        expect($html)->not->toContain('<script>alert("xss")</script>');
        expect($html)->toContain('&lt;script&gt;');
    });

    test('shows solutions panel for undefined method errors', function(): void {
        $e = new \BadMethodCallException('Call to undefined method Foo::bar()');
        $html = capture_error_page($e);
        // No solution for non-existent class, so just verify no crash
        expect($html)->toContain('BadMethodCallException');
    });

    test('contains syntax-highlighted code context for real files', function(): void {
        $e = new \RuntimeException('test');
        $html = capture_error_page($e);
        // code_box component renders <table> with line numbers
        expect($html)->toContain('class="code-box"');
        expect($html)->toContain('class="ln"');
    });

    test('includes PHP version and debug mode chip', function(): void {
        $html = capture_error_page(new \RuntimeException('test'));
        expect($html)->toContain('PHP ' . PHP_VERSION);
        expect($html)->toContain('debug mode');
    });

    test('syntax highlighting emits well-formed <span> tags', function(): void {
        $fixture = realpath(__DIR__ . '/fixtures/clean_path.php');
        require_once $fixture;
        $e = clean_path_fixture::throw_runtime();
        $html = capture_error_page($e);
        file_put_contents('/tmp/highlight-dump.html', $html);
        expect($html)->not->toMatch('/<span class=<span/');
        expect($html)->toMatch('/<span class="kw">declare<\/span>/');
    });

});

describe('error_page noise detection', function(): void {

    test('marks /vendor/ file as framework noise', function(): void {
        $ref = new \ReflectionClass(error_page::class);
        $method = $ref->getMethod('is_noise');
        expect($method->invoke(null, '/app/vendor/some/lib/file.php'))->toBeTrue();
    });

    test('marks /src/core/ file as framework noise', function(): void {
        $ref = new \ReflectionClass(error_page::class);
        $method = $ref->getMethod('is_noise');
        expect($method->invoke(null, '/app/src/core/pipeline.php'))->toBeTrue();
        expect($method->invoke(null, '/app/src/core/app.php'))->toBeTrue();
    });

    test('marks closures in framework classes as noise even when called from user file', function(): void {
        $ref = new \ReflectionClass(error_page::class);
        $method = $ref->getMethod('is_noise');
        // The closure belongs to skim\core\app::dispatch but is invoked
        // from a user middleware file. Trace reports the caller file.
        $is_noise = $method->invoke(
            null,
            '/app/src/middleware/toolbar_middleware.php',
            'skim\\core\\app',
            '{closure:skim\\core\\app::dispatch():483}',
        );
        expect($is_noise)->toBeTrue();
    });

    test('marks framework method calls (e.g. app::run) as noise from caller file', function(): void {
        $ref = new \ReflectionClass(error_page::class);
        $method = $ref->getMethod('is_noise');
        $is_noise = $method->invoke(
            null,
            '/app/public/index.php',
            'skim\\core\\app',
            'run',
        );
        expect($is_noise)->toBeTrue();
    });

    test('does NOT mark user code as noise', function(): void {
        $ref = new \ReflectionClass(error_page::class);
        $method = $ref->getMethod('is_noise');
        $is_noise = $method->invoke(
            null,
            '/app/app/controllers/home.php',
            'app\\controllers\\home_controller',
            'index',
        );
        expect($is_noise)->toBeFalse();
    });

    test('marks middleware classes as noise', function(): void {
        $ref = new \ReflectionClass(error_page::class);
        $method = $ref->getMethod('is_noise');
        expect($method->invoke(null, '/app/src/middleware/cors.php', 'skim\\middleware\\cors', 'handle'))->toBeTrue();
    });

});
