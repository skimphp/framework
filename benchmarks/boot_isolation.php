<?php declare(strict_types=1);

define('SKIM_ROOT', dirname(__DIR__));
require SKIM_ROOT . '/vendor/autoload.php';

\skim\core\env::reset();
\skim\core\config::reset();

$start = hrtime(true);
$app = \skim\core\app::instance();
$end = (hrtime(true) - $start) / 1e6;

echo "boot_isolation: {$end} ms\n";

if ($end > 1.0) {
    echo "WARNING: boot time exceeds 1ms target\n";
    exit(1);
}
