<?php declare(strict_types=1);

use Skim\Cli\Cli;

describe('Cli output helpers', function(): void {

    test('line/info/success/warn/muted/bold print the message', function(): void {
        ob_start();
        Cli::line('plain');
        Cli::info('inf');
        Cli::success('ok');
        Cli::warn('wrn');
        Cli::muted('dim');
        Cli::bold('bld');
        $out = ob_get_clean();

        foreach (['plain', 'inf', 'ok', 'wrn', 'dim', 'bld'] as $w) {
            expect($out)->toContain($w);
        }
    });

    test('error writes to stderr', function(): void {
        // stderr can't be captured with ob — verify it doesn't go to stdout
        ob_start();
        Cli::error('err-msg');
        $out = ob_get_clean();
        expect($out)->toBe('');
    });

    test('table renders headers, rows and borders', function(): void {
        ob_start();
        Cli::table(['Name', 'Age'], [['Alice', 30], ['Bob', 4]]);
        $out = ob_get_clean();

        expect($out)->toContain('| Name  |');
        expect($out)->toContain('| Alice | 30');
        expect($out)->toContain('+');
    });

    test('section uppercases the title', function(): void {
        ob_start();
        Cli::section('cache tools');
        $out = ob_get_clean();
        expect($out)->toContain('CACHE TOOLS');
    });

    test('step renders n/total indicator', function(): void {
        ob_start();
        Cli::step(2, 5, 'migrating');
        Cli::step(3, 5, 'done', 'success');
        Cli::step(4, 5, 'oops', 'error');
        $out = ob_get_clean();
        expect($out)->toContain('[2/5]')->toContain('migrating')
             ->toContain('[3/5]')->toContain('[4/5]');
    });

    test('errorBox renders title and body', function(): void {
        ob_start();
        Cli::errorBox('Failure', 'something broke');
        $out = ob_get_clean();
        expect($out)->toContain('Failure')->toContain('something broke');
    });

    test('divider and newline emit separators', function(): void {
        ob_start();
        Cli::divider('-');
        Cli::newline(2);
        $out = ob_get_clean();
        expect($out)->toContain('-----');
        expect(substr_count($out, "\n"))->toBeGreaterThanOrEqual(3);
    });

    test('duration prints elapsed time', function(): void {
        ob_start();
        Cli::duration(microtime(true) - 0.01);
        $out = ob_get_clean();
        expect($out)->toContain('Done in');
    });

    test('header prints banner metadata in plain mode', function(): void {
        Cli::forcePlain(true);
        ob_start();
        Cli::header('9.9.9', '8.5.0', 'testing', 'Linux x86_64');
        $out = ob_get_clean();
        Cli::forcePlain(false);

        expect($out)->toContain('SKIM Framework CLI (v9.9.9)');
        expect($out)->toContain('PHP: 8.5.0');
        expect($out)->toContain('Env: testing');
    });

    test('progressBar returns a ProgressBar instance', function(): void {
        $bar = Cli::progressBar(10, 'work');
        expect($bar)->toBeInstanceOf(\Skim\Cli\ProgressBar::class);
    });

});
