<?php declare(strict_types=1);

namespace Tests\Fixtures\Realtime;

use Skim\Realtime\Sse;

class SilentSse extends \Skim\Realtime\Sse {
    protected function flush(): void {}
}
