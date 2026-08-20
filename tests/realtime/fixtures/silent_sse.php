<?php declare(strict_types=1);

use skim\realtime\sse;

class silent_sse extends sse {
    protected function flush(): void {}
}
