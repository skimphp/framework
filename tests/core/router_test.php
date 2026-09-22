<?php declare(strict_types=1);

use Skim\Core\Router;

describe('router — static routes', function(): void {

    test('matches an exact GET route', function(): void {
        $r = new \Skim\Core\Router();
        $r->add('GET', '/', fn() => 'home');

        $result = $r->dispatch('GET', '/');

        expect($result)->toBeArray()
            ->toHaveKey('handler')
            ->toHaveKey('params');
    });

    test('returns null for unregistered path (404)', function(): void {
        $r = new \Skim\Core\Router();
        $result = $r->dispatch('GET', '/not-found');
        expect($result)->toBeNull();
    });

    test('returns false when method not allowed (405)', function(): void {
        $r = new \Skim\Core\Router();
        $r->add('GET', '/items', fn() => []);

        $result = $r->dispatch('POST', '/items');
        expect($result)->toBeFalse();
    });

});

describe('router — dynamic @param tokens', function(): void {

    test('matches @id and extracts numeric segment', function(): void {
        $r = new \Skim\Core\Router();
        $r->add('GET', '/users/@id', fn() => null);

        $result = $r->dispatch('GET', '/users/42');

        expect($result)->toBeArray();
        expect($result['params']['id'])->toBe('42');
    });

    test('@id:int only matches digits', function(): void {
        $r = new \Skim\Core\Router();
        $r->add('GET', '/users/@id:int', fn() => null);

        expect($r->dispatch('GET', '/users/42'))->toBeArray();
        expect($r->dispatch('GET', '/users/abc'))->toBeNull();
    });

    test('@slug:str matches alphanumeric-dash segments', function(): void {
        $r = new \Skim\Core\Router();
        $r->add('GET', '/posts/@slug:str', fn() => null);

        expect($r->dispatch('GET', '/posts/hello-world'))->toBeArray();
        expect($r->dispatch('GET', '/posts/hello world'))->toBeNull();
    });

});

describe('router — named routes', function(): void {

    test('buildUrl generates correct path from named route', function(): void {
        $r = new \Skim\Core\Router();
        $r->add('GET', '/users/@id:int', fn() => null)->name('user.show');

        $url = $r->buildUrl('user.show', ['id' => 5]);
        expect($url)->toBe('/users/5');
    });

    test('buildUrl throws when name not registered', function(): void {
        $r = new \Skim\Core\Router();
        expect(fn() => $r->buildUrl('nonexistent'))->toThrow(\InvalidArgumentException::class);
    });

    test('buildUrl throws when required param missing', function(): void {
        $r = new \Skim\Core\Router();
        $r->add('GET', '/users/@id', fn() => null)->name('user.show');

        expect(fn() => $r->buildUrl('user.show', []))->toThrow(\InvalidArgumentException::class);
    });

});

describe('Router::url() — static url via app container', function(): void {

    test('App::testInstance() registers sys.router in container', function(): void {
        $app = \Skim\Core\App::testInstance();
        expect($app->get('sys.router'))->toBeInstanceOf(\Skim\Core\Router::class);
    });

    test('sys.router can build named route URLs', function(): void {
        $app = \Skim\Core\App::testInstance();
        $app->router->add('GET', '/posts/@slug:str', fn() => null)->name('post.show');

        $router = $app->get('sys.router');
        expect($router->buildUrl('post.show', ['slug' => 'hello-world']))->toBe('/posts/hello-world');
    });

});

describe('router — groups', function(): void {

    test('group prefix is prepended to all routes inside', function(): void {
        $r = new \Skim\Core\Router();
        $r->group('/api', function(\Skim\Core\Router $r): void {
            $r->add('GET', '/users', fn() => []);
        });

        expect($r->dispatch('GET', '/api/users'))->toBeArray();
        expect($r->dispatch('GET', '/users'))->toBeNull();
    });

    test('group middleware is attached to all routes inside', function(): void {
        $r = new \Skim\Core\Router();
        $r->group('/admin', function(\Skim\Core\Router $r): void {
            $r->add('GET', '/dashboard', fn() => null);
        }, middleware: ['SomeMiddleware']);

        $result = $r->dispatch('GET', '/admin/dashboard');
        expect($result['middleware'])->toContain('SomeMiddleware');
    });

});
