<?php declare(strict_types=1);

use skim\core\app;
use skim\core\response;
use skim\realtime\datastar;
use skim\realtime\sse;

function stream_subprocess(string $php_code): string {
    $proc = proc_open(
        ['php', '-r', $php_code],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return trim($stdout . $stderr);
}

describe('response::stream() driver resolution', function(): void {

    test('passes a plain sse when no driver is configured', function(): void {
        $code = '
            require "vendor/autoload.php";
            $res = new \skim\core\response();
            $received = null;
            $res->stream(function($instance) use (&$received) {
                $received = get_class($instance);
            });
            echo $received;
        ';
        expect(stream_subprocess($code))->toBe(sse::class);
    });

    test('resolves a driver from the container when driver arg is given', function(): void {
        $code = '
            require "vendor/autoload.php";
            \skim\core\app::instance()->bind(\skim\realtime\contract\element_patcher::class, \skim\realtime\datastar::class);
            $res = new \skim\core\response();
            $received = null;
            $res->stream(function($instance) use (&$received) {
                $received = get_class($instance);
            }, driver: \skim\realtime\contract\element_patcher::class);
            echo $received;
        ';
        expect(stream_subprocess($code))->toBe(datastar::class);
    });

    test('resolves a driver from config when no driver arg is given', function(): void {
        $code = '
            require "vendor/autoload.php";
            \skim\core\config::set("realtime.driver", \skim\realtime\datastar::class);
            $res = new \skim\core\response();
            $received = null;
            $res->stream(function($instance) use (&$received) {
                $received = get_class($instance);
            });
            echo $received;
        ';
        expect(stream_subprocess($code))->toBe(datastar::class);
    });

});
