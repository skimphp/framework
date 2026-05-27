<?php declare(strict_types=1);

namespace skim\testing;

use skim\auth\auth_service;
use skim\core\app;
use skim\session\session;

class pending_request {
    private ?object $user = null;
    private array $headers = [];
    private array $session = [];
    private array $cookies = [];
    private bool $follow_redirects = false;
    private bool $skip_middleware = false;

    public function __construct(private readonly app $app) {}

    public function acting_as(object $user): static {
        $clone = clone $this;
        $clone->user = $user;
        return $clone;
    }

    public function with_headers(array $headers): static {
        $clone = clone $this;
        $clone->headers = array_merge($this->headers, $headers);
        return $clone;
    }

    public function with_session(array $data): static {
        $clone = clone $this;
        $clone->session = array_merge($this->session, $data);
        return $clone;
    }

    public function with_cookies(array $cookies): static {
        $clone = clone $this;
        $clone->cookies = $cookies;
        return $clone;
    }

    public function following_redirects(): static {
        $clone = clone $this;
        $clone->follow_redirects = true;
        return $clone;
    }

    public function without_middleware(): static {
        $clone = clone $this;
        $clone->skip_middleware = true;
        return $clone;
    }

    public function get(string $path, array $query = []): http_response {
        return $this->send('GET', $path, query: $query);
    }

    public function post(string $path, array $post = [], array $json = []): http_response {
        return $this->send('POST', $path, post: $post, json: $json);
    }

    public function put(string $path, array $post = []): http_response {
        return $this->send('PUT', $path, post: $post);
    }

    public function delete(string $path): http_response {
        return $this->send('DELETE', $path);
    }

    private function send(string $method, string $path, array $query = [], array $post = [], array $json = []): http_response {
        $headers = $this->headers;
        if ($json) {
            $headers['Content-Type'] = 'application/json';
        }

        $req = request_factory::make(
            method: $method,
            path: $path,
            query: $query,
            post: $post,
            headers: $headers,
            raw_body: $json ? json_encode($json) : '',
            cookies: $this->cookies,
        );

        $app = clone $this->app;

        if ($this->user && class_exists(auth_service::class)) {
            $app->bind(auth_service::class, fn(): auth_fake => new auth_fake($this->user));
            $app->bind('auth', fn(): auth_fake => new auth_fake($this->user));
        }

        if ($this->session) {
            $session = new session_fake();
            foreach ($this->session as $k => $v) {
                $session->set($k, $v);
            }
            $app->bind(session_fake::class, fn(): session_fake => $session);
            $app->bind('session', fn(): session_fake => $session);
            if (class_exists(session::class)) {
                $app->bind(session::class, fn(): session_fake => $session);
            }
        }

        $response = new http_response($app->dispatch($req, new \skim\core\response(), skip_middleware: $this->skip_middleware));

        if ($this->follow_redirects && $response->is_redirect()) {
            return $this->get((string) $response->header('Location'));
        }

        return $response;
    }
}
