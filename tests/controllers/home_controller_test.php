<?php declare(strict_types=1);

// Starter-controller autoload smoke: App\ is mapped via composer autoload-dev
// (root checkout only). Installed library consumers define their own App\
// mapping, so this package must never ship App\ in production autoload.
describe('App\\Controllers\\HomeController autoload contract', function (): void {

    test('starter controller autoloads in root checkout', function (): void {
        expect(class_exists(\App\Controllers\HomeController::class))->toBeTrue();
    });

    test('index() returns greeting response', function (): void {
        $res = (new \App\Controllers\HomeController())->index();
        expect($res)->toBeInstanceOf(\Skim\Core\Response::class);
    });

});
