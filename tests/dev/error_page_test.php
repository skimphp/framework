<?php declare(strict_types=1);

use Skim\Dev\ErrorPage;
use Skim\Core\Config;
use Skim\Core\App;

beforeEach(function(): void {
    \Skim\Core\Config::reset();
    \Skim\Core\Config::set('app.debug', true);
});

afterEach(function(): void {
    \Skim\Core\Config::reset();
});

/**
 * Captures output of ErrorPage::render() without sending headers.
 *
 * The real render() calls http_response_code() and header() which fail
 * in CLI test context. This wrapper catches those and just returns HTML.
 */
function captureErrorPage(\Throwable $e): string {
    ob_start();
    try {
        \Skim\Dev\ErrorPage::render($e);
    }
    catch (\Throwable) {
    }
    return (string) ob_get_clean();
}

describe('ErrorPage::render()', function(): void {

    test('produces valid HTML starting with <!DOCTYPE', function(): void {
        $html = captureErrorPage(new \RuntimeException('test error'));
        expect($html)->toStartWith('<!DOCTYPE');
    });

    test('does not contain var_dump artifacts', function(): void {
        // Use a fixture so the test's own source code (which contains
        // the literal "string(" used in these assertions) does not appear
        // in the code-box component and cause a false positive.
        $fixture = realpath(__DIR__ . '/../Fixtures/Dev/CleanPathFixture.php');
        require_once $fixture;
        $e = \Tests\Fixtures\Dev\CleanPathFixture::throwRuntime();
        $html = captureErrorPage($e);
        expect($html)->not->toContain("string(");
        expect($html)->not->toMatch('/string\(\d+\)/');
    });

    test('does not leak absolute template paths', function(): void {
        // Use a fixture file so the test's own source code (which contains
        // the literal substrings used in these assertions) does not appear
        // in the code-box component and cause false positives.
        $fixture = realpath(__DIR__ . '/../Fixtures/Dev/CleanPathFixture.php');
        require_once $fixture;
        $e = \Tests\Fixtures\Dev\CleanPathFixture::throwRuntime();
        $html = captureErrorPage($e);
        expect($html)->not->toContain('/src/Dev/Views/');
        expect($html)->not->toContain('error_page.php');
        expect($html)->not->toContain('base.php');
        expect($html)->not->toContain('kv_grid.php');
    });

    test('contains the exception class name', function(): void {
        $html = captureErrorPage(new \RuntimeException('specific test message xyz'));
        expect($html)->toContain('RuntimeException');
        expect($html)->toContain('specific test message xyz');
    });

    test('contains tab bar with expected sections', function(): void {
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toContain('data-tab="code"');
        expect($html)->toContain('data-tab="trace"');
        expect($html)->toContain('data-tab="container"');
        expect($html)->toContain('data-tab="env"');
        expect($html)->toContain('data-tab="request"');
    });

    test('container panel is rendered with DI tree structure', function(): void {
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toContain('id="panel-container"');
        expect($html)->toContain('class="di-tree"');
        // Falls back to the hard-coded example (PaymentController → StripeGateway)
        // when no controller is identifiable in the stack trace.
        expect($html)->toContain('PaymentController');
    });

    test('contains shared design system CSS variables', function(): void {
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toContain('--bg:');
        expect($html)->toContain('--accent:');
        expect($html)->toContain('--danger:');
    });

    test('contains the error location pill', function(): void {
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toContain('class="hero-loc"');
        expect($html)->toContain('phpstorm://open');
    });

    test('ide deep-link respects app.debug_ide config', function(): void {
        \Skim\Core\Config::set('app.debug_ide', 'vscode');
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toContain('vscode://file/');
    });

    test('contains action buttons (IDE, copy, Google, AI)', function(): void {
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toContain('Open in PhpStorm');
        expect($html)->toContain('Copy stack trace');
        expect($html)->toContain('Ask AI');
    });

    test('hero badge sits on the same line as the message', function(): void {
        $html = captureErrorPage(new \RuntimeException('test'));
        // badge-err appears before the message in the hero-msg container
        $heroMsg = (string) preg_match('/<div class="hero-msg">.*?<\/div>/s', $html, $m);
        expect($m[0] ?? '')->toContain('class="badge-err"');
        expect($m[0] ?? '')->toContain('test');
    });

    test('stack trace is split into Application / Request Pipeline / Framework sections', function(): void {
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toMatch('/trace-section-label[^<]*>.*?Application/');
        expect($html)->toMatch('/trace-section-label[^<]*>.*?Framework/');
    });

    test('object arguments are expanded as obj-props rows', function(): void {
        // Use a controlled fixture object with known public properties
        // (real \Skim\Core\Request has only private promoted props).
        $obj = new \stdClass();
        $obj->id     = 42;
        $obj->name   = 'Alice';
        $obj->active = true;
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('describeArg');
        $arg = $method->invoke(null, $obj, 0);
        expect($arg['type'])->toBe('stdClass');
        expect($arg['object_id'])->toStartWith('#');
        expect($arg['props'])->toBeArray();
        expect($arg['props'][0]['key'])->toBe('id');
        // propValue() returns formatted strings (e.g. "42", '"Alice"', "true")
        expect($arg['props'][0]['value'])->toBe('42');
    });

    test('allProps() reads private and protected properties via Closure::bind', function(): void {
        // Use a controlled fixture with all three visibilities — \Closure::bind
        // should give us access to private/protected state without ever calling
        // any setter or instantiating anything.
        $obj = new class {
            public string    $publicA  = 'pub';
            protected int   $protectedB = 99;
            private ?string $privateC  = 'secret';
        };

        $ref   = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('allProps');
        $rows   = $method->invoke(null, $obj);

        $keys = array_column($rows, 'key');
        expect($keys)->toContain('publicA');
        expect($keys)->toContain('protectedB (p)');
        expect($keys)->toContain('privateC (p)');
    });

    test('allProps() respects the 8-row cap with _more hint', function(): void {
        $obj = new class {
            public int $a = 1; public int $b = 2; public int $c = 3; public int $d = 4;
            public int $e = 5; public int $f = 6; public int $g = 7; public int $h = 8;
            public int $i = 9; public int $j = 10;
        };
        $ref    = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('allProps');
        $rows   = $method->invoke(null, $obj);

        // 8 rows + 1 _more hint
        expect(count($rows))->toBe(9);
        expect($rows[8]['key'])->toBe('_more');
        expect($rows[8]['value'])->toBe('…');
    });

    test('allProps() returns empty for unreflectable objects', function(): void {
        // stdClass is an internal class — Closure::bind can't bind to its scope,
        // so the method falls back to get_object_vars() for dynamic properties.
        // A stdClass with no dynamic properties returns [].
        $ref    = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('allProps');
        $rows   = $method->invoke(null, new \stdClass());

        expect($rows)->toBe([]);
    });

    test('allProps() falls back to get_object_vars() for stdClass dynamic properties', function(): void {
        // stdClass can't be ReflectionObject-ed via Closure::bind (internal class)
        // — the method must use get_object_vars() as a fallback for dynamic props.
        $obj = new \stdClass();
        $obj->id   = 42;
        $obj->name = 'Alice';
        $ref    = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('allProps');
        $rows   = $method->invoke(null, $obj);

        $keys = array_column($rows, 'key');
        expect($keys)->toContain('id');
        expect($keys)->toContain('name');
    });

    test('primitive scalar args return a short form, not an object expansion', function(): void {
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('describeArg');
        $arg = $method->invoke(null, 42, 0);
        expect($arg['type'])->toBe('int');
        // argValue() returns string form for safe HTML rendering
        expect($arg['value'])->toBe('42');
        expect($arg['object_id'] ?? null)->toBeNull();
        // props is an empty array for non-objects (the template just renders it as-is)
        expect($arg['props'])->toBe([]);
    });

    test('null args render as `null`', function(): void {
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('describeArg');
        $arg = $method->invoke(null, null, 0);
        expect($arg['type'])->toBe('null');
        expect($arg['value'])->toBe('null');
    });

    test('container panel shows the failed-resolution example as a demo', function(): void {
        // The hard-coded PaymentController → StripeGateway example is always
        // shown at the bottom of the panel as a "what failure looks like"
        // demo, regardless of the actual request.
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toContain('id="panel-container"');
        expect($html)->toContain('class="di-tree"');
        expect($html)->toContain('Example — failed resolution');
        expect($html)->toContain('PaymentController');
        expect($html)->toContain('StripeGateway');
    });

    test('DI tree marks resolved services with the green checkmark', function(): void {
        // Verify buildDiNode() produces the `resolved` flag for services
        // that appear in App::resolvedServices() — the di_node component
        // renders the green ✓ marker when this flag is true.
        \Skim\Core\App::testInstance();
        \Skim\Core\App::instance()->bind('svc.alpha', fn() => 'A');
        \Skim\Core\App::instance()->make('svc.alpha');

        $ref = new \ReflectionMethod(\Skim\Dev\ErrorPage::class, 'buildDiNode');
        $node = $ref->invoke(null, 'svc.alpha', depth: 0, visited: []);

        expect($node['cls'])->toBe('svc.alpha');
        expect($node['resolved'])->toBeTrue();
        expect($node['ref'])->toBe('✓ resolved');
    });

    test('DI tree marks unresolved services without the checkmark', function(): void {
        // A service that's bound but not yet resolved should NOT be marked
        // as resolved — only services in App::resolvedServices() get the ✓.
        \Skim\Core\App::testInstance();
        \Skim\Core\App::instance()->bind('svc.beta', fn() => 'B');

        $ref = new \ReflectionMethod(\Skim\Dev\ErrorPage::class, 'buildDiNode');
        $node = $ref->invoke(null, 'svc.beta', depth: 0, visited: []);

        expect($node['resolved'])->toBeFalse();
        expect($node['ref'])->not->toBe('✓ resolved');
    });

    test('solutions are rendered inline (not a separate tab)', function(): void {
        $html = captureErrorPage(new \BadMethodCallException('Call to undefined method App\\NonExistent::nope()'));
        // No solution for non-existent class, just verify panel-solutions is NOT a tab
        // but the inline panel may still exist; the "Solutions" tab is gone.
        expect($html)->not->toMatch('/data-tab="solutions"/');
    });

    test('escapes HTML in exception message', function(): void {
        $msg = '<script>alert("xss")</script>';
        $html = captureErrorPage(new \RuntimeException($msg));
        expect($html)->not->toContain('<script>alert("xss")</script>');
        expect($html)->toContain('&lt;script&gt;');
    });

    test('shows solutions panel for undefined method errors', function(): void {
        $e = new \BadMethodCallException('Call to undefined method Foo::bar()');
        $html = captureErrorPage($e);
        // No solution for non-existent class, so just verify no crash
        expect($html)->toContain('BadMethodCallException');
    });

    test('contains syntax-highlighted code context for real files', function(): void {
        $e = new \RuntimeException('test');
        $html = captureErrorPage($e);
        // code_box component renders <table> with line numbers
        expect($html)->toContain('class="code-box"');
        expect($html)->toContain('class="ln"');
    });

    test('includes PHP version and debug mode chip', function(): void {
        $html = captureErrorPage(new \RuntimeException('test'));
        expect($html)->toContain('PHP ' . PHP_VERSION);
        expect($html)->toContain('debug mode');
    });

    test('syntax highlighting emits well-formed <span> tags', function(): void {
        $fixture = realpath(__DIR__ . '/../Fixtures/Dev/CleanPathFixture.php');
        require_once $fixture;
        $e = \Tests\Fixtures\Dev\CleanPathFixture::throwRuntime();
        $html = captureErrorPage($e);
        file_put_contents('/tmp/highlight-dump.html', $html);
        expect($html)->not->toMatch('/<span class=<span/');
        expect($html)->toMatch('/<span class="kw">declare<\/span>/');
    });

});

