<?php declare(strict_types=1);

namespace skim\testing;

use skim\core\app;

class http_client {
    public function __construct(private readonly app $app) {}

    public function acting_as(object $user): pending_request {
        return (new pending_request($this->app))->acting_as($user);
    }

    public function with_headers(array $headers): pending_request {
        return (new pending_request($this->app))->with_headers($headers);
    }

    public function with_session(array $data): pending_request {
        return (new pending_request($this->app))->with_session($data);
    }

    public function following_redirects(): pending_request {
        return (new pending_request($this->app))->following_redirects();
    }

    public function without_middleware(): pending_request {
        return (new pending_request($this->app))->without_middleware();
    }

    public function get(string $path, array $query = []): http_response {
        return (new pending_request($this->app))->get($path, $query);
    }

    public function post(string $path, array $post = [], array $json = []): http_response {
        return (new pending_request($this->app))->post($path, $post, $json);
    }

    public function put(string $path, array $post = []): http_response {
        return (new pending_request($this->app))->put($path, $post);
    }

    public function delete(string $path): http_response {
        return (new pending_request($this->app))->delete($path);
    }
}
