<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Core\Response;
use Skim\Testing\AuthFake;
use Skim\Testing\HttpClient;
use Skim\Testing\PendingRequest;
use Skim\Testing\SessionFake;

class BlockingMiddleware implements \Skim\Core\Middleware {
    public function handle(\Skim\Core\Request $req, Response $res, callable $next): Response {
        return $res->status(403);
    }
}

describe('AuthFake', function(): void {

    test('reports authenticated user', function(): void {
        $user = (object) ['id' => 7, 'email' => 'a@b.c'];
        $auth = new AuthFake($user);
        expect($auth->check())->toBeTrue();
        expect($auth->guest())->toBeFalse();
        expect($auth->user())->toBe($user);
        expect($auth->id())->toBe(7);
    });

    test('id() returns null when user has no id', function(): void {
        expect((new AuthFake((object) []))->id())->toBeNull();
    });

});

describe('SessionFake', function(): void {

    test('set/get/has/flush/all round-trip in memory', function(): void {
        $s = new SessionFake();
        expect($s->has('k'))->toBeFalse();
        expect($s->get('k', 'def'))->toBe('def');
        $s->set('k', 'v');
        expect($s->has('k'))->toBeTrue();
        expect($s->get('k'))->toBe('v');
        expect($s->all())->toBe(['k' => 'v']);
        $s->flush();
        expect($s->all())->toBe([]);
    });

});

describe('Testing\\HttpResponse assertions', function(): void {

    test('status shorthands assert the matching code', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->get('/created', fn(\Skim\Core\Request $req, Response $res): Response => $res->status(201));
        $app->router->get('/empty', fn(\Skim\Core\Request $req, Response $res): Response => $res->status(204));
        $app->router->get('/bad', fn(\Skim\Core\Request $req, Response $res): Response => $res->status(422));

        $client = new HttpClient($app);
        $client->get('/created')->assertCreated();
        $client->get('/empty')->assertNoContent();
        $client->get('/bad')->assertUnprocessable();
    });

    test('assertRedirect, assertHeader, assertContains read the response', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->get('/red', fn(\Skim\Core\Request $req, Response $res): Response => $res->redirect('/there'));
        $app->router->get('/text', fn(\Skim\Core\Request $req, Response $res): Response => $res->withHeader('X-B', 'bv')->setBody('hello world'));

        $client = new HttpClient($app);
        $res = $client->get('/red');
        expect($res->isRedirect())->toBeTrue();
        $res->assertRedirect('/there')->assertHeader('Location', '/there');

        $client->get('/text')->assertContains('hello')->assertHeader('X-B', 'bv');
    });

    test('accessors expose status, json, body and headers', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->get('/api', fn(\Skim\Core\Request $req, Response $res): Response => $res->status(201)->json(['n' => 1]));

        $res = (new HttpClient($app))->get('/api');
        expect($res->status())->toBe(201);
        expect($res->json())->toBe(['n' => 1]);
        expect($res->body())->toBeJson();
        expect($res->header('missing'))->toBeNull();
        expect($res->isRedirect())->toBeFalse();
    });

});

describe('Testing\\HttpClient', function(): void {

    test('get() dispatches a route in-process', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->get('/ping', fn(): array => ['ok' => true]);

        (new HttpClient($app))->get('/ping')
            ->assertOk()
            ->assertJson(['ok' => true]);
    });

    test('post() with json body reaches the route', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->post('/echo', function(\Skim\Core\Request $r): array {
            return ['got' => $r->json()];
        });

        (new HttpClient($app))->post('/echo', json: ['x' => 1])
            ->assertOk()
            ->assertJson(['got' => ['x' => 1]]);
    });

    test('unknown path returns 404', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        (new HttpClient($app))->get('/nope')->assertNotFound();
    });

    test('followingRedirects() follows a redirect chain', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->get('/old', fn(\Skim\Core\Request $req, Response $res): Response => $res->redirect('/new'));
        $app->router->get('/new', fn(): array => ['landed' => true]);

        (new HttpClient($app))->followingRedirects()->get('/old')
            ->assertOk()
            ->assertJson(['landed' => true]);
    });

    test('withoutMiddleware() skips short-circuiting middleware', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->use(BlockingMiddleware::class);
        $app->router->get('/guarded', fn(): array => ['ok' => true]);

        $client = new HttpClient($app);
        $client->get('/guarded')->assertForbidden();
        $client->withoutMiddleware()->get('/guarded')->assertOk();
    });

    test('put() and delete() dispatch the matching verb', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->put('/item', fn(): array => ['verb' => 'put']);
        $app->router->delete('/item', fn(): array => ['verb' => 'delete']);

        $client = new HttpClient($app);
        $client->put('/item')->assertJson(['verb' => 'put']);
        $client->delete('/item')->assertJson(['verb' => 'delete']);
    });

    test('withHeaders() sends custom headers to the route', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->get('/hdr', fn(\Skim\Core\Request $r): array => ['v' => $r->header('x-a')]);

        (new HttpClient($app))->withHeaders(['X-A' => 'hello'])->get('/hdr')
            ->assertJson(['v' => 'hello']);
    });

    test('withSession() binds SessionFake into the request', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->router->get('/sess', function() use ($app): array {
            $s = App::instance()->make('session');
            return ['v' => $s instanceof SessionFake ? $s->get('k') : null];
        });

        (new HttpClient($app))->withSession(['k' => 'sv'])->get('/sess')
            ->assertJson(['v' => 'sv']);
    });

    test('PendingRequest config methods return clones', function(): void {
        $app  = App::testInstance(['app.debug' => false]);
        $base = new PendingRequest($app);
        $req  = $base->withHeaders(['X-A' => '1'])->withSession(['k' => 'v'])->actingAs((object) ['id' => 1]);

        expect($req)->toBeInstanceOf(PendingRequest::class);
        expect($req)->not->toBe($base);
    });

});
