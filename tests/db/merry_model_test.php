<?php declare(strict_types=1);

use Skim\Db\Db;
use Skim\Db\MerryModel;

class MmUser extends MerryModel {
    protected static string $table = 'mm_users';
    protected static array $hasMany = ['posts' => ['class' => MmPost::class, 'fk' => 'user_id']];
}

class MmPost extends MerryModel {
    protected static string $table = 'mm_posts';
    protected static array $belongsTo  = ['author' => ['class' => MmUser::class, 'fk' => 'user_id']];
    protected static array $manyToMany = ['tags'   => ['class' => MmTag::class, 'pivot' => 'mm_post_tag', 'fk' => 'post_id', 'rfk' => 'tag_id']];
}

class MmTag extends MerryModel {
    protected static string $table = 'mm_tags';
}

function setupMmDb(): void {
    Db::reset();
    Db::connect('default', ['driver' => 'sqlite', 'database' => ':memory:']);
    Db::query('CREATE TABLE mm_users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    Db::query('CREATE TABLE mm_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');
    Db::query('CREATE TABLE mm_tags (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT)');
    Db::query('CREATE TABLE mm_post_tag (post_id INTEGER, tag_id INTEGER)');

    Db::query("INSERT INTO mm_users (id, name) VALUES (1, 'Ann'), (2, 'Bob')");
    Db::query("INSERT INTO mm_posts (id, user_id, title) VALUES (1, 1, 'p1'), (2, 1, 'p2'), (3, 2, 'p3')");
    Db::query("INSERT INTO mm_tags (id, label) VALUES (1, 'red'), (2, 'blue')");
    Db::query("INSERT INTO mm_post_tag (post_id, tag_id) VALUES (1, 1), (1, 2), (2, 2)");
}

describe('MerryModel eager loading', function(): void {

    beforeEach(fn() => setupMmDb());

    test('with() eager-loads hasMany relation', function(): void {
        $users = MmUser::with(MmUser::where(['id' => 1])->all(), 'posts');

        expect($users[0]->load('posts'))->toHaveCount(2);
        expect($users[0]->load('posts')[0]->title)->toBe('p1');
    });

    test('with() eager-loads belongsTo relation', function(): void {
        $posts = MmPost::with(MmPost::where([])->all(), 'author');

        expect($posts[0]->load('author')->name)->toBe('Ann');
        expect($posts[2]->load('author')->name)->toBe('Bob');
    });

    test('with() eager-loads manyToMany through pivot', function(): void {
        $posts = MmPost::with(MmPost::where(['id' => 1])->all(), 'tags');
        $tags  = $posts[0]->load('tags');

        expect($tags)->toHaveCount(2);
        expect(array_map(fn($t) => $t->label, $tags))->toContain('red', 'blue');
    });

    test('with() supports nested dot notation', function(): void {
        $users = MmUser::with(MmUser::where(['id' => 1])->all(), 'posts.tags');

        expect($users[0]->load('posts')[0]->load('tags'))->toHaveCount(2);
    });

    test('unknown relation throws', function(): void {
        expect(fn() => MmUser::with(MmUser::where(['id' => 1])->all(), 'nope'))
            ->toThrow(\InvalidArgumentException::class);
    });

});

describe('MerryModel lazy loading', function(): void {

    beforeEach(fn() => setupMmDb());

    test('load() lazy-loads relation on first call', function(): void {
        $post = MmPost::find(1);
        expect($post->load('author')->name)->toBe('Ann');
        expect($post->load('tags'))->toHaveCount(2);
    });

});

describe('MerryModel pivot operations', function(): void {

    beforeEach(fn() => setupMmDb());

    test('attach inserts pivot rows', function(): void {
        $post = MmPost::find(3);
        $post->attach('tags', [1, 2]);

        expect($post->load('tags'))->toHaveCount(2);
    });

    test('detach removes pivot rows', function(): void {
        $post = MmPost::find(1);
        $post->detach('tags', [1]);

        $labels = array_map(fn($t) => $t->label, MmPost::find(1)->load('tags'));
        expect($labels)->toBe(['blue']);
    });

    test('sync aligns pivot rows to given ids', function(): void {
        $post = MmPost::find(1);
        $post->sync('tags', [2]);

        $labels = array_map(fn($t) => $t->label, MmPost::find(1)->load('tags'));
        expect($labels)->toBe(['blue']);
    });

});
