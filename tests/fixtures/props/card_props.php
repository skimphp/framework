<?php declare(strict_types=1);

namespace tests\fixtures\props;

readonly class card_props {
    public function __construct(
        public string $title = 'Card'
    ) {}
}
