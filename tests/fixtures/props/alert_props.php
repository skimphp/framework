<?php declare(strict_types=1);

namespace tests\fixtures\props;

readonly class alert_props {
    public function __construct(
        public string $message,
        public string $type = 'info'
    ) {}
}
