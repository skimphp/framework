<?php declare(strict_types=1);

use Skim\Cli\ProgressBar;

describe('ProgressBar', function(): void {

    test('renders percentage and counter in plain mode', function(): void {
        ob_start();
        $bar = new ProgressBar(4, 'items');
        $bar->advance();
        $bar->advance();
        $bar->finish('All done');
        $out = ob_get_clean();

        expect($out)->toContain('(4/4)');
        expect($out)->toContain('All done');
    });

    test('advance clamps at total', function(): void {
        ob_start();
        $bar = new ProgressBar(2, 'x');
        $bar->advance(10);
        $out = ob_get_clean();

        expect($out)->toContain('(2/2)');
        expect($out)->not->toContain('(10/2)');
    });

    test('indeterminate mode uses spinner without percentage', function(): void {
        ob_start();
        $bar = new ProgressBar(0, 'spin');
        $bar->advance();
        $bar->advance();
        $bar->finish();
        $out = ob_get_clean();

        expect($out)->toContain('spin');
        expect($out)->toContain('Done');
    });

});
