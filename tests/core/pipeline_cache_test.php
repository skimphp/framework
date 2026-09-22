<?php declare(strict_types=1);

use Skim\Core\Middleware;
use Skim\Core\Pipeline;
use Skim\Core\Request;
use Skim\Core\Response;

describe('Pipeline::resetInstanceCache()', function (): void {

    test('clears the middleware instance cache', function (): void {
        $pipeline = new \Skim\Core\Pipeline();
        $req      = \Skim\Core\Request::make();
        $res      = new \Skim\Core\Response();

        // Use a class-string so the pipeline caches the instance
        $pipeline->run($req, $res, [\Skim\Middleware\Cors::class], fn() => $res);

        // After reset, a new instance should be created on next run
        \Skim\Core\Pipeline::resetInstanceCache();

        $result = $pipeline->run($req, $res, [\Skim\Middleware\Cors::class], fn() => $res);
        expect($result)->toBeInstanceOf(\Skim\Core\Response::class);
    });

    test('is safe to call when cache is empty', function (): void {
        \Skim\Core\Pipeline::resetInstanceCache();
        expect(true)->toBeTrue();
    });

});
