<?php declare(strict_types=1);

use Skim\Validation\Validate;
use Skim\Validation\Rule;
use Skim\Validation\Result;

describe('validate — required rule', function(): void {

    test('fails when required field is absent', function(): void {
        $v = \Skim\Validation\Validate::make(['name' => ['required']]);
        expect($v->check([])->ok)->toBeFalse();
    });

    test('fails when required field is empty string', function(): void {
        $v = \Skim\Validation\Validate::make(['name' => ['required']]);
        expect($v->check(['name' => ''])->ok)->toBeFalse();
    });

    test('passes when required field has a value', function(): void {
        $v = \Skim\Validation\Validate::make(['name' => ['required']]);
        expect($v->check(['name' => 'John'])->ok)->toBeTrue();
    });

});

describe('validate — type rules', function(): void {

    test('email rule rejects invalid address', function(): void {
        $v      = \Skim\Validation\Validate::make(['email' => ['required', 'email']]);
        $result = $v->check(['email' => 'not-email']);
        expect($result->ok)->toBeFalse();
        expect($result->errors())->toHaveKey('email');
    });

    test('email rule accepts valid address', function(): void {
        $v = \Skim\Validation\Validate::make(['email' => ['required', 'email']]);
        expect($v->check(['email' => 'a@b.com'])->ok)->toBeTrue();
    });

    test('int rule rejects non-numeric string', function(): void {
        $v = \Skim\Validation\Validate::make(['age' => ['required', 'int']]);
        expect($v->check(['age' => 'abc'])->ok)->toBeFalse();
    });

    test('min rule fails when numeric value is below threshold', function(): void {
        $v = \Skim\Validation\Validate::make(['age' => ['required', 'int', 'min:18']]);
        expect($v->check(['age' => '16'])->ok)->toBeFalse();
    });

    test('max rule fails when numeric value exceeds threshold', function(): void {
        $v = \Skim\Validation\Validate::make(['age' => ['required', 'int', 'max:99']]);
        expect($v->check(['age' => '100'])->ok)->toBeFalse();
    });

    test('in rule fails when value not in list', function(): void {
        $v = \Skim\Validation\Validate::make(['role' => ['required', 'in:admin,user,guest']]);
        expect($v->check(['role' => 'superuser'])->ok)->toBeFalse();
    });

    test('in rule passes when value is in list', function(): void {
        $v = \Skim\Validation\Validate::make(['role' => ['required', 'in:admin,user,guest']]);
        expect($v->check(['role' => 'admin'])->ok)->toBeTrue();
    });

    test('same rule fails when fields do not match', function(): void {
        $v = \Skim\Validation\Validate::make([
            'password' => ['required'],
            'confirm'  => ['required', 'same:password'],
        ]);
        $result = $v->check(['password' => 'abc123', 'confirm' => 'different']);
        expect($result->ok)->toBeFalse();
    });

});

describe('validate — smart min/max', function(): void {

    test('min rule works on string length', function(): void {
        $v = \Skim\Validation\Validate::make(['username' => ['required', 'min:3']]);
        expect($v->check(['username' => 'ab'])->ok)->toBeFalse();
    });

    test('min rule passes when string meets minimum length', function(): void {
        $v = \Skim\Validation\Validate::make(['username' => ['required', 'min:3']]);
        expect($v->check(['username' => 'john'])->ok)->toBeTrue();
    });

    test('max rule works on string length', function(): void {
        $v = \Skim\Validation\Validate::make(['username' => ['required', 'max:5']]);
        expect($v->check(['username' => 'toolong'])->ok)->toBeFalse();
    });

    test('max rule passes when string within limit', function(): void {
        $v = \Skim\Validation\Validate::make(['username' => ['required', 'max:10']]);
        expect($v->check(['username' => 'john'])->ok)->toBeTrue();
    });

});

