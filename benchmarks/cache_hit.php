<?php declare(strict_types=1);

define('SKIM_ROOT', dirname(__DIR__));
require SKIM_ROOT . '/vendor/autoload.php';

\Skim\Core\Env::reset();
\Skim\Core\Config::reset();

\Skim\Core\Env::get('APP_NAME');
\Skim\Core\Config::get('app.name');

$iterations = 10000;

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    \Skim\Core\Env::get('APP_NAME');
    \Skim\Core\Config::get('app.name');
}
$end = (hrtime(true) - $start) / 1e6;
$per_call = $end / ($iterations * 2);

echo "cache_hit: " . number_format($per_call, 4) . " ms per call ({$iterations} iterations, 2 calls each)\n";

if ($per_call > 0.05) {
    echo "WARNING: cache hit time exceeds 0.05ms target\n";
    exit(1);
}
