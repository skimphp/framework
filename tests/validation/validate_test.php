<?php declare(strict_types=1);

use skim\validation\validate;

describe('validate — required rule', function(): void {

    test('fails when required field is absent', function(): void {
        $v = validate::make(['name' => ['required']]);
        expect($v->check([])->ok())->toBeFalse();
    });

    test('fails when required field is empty string', function(): void {
        $v = validate::make(['name' => ['required']]);
        expect($v->check(['name' => ''])->ok())->toBeFalse();
    });

    test('passes when required field has a value', function(): void {
        $v = validate::make(['name' => ['required']]);
        expect($v->check(['name' => 'John'])->ok())->toBeTrue();
    });

});

describe('validate — type rules', function(): void {

    test('email rule rejects invalid address', function(): void {
        $v      = validate::make(['email' => ['required', 'email']]);
        $result = $v->check(['email' => 'not-email']);
        expect($result->ok())->toBeFalse();
        expect($result->errors())->toHaveKey('email');
    });

    test('email rule accepts valid address', function(): void {
        $v = validate::make(['email' => ['required', 'email']]);
        expect($v->check(['email' => 'a@b.com'])->ok())->toBeTrue();
    });

    test('int rule rejects non-numeric string', function(): void {
        $v = validate::make(['age' => ['required', 'int']]);
        expect($v->check(['age' => 'abc'])->ok())->toBeFalse();
    });

    test('min rule fails when value is below threshold', function(): void {
        $v = validate::make(['age' => ['required', 'int', 'min:18']]);
        expect($v->check(['age' => '16'])->ok())->toBeFalse();
    });

    test('max rule fails when value exceeds threshold', function(): void {
        $v = validate::make(['age' => ['required', 'int', 'max:99']]);
        expect($v->check(['age' => '100'])->ok())->toBeFalse();
    });

    test('min_len rule fails when string too short', function(): void {
        $v = validate::make(['username' => ['required', 'min_len:3']]);
        expect($v->check(['username' => 'ab'])->ok())->toBeFalse();
    });

    test('max_len rule fails when string too long', function(): void {
        $v = validate::make(['username' => ['required', 'max_len:5']]);
        expect($v->check(['username' => 'toolong'])->ok())->toBeFalse();
    });

    test('in rule fails when value not in list', function(): void {
        $v = validate::make(['role' => ['required', 'in:admin,user,guest']]);
        expect($v->check(['role' => 'superuser'])->ok())->toBeFalse();
    });

    test('in rule passes when value is in list', function(): void {
        $v = validate::make(['role' => ['required', 'in:admin,user,guest']]);
        expect($v->check(['role' => 'admin'])->ok())->toBeTrue();
    });

    test('same rule fails when fields do not match', function(): void {
        $v = validate::make([
            'password' => ['required'],
            'confirm'  => ['required', 'same:password'],
        ]);
        $result = $v->check(['password' => 'abc123', 'confirm' => 'different']);
        expect($result->ok())->toBeFalse();
    });

});

describe('validate — optional fields', function(): void {

    test('optional field with no value passes validation', function(): void {
        $v = validate::make(['website' => ['url']]);
        expect($v->check([])->ok())->toBeTrue();
    });

    test('optional field with invalid value fails', function(): void {
        $v = validate::make(['website' => ['url']]);
        expect($v->check(['website' => 'not-a-url'])->ok())->toBeFalse();
    });

});

describe('validate — custom rules', function(): void {

    test('custom rule passes when callback returns true', function(): void {
        validate::rule('even_number', fn($v) => (int)$v % 2 === 0, message: 'Must be even');
        $v = validate::make(['count' => ['required', 'even_number']]);
        expect($v->check(['count' => '4'])->ok())->toBeTrue();
    });

    test('custom rule fails when callback returns false', function(): void {
        validate::rule('even_number', fn($v) => (int)$v % 2 === 0, message: 'Must be even');
        $v = validate::make(['count' => ['required', 'even_number']]);
        expect($v->check(['count' => '3'])->ok())->toBeFalse();
    });

});

describe('validate — validated() whitelist', function(): void {

    test('validated() returns only declared fields', function(): void {
        $v      = validate::make(['name' => ['required'], 'email' => ['required', 'email']]);
        $result = $v->check(['name' => 'John', 'email' => 'j@j.com', 'extra_field' => 'danger']);
        expect($result->validated())->toHaveKey('name')
                                     ->toHaveKey('email')
                                     ->not->toHaveKey('extra_field');
    });

    test('errors() returns field→[messages] map', function(): void {
        $v      = validate::make(['email' => ['required', 'email']]);
        $result = $v->check(['email' => 'bad']);
        expect($result->errors()['email'])->toBeArray()->not->toBeEmpty();
    });

});
