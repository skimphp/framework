<?php declare(strict_types=1);

use Skim\Core\App;
use Skim\Ext\ExtensionManager;

class EmOrderTracker {
    public static array $calls = [];
    public static function reset(): void { self::$calls = []; }
}

class EmFirstExtension extends \Skim\Ext\Extension {
    public function register(App $app): void { EmOrderTracker::$calls[] = 'first.register'; }
    public function boot(App $app): void     { EmOrderTracker::$calls[] = 'first.boot'; }
}

class EmSecondExtension extends \Skim\Ext\Extension {
    public function register(App $app): void { EmOrderTracker::$calls[] = 'second.register'; }
    public function boot(App $app): void     { EmOrderTracker::$calls[] = 'second.boot'; }
}

function writeExtPkg(string $root, string $pkg, array $extra): void {
    mkdir($root . '/vendor/' . $pkg, 0777, true);
    file_put_contents($root . '/vendor/' . $pkg . '/composer.json', json_encode([
        'name'  => $pkg,
        'extra' => ['skim' => $extra],
    ]));
}

describe('ExtensionManager', function(): void {

    beforeEach(function(): void {
        EmOrderTracker::reset();
        $this->root = sys_get_temp_dir() . '/skim_em_' . bin2hex(random_bytes(4));
    });

    afterEach(function(): void {
        $rm = function(string $p) use (&$rm): void {
            if (is_file($p)) { unlink($p); return; }
            foreach (array_diff(scandir($p) ?: [], ['.', '..']) as $c) {
                $rm($p . '/' . $c);
            }
            rmdir($p);
        };
        if (is_dir($this->root)) {
            $rm($this->root);
        }
    });

    test('discover sorts extensions topologically and stores metadata in app', function(): void {
        writeExtPkg($this->root, 'acme/second', [
            'extension' => 'Acme\\Second',
            'requires'  => ['acme/first'],
            'priority'  => 10,
        ]);
        writeExtPkg($this->root, 'acme/first', [
            'extension' => 'Acme\\First',
            'priority'  => 90,
        ]);

        $app = App::testInstance(['app.debug' => false]);
        $mgr = ExtensionManager::discover($this->root, $app);

        $exts = $app->get('sys.extensions');
        expect(array_column($exts, 'name'))->toBe(['acme/first', 'acme/second']);
    });

    test('discover throws when a required capability is missing', function(): void {
        writeExtPkg($this->root, 'acme/needs', [
            'extension' => 'Acme\\Needs',
            'requires'  => ['acme/missing'],
        ]);

        ExtensionManager::discover($this->root, App::testInstance(['app.debug' => false]));
    })->throws(\RuntimeException::class, 'requires');

    test('register and boot call extensions in order', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $mgr = new ExtensionManager([
            ['name' => 'a', 'class' => EmFirstExtension::class,  'priority' => 10],
            ['name' => 'b', 'class' => EmSecondExtension::class, 'priority' => 20],
        ]);

        $mgr->register($app);
        $mgr->boot($app);

        expect(EmOrderTracker::$calls)->toBe([
            'first.register', 'second.register', 'first.boot', 'second.boot',
        ]);
    });

    test('register throws when conflicts were recorded', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $app->set('sys.extension_conflicts', [['message' => 'acme/x conflicts with auth']]);

        $mgr = new ExtensionManager([
            ['name' => 'a', 'class' => EmFirstExtension::class, 'priority' => 10],
        ]);

        $mgr->register($app);
    })->throws(\RuntimeException::class, 'conflict');

    test('replacement extension without declared conflicts throws', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $mgr = new ExtensionManager([
            ['name' => 'a', 'class' => EmFirstExtension::class, 'priority' => 10,
             'type' => 'replacement', 'conflicts' => []],
        ]);

        $mgr->register($app);
    })->throws(\RuntimeException::class, 'conflict scope');

    test('non-autoloadable extension class throws', function(): void {
        $app = App::testInstance(['app.debug' => false]);
        $mgr = new ExtensionManager([
            ['name' => 'a', 'class' => 'No\\Such\\Class', 'priority' => 10],
        ]);

        $mgr->register($app);
    })->throws(\RuntimeException::class, 'not autoloadable');

});
