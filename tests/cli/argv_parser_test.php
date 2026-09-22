<?php declare(strict_types=1);

use Skim\Cli\ArgvParser;

describe('ArgvParser', function(): void {

    test('parses command, args and flags from argv', function(): void {
        $p = ArgvParser::parse(['skim', 'cache:clear', 'user:', '--force']);
        expect($p->command)->toBe('cache:clear');
        expect($p->args)->toBe(['clear', 'user:']);
        expect($p->flags)->toBe(['force' => true]);
    });

    test('colon sub-command is prepended to args', function(): void {
        $p = ArgvParser::parse(['skim', 'migrate:down', '5']);
        expect($p->command)->toBe('migrate:down');
        expect($p->args)->toBe(['down', '5']);
    });

    test('sub-command is not duplicated when already first arg', function(): void {
        $p = ArgvParser::parse(['skim', 'migrate:down', 'down']);
        expect($p->args)->toBe(['down']);
    });

    test('defaults to help when no command given', function(): void {
        $p = ArgvParser::parse(['skim']);
        expect($p->command)->toBe('help');
        expect($p->args)->toBe([]);
    });

    test('flags may precede the command', function(): void {
        $p = ArgvParser::parse(['skim', '--env=prod', 'list']);
        expect($p->command)->toBe('list');
        expect($p->flags['env'])->toBe('prod');
    });

    test('--flag=value yields string value, bare flag yields true', function(): void {
        $p = ArgvParser::parse(['skim', 'cmd', '--tag=v2', '--dry']);
        expect($p->flags['tag'])->toBe('v2');
        expect($p->flags['dry'])->toBeTrue();
    });

    test('single-dash flag is recorded without dash', function(): void {
        $p = ArgvParser::parse(['skim', 'cmd', '-v']);
        expect($p->flags['v'])->toBeTrue();
    });

    test('hasFlag matches any of the given names', function(): void {
        $p = ArgvParser::parse(['skim', 'cmd', '--force']);
        expect($p->hasFlag('force', 'f'))->toBeTrue();
        expect($p->hasFlag('dry'))->toBeFalse();
    });

});
