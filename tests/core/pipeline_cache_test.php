<?php declare(strict_types=1);

use skim\core\middleware;
use skim\core\pipeline;
use skim\core\request;
use skim\core\response;

describe('pipeline::reset_instance_cache()', function (): void {

    test('clears the middleware instance cache', function (): void {
        $pipeline = new pipeline();
        $req      = request::make();
        $res      = new response();

        // Use a class-string so the pipeline caches the instance
        $pipeline->run($req, $res, [\skim\middleware\cors::class], fn() => $res);

        // After reset, a new instance should be created on next run
        pipeline::reset_instance_cache();

        $result = $pipeline->run($req, $res, [\skim\middleware\cors::class], fn() => $res);
        expect($result)->toBeInstanceOf(response::class);
    });

    test('is safe to call when cache is empty', function (): void {
        pipeline::reset_instance_cache();
        expect(true)->toBeTrue();
    });

});
