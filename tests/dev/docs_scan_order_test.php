<?php declare(strict_types=1);

use Skim\Dev\Docs\Extractor\ProjectScanner;

describe('ProjectScanner ordering', function(): void {

    test('scan order is stable regardless of input file creation order', function(): void {
        $dir = sys_get_temp_dir() . '/skim-scan-order-' . uniqid();
        mkdir($dir . '/sub', 0777, true);
        // Create files in deliberately non-sorted order.
        foreach (['zebra.php', 'apple.php', 'sub/mango.php', 'sub/banana.php'] as $rel) {
            file_put_contents(
                $dir . '/' . $rel,
                "<?php declare(strict_types=1);\nnamespace TmpScan;\nclass " . ucfirst(pathinfo($rel, PATHINFO_FILENAME)) . " {}",
            );
        }

        $symbols = fn(): array => array_map(
            fn($c) => $c->symbol !== '' ? $c->symbol : $c->namespace . '\\' . $c->className,
            (new ProjectScanner())->scanPaths([$dir]),
        );

        $first = $symbols();
        // Recreate one file to perturb filesystem readdir order, then rescan.
        unlink($dir . '/apple.php');
        file_put_contents($dir . '/apple.php', "<?php declare(strict_types=1);\nnamespace TmpScan;\nclass Apple {}");
        $second = $symbols();

        expect($second)->toBe($first);
        expect($first)->toBe(array_values(array_unique($first)));
        expect($first)->not->toBeEmpty();

        // Scanned file order must equal byte-sorted path order.
        $files = array_map(fn($c) => $c->file, (new ProjectScanner())->scanPaths([$dir]));
        $sortedFiles = $files;
        sort($sortedFiles, SORT_STRING);
        expect($files)->toBe($sortedFiles);

        array_map(unlink(...), glob($dir . '/sub/*.php'));
        rmdir($dir . '/sub');
        array_map(unlink(...), glob($dir . '/*.php'));
        rmdir($dir);
    });

});
