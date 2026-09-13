<?php declare(strict_types=1);

namespace Tests\Fixtures\Props;

readonly class AlertProps {
    public function __construct(
        public string $message,
        public string $type = 'info'
    ) {}
}
