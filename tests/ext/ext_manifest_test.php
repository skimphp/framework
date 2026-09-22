<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Db\Exceptions\DbException;
use Skim\Ext\ExtManifest;

class ManifestTestExtension extends \Skim\Ext\Extension {
    public string $name = 'acme/auth';
    public string $version = '2.1.0';
    public string $description = 'auth ext';
    public array $capabilities = ['auth'];
    public function register(App $app): void {}
    public function boot(App $app): void {}
    public function commands(): array { return ['auth:install' => 'StdClass']; }
    public function envKeys(): array { return ['AUTH_KEY']; }
}

describe('ExtManifest', function(): void {

    test('generate writes pretty JSON with extension metadata', function(): void {
        $out = sys_get_temp_dir() . '/skim_manifest_' . uniqid() . '.json';
        ExtManifest::generate(new ManifestTestExtension(), $out);

        $data = json_decode(file_get_contents($out), true);
        unlink($out);

        expect($data['name'])->toBe('acme/auth');
        expect($data['version'])->toBe('2.1.0');
        expect($data['capabilities'])->toBe(['auth']);
        expect($data['migrations'])->toBeFalse();
        expect($data['commands'])->toBe(['auth:install']);
        expect($data['env_keys'])->toBe(['AUTH_KEY']);
    });

});

describe('DbException', function(): void {

    test('message includes the failed SQL and wraps previous', function(): void {
        $prev = new \PDOException('no such table');
        $e = new DbException('SELECT * FROM missing', $prev);

        expect($e->getMessage())->toContain('SELECT * FROM missing');
        expect($e->getPrevious())->toBe($prev);
    });

});
