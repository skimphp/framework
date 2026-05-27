<?php declare(strict_types=1);

use skim\cli\cli;
use skim\cli\progress_bar;
use skim\cli\command;

describe('CLI utility formatting and headers', function(): void {
    beforeEach(function(): void {
        cli::force_plain(true);
    });

    test('cli::line outputs clean lines', function(): void {
        ob_start();
        cli::line("hello world");
        $out = ob_get_clean();
        expect($out)->toBe("hello world\n");
    });

    test('cli::bold outputs clean text under force_plain', function(): void {
        ob_start();
        cli::bold("bold text");
        $out = ob_get_clean();
        expect($out)->toBe("bold text\n");
    });

    test('cli::error_box renders bordered error message', function(): void {
        ob_start();
        cli::error_box("Critical", "Something went wrong!");
        $out = ob_get_clean();
        expect($out)->toContain("Critical");
        expect($out)->toContain("Something went wrong!");
        expect($out)->toContain("+-");
        expect($out)->toContain("+-");
    });

    test('cli::did_you_mean outputs suggestions', function(): void {
        ob_start();
        cli::did_you_mean("migrat", ["migrate", "serve", "queue:work"]);
        $out = ob_get_clean();
        expect($out)->toContain("Did you mean:  migrate");
    });

    test('cli::did_you_mean is silent if no close matches', function(): void {
        ob_start();
        cli::did_you_mean("foobar", ["migrate", "serve", "queue:work"]);
        $out = ob_get_clean();
        expect($out)->toBe("");
    });
});

describe('command base class configuration', function(): void {
    test('dynamic configuration resolves command properties based on name', function(): void {
        $cmd = new class extends command {
            public function handle(): int {
                return 0;
            }
        };

        $cmd->configure_for_name('migrate:down');
        expect($cmd->get_name())->toBe('migrate:down');
        expect($cmd->get_group())->toBe('database');
        expect($cmd->get_description())->toBe('rollback last batch');
        expect($cmd->get_usage())->toBe('[--steps=N]');
    });
});

describe('progress bar functionality', function(): void {
    test('progress bar advances and finishes', function(): void {
        ob_start();
        $bar = new progress_bar(10, 'Testing');
        $bar->advance(2);
        $bar->finish('Done');
        $out = ob_get_clean();
        expect($out)->toContain('Testing');
        expect($out)->toContain('Done');
    });

    test('spinner mode for unknown total count', function(): void {
        ob_start();
        $bar = new progress_bar(0, 'Working');
        $bar->advance();
        $bar->finish();
        $out = ob_get_clean();
        expect($out)->toContain('Working');
    });
});
