<?php declare(strict_types=1);

class clean_path_fixture {
    public static function throw_runtime(): \RuntimeException {
        return new \RuntimeException('fixture test error');
    }
}