describe('validate — bool rule', function(): void {

    test('bool rule accepts true-like values', function(): void {
        $v = \Skim\Validation\Validate::make(['active' => ['required', 'bool']]);
        expect($v->check(['active' => '1'])->ok)->toBeTrue();
        expect($v->check(['active' => 'true'])->ok)->toBeTrue();
        expect($v->check(['active' => 'yes'])->ok)->toBeTrue();
        expect($v->check(['active' => 'on'])->ok)->toBeTrue();
    });

    test('bool rule accepts false-like values', function(): void {
        $v = \Skim\Validation\Validate::make(['active' => ['required', 'bool']]);
        expect($v->check(['active' => '0'])->ok)->toBeTrue();
        expect($v->check(['active' => 'false'])->ok)->toBeTrue();
        expect($v->check(['active' => 'no'])->ok)->toBeTrue();
        expect($v->check(['active' => 'off'])->ok)->toBeTrue();
    });

    test('bool rule rejects invalid values', function(): void {
        $v = \Skim\Validation\Validate::make(['active' => ['required', 'bool']]);
        expect($v->check(['active' => 'maybe'])->ok)->toBeFalse();
    });

});

describe('validate — nullable rule', function(): void {

    test('nullable field with null value passes and returns null', function(): void {
        $v = \Skim\Validation\Validate::make(['bio' => ['nullable']]);
        $result = $v->check(['bio' => null]);
        expect($result->ok)->toBeTrue();
        expect($result->validated()['bio'])->toBeNull();
    });

    test('nullable field with value passes validation', function(): void {
        $v = \Skim\Validation\Validate::make(['bio' => ['nullable', 'min:10']]);
        $result = $v->check(['bio' => 'Hello world!']);
        expect($result->ok)->toBeTrue();
    });

    test('nullable field with invalid value fails', function(): void {
        $v = \Skim\Validation\Validate::make(['bio' => ['nullable', 'min:10']]);
        $result = $v->check(['bio' => 'short']);
        expect($result->ok)->toBeFalse();
    });

    test('nullable field absent from data passes', function(): void {
        $v = \Skim\Validation\Validate::make(['bio' => ['nullable']]);
        expect($v->check([])->ok)->toBeTrue();
    });

});

describe('validate — optional fields', function(): void {

    test('optional field with no value passes validation', function(): void {
        $v = \Skim\Validation\Validate::make(['website' => ['url']]);
        expect($v->check([])->ok)->toBeTrue();
    });

    test('optional field with invalid value fails', function(): void {
        $v = \Skim\Validation\Validate::make(['website' => ['url']]);
        expect($v->check(['website' => 'not-a-url'])->ok)->toBeFalse();
    });

});

describe('validate — extend() custom rules', function(): void {

    test('custom rule via extend() passes when callback returns true', function(): void {
        $v = \Skim\Validation\Validate::make(['count' => ['required', 'even_number']])
            ->extend('even_number', fn($v) => (int)$v % 2 === 0, message: 'Must be even');
        expect($v->check(['count' => '4'])->ok)->toBeTrue();
    });

    test('custom rule via extend() fails when callback returns false', function(): void {
        $v = \Skim\Validation\Validate::make(['count' => ['required', 'even_number']])
            ->extend('even_number', fn($v) => (int)$v % 2 === 0, message: 'Must be even');
        $result = $v->check(['count' => '3']);
        expect($result->ok)->toBeFalse();
        expect($result->errors()['count'][0])->toContain('Must be even');
    });

    test('extend() returns static for fluent chaining', function(): void {
        $v = \Skim\Validation\Validate::make(['count' => ['required', 'even_number']])
            ->extend('even_number', fn($v) => (int)$v % 2 === 0)
            ->extend('positive', fn($v) => (int)$v > 0);
        expect($v)->toBeInstanceOf(\Skim\Validation\Validate::class);
    });

    test('custom rules are instance-scoped (not global)', function(): void {
        $v1 = \Skim\Validation\Validate::make(['count' => ['required', 'even_number']])
            ->extend('even_number', fn($v) => (int)$v % 2 === 0);

        $v2 = \Skim\Validation\Validate::make(['count' => ['required', 'even_number']]);

        expect($v1->check(['count' => '4'])->ok)->toBeTrue();
        // v2 doesn't have the rule registered — unknown rules silently pass
        expect($v2->check(['count' => '3'])->ok)->toBeTrue();
    });

});

