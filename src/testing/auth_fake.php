<?php declare(strict_types=1);

namespace skim\testing;

class auth_fake {
    public function __construct(private readonly object $user) {}
    public function user(): object  { return $this->user; }
    public function check(): bool   { return true; }
    public function guest(): bool   { return false; }
    public function id(): mixed     { return $this->user->id ?? null; }
}
