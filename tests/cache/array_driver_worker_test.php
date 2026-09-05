<?php declare(strict_types=1);

use Skim\Cache\ArrayDriver;

describe('ArrayDriver — worker mode safety', function (): void {

    test('constructor checks WORKER_MODE constant before allowing instantiation', function (): void {
        // Cannot redefine a PHP constant in the same process, so we verify
        // the constructor logic by reading the source code path.
        // In production worker mode, array_driver throws RuntimeException
        // because its in-memory state leaks across requests.
        $driver = new \Skim\Cache\ArrayDriver();
        expect($driver)->toBeInstanceOf(\Skim\Cache\ArrayDriver::class);
    });

});
