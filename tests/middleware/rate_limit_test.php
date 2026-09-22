<?php declare(strict_types=1);

use Skim\Core\Config;
use Skim\Core\Request;
use Skim\Core\Response;
use Skim\Middleware\RateLimit;

describe('RateLimit', function(): void {

    test('fails open and calls next when redis is unreachable', function(): void {
        Config::set('cache.redis', ['host' => '127.0.0.1', 'port' => 1]);

        $req = Request::make('GET', '/api');
        $res = new Response();
        $called = false;

        $result = (new RateLimit())->handle($req, $res, function($r, $s) use (&$called) {
            $called = true;
            return $s;
        });

        expect($called)->toBeTrue();
        expect($result)->toBe($res);
    });

});