describe('validate — rule objects', function(): void {

    test('rule object passes when validate() returns true', function(): void {
        $rule = new class implements \Skim\Validation\Rule {
            public function validate(mixed $value, string $field, array $data): bool {
                return strlen($value) >= 3;
            }
            public function message(string $field): string {
                return "The {$field} is too short.";
            }
        };

        $v = \Skim\Validation\Validate::make(['code' => ['required', $rule]]);
        expect($v->check(['code' => 'abc'])->ok)->toBeTrue();
    });

    test('rule object fails when validate() returns false', function(): void {
        $rule = new class implements \Skim\Validation\Rule {
            public function validate(mixed $value, string $field, array $data): bool {
                return strlen($value) >= 3;
            }
            public function message(string $field): string {
                return "The {$field} is too short.";
            }
        };

        $v = \Skim\Validation\Validate::make(['code' => ['required', $rule]]);
        $result = $v->check(['code' => 'ab']);
        expect($result->ok)->toBeFalse();
        expect($result->errors()['code'][0])->toBe('The code is too short.');
    });

});

describe('validate — inline callables', function(): void {

    test('callable passes when returns truthy', function(): void {
        $v = \Skim\Validation\Validate::make(['code' => ['required', fn($v) => strlen($v) >= 3]]);
        expect($v->check(['code' => 'abc'])->ok)->toBeTrue();
    });

    test('callable fails when returns falsy', function(): void {
        $v = \Skim\Validation\Validate::make(['code' => ['required', fn($v) => strlen($v) >= 3]]);
        $result = $v->check(['code' => 'ab']);
        expect($result->ok)->toBeFalse();
        expect($result->errors()['code'][0])->toContain('invalid');
    });

});

describe('validate — validated() whitelist', function(): void {

    test('validated() returns only declared fields', function(): void {
        $v      = \Skim\Validation\Validate::make(['name' => ['required'], 'email' => ['required', 'email']]);
        $result = $v->check(['name' => 'John', 'email' => 'j@j.com', 'extra_field' => 'danger']);
        expect($result->validated())->toHaveKey('name')
                                     ->toHaveKey('email')
                                     ->not->toHaveKey('extra_field');
    });

    test('errors() returns field→[messages] map', function(): void {
        $v      = \Skim\Validation\Validate::make(['email' => ['required', 'email']]);
        $result = $v->check(['email' => 'bad']);
        expect($result->errors()['email'])->toBeArray()->not->toBeEmpty();
    });

});

describe('validate — typed validated() values', function(): void {

    test('int rule casts validated value to integer', function(): void {
        $v = \Skim\Validation\Validate::make(['age' => ['required', 'int']]);
        $result = $v->check(['age' => '25']);
        expect($result->validated()['age'])->toBeInt();
        expect($result->validated()['age'])->toBe(25);
    });

    test('float rule casts validated value to float', function(): void {
        $v = \Skim\Validation\Validate::make(['price' => ['required', 'float']]);
        $result = $v->check(['price' => '19.99']);
        expect($result->validated()['price'])->toBeFloat();
        expect($result->validated()['price'])->toBe(19.99);
    });

    test('bool rule casts validated value to boolean', function(): void {
        $v = \Skim\Validation\Validate::make(['active' => ['required', 'bool']]);
        $result = $v->check(['active' => '1']);
        expect($result->validated()['active'])->toBeTrue();

        $result = $v->check(['active' => '0']);
        expect($result->validated()['active'])->toBeFalse();
    });

    test('email rule casts validated value via filter', function(): void {
        $v = \Skim\Validation\Validate::make(['email' => ['required', 'email']]);
        $result = $v->check(['email' => 'Test@Example.COM']);
        expect($result->validated()['email'])->toBe('test@example.com');
    });

});

describe('validate — pipe syntax', function(): void {

    test('pipe-delimited rules work correctly', function(): void {
        $v = \Skim\Validation\Validate::make(['email' => 'required|email']);
        expect($v->check(['email' => 'a@b.com'])->ok)->toBeTrue();
        expect($v->check(['email' => 'bad'])->ok)->toBeFalse();
    });

    test('pipe syntax with params works', function(): void {
        $v = \Skim\Validation\Validate::make(['age' => 'required|int|min:18']);
        expect($v->check(['age' => '20'])->ok)->toBeTrue();
        expect($v->check(['age' => '15'])->ok)->toBeFalse();
    });

});

describe('result — property hook', function(): void {

    test('ok is true when errors are empty', function(): void {
        $r = new \Skim\Validation\Result([], ['name' => 'John']);
        expect($r->ok)->toBeTrue();
    });

    test('ok is false when errors exist', function(): void {
        $r = new \Skim\Validation\Result(['name' => ['Required']], []);
        expect($r->ok)->toBeFalse();
    });

});
