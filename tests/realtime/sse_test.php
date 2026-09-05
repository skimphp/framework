<?php declare(strict_types=1);

use Skim\Realtime\Sse;

require_once __DIR__ . '/../Fixtures/Realtime/SilentSse.php';

describe('Sse::send()', function(): void {

    test('formats a named event with json data', function(): void {
        $sse = new \Tests\Fixtures\Realtime\SilentSse();
        ob_start();
        $sse->send(['count' => 1], event: 'update', id: '42');
        $output = ob_get_clean();
        expect($output)->toBe("id: 42\nevent: update\ndata: {\"count\":1}\n\n");
    });

    test('formats plain string payload', function(): void {
        $sse = new \Tests\Fixtures\Realtime\SilentSse();
        ob_start();
        $sse->send('hello');
        $output = ob_get_clean();
        expect($output)->toBe("data: hello\n\n");
    });

    test('splits multiline payload into separate data lines', function(): void {
        $sse = new \Tests\Fixtures\Realtime\SilentSse();
        ob_start();
        $sse->send("line1\nline2", event: 'msg');
        $output = ob_get_clean();
        expect($output)->toBe("event: msg\ndata: line1\ndata: line2\n\n");
    });

});

describe('Sse::ping()', function(): void {

    test('sends an sse comment line', function(): void {
        $sse = new \Tests\Fixtures\Realtime\SilentSse();
        ob_start();
        $sse->ping();
        $output = ob_get_clean();
        expect($output)->toBe(": ping\n\n");
    });

});

describe('Sse::close()', function(): void {

    test('sends a close event', function(): void {
        $sse = new \Tests\Fixtures\Realtime\SilentSse();
        ob_start();
        $sse->close();
        $output = ob_get_clean();
        expect($output)->toBe("event: close\ndata: close\n\n");
    });

});
