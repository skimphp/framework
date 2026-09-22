<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Core\Response;
use Skim\Realtime\Datastar;
use Skim\Realtime\Sse;

function streamSubprocess(string $phpCode): string {
    $proc = proc_open(
        ['php', '-r', $phpCode],
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

describe('Response::stream() driver resolution', function(): void {

    test('passes a plain sse when no driver is configured', function(): void {
        $code = '
            require "vendor/autoload.php";
            \Skim\Core\Config::set("realtime.driver", null);
            $res = new \Skim\Core\Response();
            $received = null;
            $res->stream(function($instance) use (&$received) {
                $received = get_class($instance);
            });
            echo $received;
        ';
        expect(streamSubprocess($code))->toBe(\Skim\Realtime\Sse::class);
    });

    test('uses the configured default driver when no driver arg is given', function(): void {
        $code = '
            require "vendor/autoload.php";
            $res = new \Skim\Core\Response();
            $received = null;
            $res->stream(function($instance) use (&$received) {
                $received = get_class($instance);
            });
            echo $received;
        ';
        expect(streamSubprocess($code))->toBe(\Skim\Realtime\Datastar::class);
    });

    test('resolves a driver from the container when driver arg is given', function(): void {
        $code = '
            require "vendor/autoload.php";
            \Skim\Core\App::instance()->bind(\Skim\Realtime\Contract\ElementPatcher::class, \Skim\Realtime\Datastar::class);
            $res = new \Skim\Core\Response();
            $received = null;
            $res->stream(function($instance) use (&$received) {
                $received = get_class($instance);
            }, driver: \Skim\Realtime\Contract\ElementPatcher::class);
            echo $received;
        ';
        expect(streamSubprocess($code))->toBe(\Skim\Realtime\Datastar::class);
    });

    test('resolves a driver from config when no driver arg is given', function(): void {
        $code = '
            require "vendor/autoload.php";
            \Skim\Core\Config::set("realtime.driver", \Skim\Realtime\Datastar::class);
            $res = new \Skim\Core\Response();
            $received = null;
            $res->stream(function($instance) use (&$received) {
                $received = get_class($instance);
            });
            echo $received;
        ';
        expect(streamSubprocess($code))->toBe(\Skim\Realtime\Datastar::class);
    });

});