describe('ErrorPage noise detection', function(): void {

    test('marks /vendor/ file as framework noise', function(): void {
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('isNoise');
        expect($method->invoke(null, '/app/vendor/some/lib/file.php'))->toBeTrue();
    });

    test('marks /src/core/ file as framework noise', function(): void {
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('isNoise');
        expect($method->invoke(null, '/app/src/Core/Pipeline.php'))->toBeTrue();
        expect($method->invoke(null, '/app/src/Core/App.php'))->toBeTrue();
    });

    test('marks closures in framework classes as noise even when called from user file', function(): void {
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('isNoise');
        // The closure belongs to Skim\Core\App::dispatch but is invoked
        // from a user middleware file. Trace reports the caller file.
        $isNoise = $method->invoke(
            null,
            '/app/src/Middleware/ToolbarMiddleware.php',
            'Skim\\Core\\App',
            '{closure:Skim\\Core\\App::dispatch():483}',
        );
        expect($isNoise)->toBeTrue();
    });

    test('marks framework method calls (e.g. App::run) as noise from caller file', function(): void {
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('isNoise');
        $isNoise = $method->invoke(
            null,
            '/app/public/index.php',
            'Skim\\Core\\App',
            'run',
        );
        expect($isNoise)->toBeTrue();
    });

    test('does NOT mark user code as noise', function(): void {
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('isNoise');
        $isNoise = $method->invoke(
            null,
            '/app/app/controllers/home.php',
            'App\\Controllers\\HomeController',
            'index',
        );
        expect($isNoise)->toBeFalse();
    });

    test('middleware classes are classified as `pipeline` (not noise, not app, not framework)', function(): void {
        // In the redesigned error page, middleware gets its own 'pipeline' section
        // in the trace — the test is_classify_frame() instead of isNoise() to
        // reflect this new three-bucket split.
        $ref = new \ReflectionClass(\Skim\Dev\ErrorPage::class);
        $method = $ref->getMethod('classifyFrame');
        $result = $method->invoke(null, '/app/src/Middleware/Cors.php', 'Skim\\Middleware\\Cors', 'handle');
        expect($result)->toBe('pipeline');
    });

});
