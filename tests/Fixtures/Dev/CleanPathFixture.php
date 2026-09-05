<?php declare(strict_types=1);

class CleanPathFixture {
    public static function throwRuntime(): \RuntimeException {
        return new \RuntimeException('fixture test error');
    }
}
