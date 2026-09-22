<?php declare(strict_types=1);

namespace Tests\Fixtures\Props;

readonly class CardProps {
    public function __construct(
        public string $title = 'Card'
    ) {}
}
