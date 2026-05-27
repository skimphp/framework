<?php declare(strict_types=1);

namespace skim\testing;

use skim\core\response;

class http_response {
    public function __construct(private readonly response $res) {}

    public function assert_status(int $code): static {
        expect($this->res->get_status())->toBe($code);
        return $this;
    }

    public function assert_ok(): static           { return $this->assert_status(200); }
    public function assert_created(): static      { return $this->assert_status(201); }
    public function assert_no_content(): static   { return $this->assert_status(204); }
    public function assert_not_found(): static    { return $this->assert_status(404); }
    public function assert_unauthorized(): static { return $this->assert_status(401); }
    public function assert_forbidden(): static    { return $this->assert_status(403); }
    public function assert_unprocessable(): static { return $this->assert_status(422); }

    public function assert_redirect(string $url): static {
        expect($this->is_redirect())->toBeTrue();
        expect($this->res->get_header('Location'))->toBe($url);
        return $this;
    }

    public function assert_json(array $data): static {
        expect($this->res->get_json())->toMatchArray($data);
        return $this;
    }

    public function assert_header(string $key, string $value): static {
        expect($this->res->get_header($key))->toBe($value);
        return $this;
    }

    public function assert_contains(string $text): static {
        expect($this->res->get_body())->toContain($text);
        return $this;
    }

    public function status(): int   { return $this->res->get_status(); }
    public function json(): array   { return $this->res->get_json(); }
    public function body(): string  { return $this->res->get_body(); }
    public function header(string $key): ?string { return $this->res->get_header($key); }

    public function is_redirect(): bool {
        return in_array($this->res->get_status(), [301, 302, 303, 307, 308], true);
    }

    public function dump(): static  { dump($this->res->get_body()); return $this; }
    public function dd(): never     { dd($this->res->get_body()); }
}
