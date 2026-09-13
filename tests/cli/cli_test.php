<?php declare(strict_types=1);

use Skim\Cli\Cli;
use Skim\Cli\ProgressBar;
use Skim\Cli\Command;
use Skim\Cli\ArgvParser;
use Skim\Cli\Kernel;

describe('CLI utility formatting and headers', function(): void {
    beforeEach(function(): void {
        \Skim\Cli\Cli::forcePlain(true);
    });

    test('Cli::line outputs clean lines', function(): void {
        ob_start();
        \Skim\Cli\Cli::line("hello world");
        $out = ob_get_clean();
        expect($out)->toBe("hello world\n");
    });

    test('Cli::bold outputs clean text under forcePlain', function(): void {
        ob_start();
        \Skim\Cli\Cli::bold("bold text");
        $out = ob_get_clean();
        expect($out)->toBe("bold text\n");
    });

    test('Cli::errorBox renders bordered error message', function(): void {
        ob_start();
        \Skim\Cli\Cli::errorBox("Critical", "Something went wrong!");
        $out = ob_get_clean();
        expect($out)->toContain("Critical");
        expect($out)->toContain("Something went wrong!");
        expect($out)->toContain("+-");
        expect($out)->toContain("+-");
    });

    test('Cli::didYouMean outputs suggestions', function(): void {
        ob_start();
        \Skim\Cli\Cli::didYouMean("migrat", ["migrate", "serve", "queue:work"]);
        $out = ob_get_clean();
        expect($out)->toContain("Did you mean:  migrate");
    });

    test('Cli::didYouMean is silent if no close matches', function(): void {
        ob_start();
        \Skim\Cli\Cli::didYouMean("foobar", ["migrate", "serve", "queue:work"]);
        $out = ob_get_clean();
        expect($out)->toBe("");
    });
});

describe('command base class configuration', function(): void {
    test('dynamic configuration resolves command properties based on name', function(): void {
        $cmd = new class extends \Skim\Cli\Command {
            public function handle(): int {
                return 0;
            }
        };

        $cmd->configureForName('migrate:down');
        expect($cmd->getName())->toBe('migrate:down');
        expect($cmd->getGroup())->toBe('database');
        expect($cmd->getDescription())->toBe('rollback last batch');
        expect($cmd->getUsage())->toBe('[--steps=N]');
    });
});

describe('kernel agent mode', function(): void {
    test('list output is compact and machine-readable', function(): void {
        ob_start();
        $code = (new \Skim\Cli\Kernel())->run(\Skim\Cli\ArgvParser::parse(['skim', 'list', '--agent']));
        $out = ob_get_clean();

        expect($code)->toBe(0);
        expect(str_starts_with($out, "command\tusage\tdescription\n"))->toBeTrue();
        expect($out)->toContain("migrate\t\trun pending migrations");
        expect($out)->not->toContain('SKIM Framework CLI');
        expect($out)->not->toContain('Available commands');
    });
});

describe('progress bar functionality', function(): void {
    test('progress bar advances and finishes', function(): void {
        ob_start();
        $bar = new \Skim\Cli\ProgressBar(10, 'Testing');
        $bar->advance(2);
        $bar->finish('Done');
        $out = ob_get_clean();
        expect($out)->toContain('Testing');
        expect($out)->toContain('Done');
    });

    test('spinner mode for unknown total count', function(): void {
        ob_start();
        $bar = new \Skim\Cli\ProgressBar(0, 'Working');
        $bar->advance();
        $bar->finish();
        $out = ob_get_clean();
        expect($out)->toContain('Working');
    });
});
