<?php declare(strict_types=1);

use Skim\Realtime\Sse;

class SilentSse extends \Skim\Realtime\Sse {
    protected function flush(): void {}
}
