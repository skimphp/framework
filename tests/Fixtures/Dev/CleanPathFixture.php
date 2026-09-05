<?php declare(strict_types=1);

class CleanPathFixture {
    public static function throw_runtime(): \RuntimeException {
        return new \RuntimeException('fixture test error');
    }
}
