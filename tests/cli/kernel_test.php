<?php declare(strict_types=1);

use Skim\Cli\ArgvParser;
use Skim\Cli\Command;
use Skim\Cli\Kernel;
use Skim\Core\Config;

class KernelOkCommand extends Command {
    public function handle(): int {
        $this->line('ok:' . $this->arg(0, 'none'));
        return 0;
    }
}

class KernelFailCommand extends Command {
    public function handle(): int {
        throw new \RuntimeException('boom');
    }
}

describe('kernel dispatch', function(): void {

    test('--version prints version and exits 0', function(): void {
        ob_start();
        $code = (new Kernel())->run(ArgvParser::parse(['skim', '--version']));
        $out = ob_get_clean();

        expect($code)->toBe(0);
        expect($out)->toContain('SKIM Framework CLI v' . Kernel::VERSION);
    });

    test('user command from config/app.php is dispatched', function(): void {
        Config::set('app.commands', ['my:cmd' => KernelOkCommand::class]);

        ob_start();
        $code = (new Kernel())->run(ArgvParser::parse(['skim', 'my:cmd', 'hello', '--quiet']));
        $out = ob_get_clean();

        expect($code)->toBe(0);
        expect($out)->toContain('ok:cmd'); // sub-command prepended as arg(0)
    });

    test('colon fallback resolves base command when sub not registered', function(): void {
        Config::set('app.commands', ['mycmd' => KernelOkCommand::class]);

        ob_start();
        $code = (new Kernel())->run(ArgvParser::parse(['skim', 'mycmd:extra', '--quiet']));
        $out = ob_get_clean();

        expect($code)->toBe(0);
        expect($out)->toContain('ok:extra');
    });

    test('unknown command exits 1 with error output', function(): void {
        ob_start();
        $code = (new Kernel())->run(ArgvParser::parse(['skim', 'nosuchcmd', '--quiet']));
        $out = ob_get_clean();

        expect($code)->toBe(1);
        expect($out)->toContain('nosuchcmd');
    });

    test('command exception returns 1 and prints error box', function(): void {
        Config::set('app.commands', ['fail' => KernelFailCommand::class]);

        ob_start();
        $code = (new Kernel())->run(ArgvParser::parse(['skim', 'fail', '--quiet']));
        $out = ob_get_clean();

        expect($code)->toBe(1);
        expect($out)->toContain('boom');
    });

    test('list prints grouped commands in plain mode', function(): void {
        ob_start();
        $code = (new Kernel())->run(ArgvParser::parse(['skim', 'list', '--no-ansi']));
        $out = ob_get_clean();

        expect($code)->toBe(0);
        expect($out)->toContain('AVAILABLE COMMANDS');
        expect($out)->toContain('migrate');
    });

});
