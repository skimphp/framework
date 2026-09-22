<?php declare(strict_types=1);

use Skim\Realtime\Datastar;
use Skim\Realtime\Sse;

require_once __DIR__ . '/../Fixtures/Realtime/SilentSse.php';

describe('Datastar::signals()', function(): void {

    test('emits datastar-patch-signals with json signals', function(): void {
        $ds = new \Skim\Realtime\Datastar(new \Tests\Fixtures\Realtime\SilentSse());
        ob_start();
        $ds->signals(['loading' => false, 'count' => 3]);
        $output = ob_get_clean();
        expect($output)->toContain("event: datastar-patch-signals\n");
        expect($output)->toContain('data: signals {"loading":false,"count":3}');
    });

    test('adds onlyIfMissing line when flag is true', function(): void {
        $ds = new \Skim\Realtime\Datastar(new \Tests\Fixtures\Realtime\SilentSse());
        ob_start();
        $ds->signals(['theme' => 'dark'], onlyIfMissing: true);
        $output = ob_get_clean();
        expect($output)->toContain("event: datastar-patch-signals\n");
        expect($output)->toContain("data: onlyIfMissing true\n");
    });

});

describe('Datastar::patch()', function(): void {

    test('emits datastar-patch-elements with selector and mode', function(): void {
        $ds = new \Skim\Realtime\Datastar(new \Tests\Fixtures\Realtime\SilentSse());
        ob_start();
        $ds->patch('<div id="x">hi</div>', '#x', 'inner');
        $output = ob_get_clean();
        expect($output)->toBe(
            "event: datastar-patch-elements\n"
            . "data: selector #x\n"
            . "data: mode inner\n"
            . "data: elements <div id=\"x\">hi</div>\n"
            . "\n"
        );
    });

    test('omits selector and mode when defaults are used', function(): void {
        $ds = new \Skim\Realtime\Datastar(new \Tests\Fixtures\Realtime\SilentSse());
        ob_start();
        $ds->patch('<span>ok</span>');
        $output = ob_get_clean();
        expect($output)->toBe(
            "event: datastar-patch-elements\n"
            . "data: elements <span>ok</span>\n"
            . "\n"
        );
    });

    test('prefixes every line of multiline html with elements', function(): void {
        $ds = new \Skim\Realtime\Datastar(new \Tests\Fixtures\Realtime\SilentSse());
        ob_start();
        $ds->patch("<div>\n  hello\n</div>", '#box');
        $output = ob_get_clean();
        expect($output)->toBe(
            "event: datastar-patch-elements\n"
            . "data: selector #box\n"
            . "data: elements <div>\n"
            . "data: elements   hello\n"
            . "data: elements </div>\n"
            . "\n"
        );
    });

});

describe('Datastar::remove()', function(): void {

    test('emits datastar-patch-elements with mode remove', function(): void {
        $ds = new \Skim\Realtime\Datastar(new \Tests\Fixtures\Realtime\SilentSse());
        ob_start();
        $ds->remove('#toast');
        $output = ob_get_clean();
        expect($output)->toBe(
            "event: datastar-patch-elements\n"
            . "data: selector #toast\n"
            . "data: mode remove\n"
            . "\n"
        );
    });

});

describe('Datastar::run()', function(): void {

    test('appends a script element to body', function(): void {
        $ds = new \Skim\Realtime\Datastar(new \Tests\Fixtures\Realtime\SilentSse());
        ob_start();
        $ds->run("alert('ok')");
        $output = ob_get_clean();
        expect($output)->toContain("event: datastar-patch-elements\n");
        expect($output)->toContain("data: selector body\n");
        expect($output)->toContain("data: mode append\n");
        expect($output)->toContain("data: elements <script>alert('ok')</script>\n");
    });

});
