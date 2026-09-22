<?php declare(strict_types=1);

// Measures App::instance() boot time in isolation.
// Target: < 1ms

require __DIR__ . '/../../vendor/autoload.php';

$start = hrtime(true);
$app = Skim\Core\App::instance();
$ms = (hrtime(true) - $start) / 1e6;

echo "boot_isolation: {$ms} ms\n";

if ($ms > 1.0) {
    fwrite(STDERR, "FAIL: app::instance() took > 1ms\n");
    exit(1);
}

echo "OK\n";
