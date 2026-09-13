<?php declare(strict_types=1);

use Skim\Helpers\Filter;

describe('Filter::int()', function(): void {

    test('returns int for valid numeric string', function(): void {
        expect(\Skim\Helpers\Filter::int('42'))->toBe(42);
    });

    test('returns false for non-numeric string', function(): void {
        expect(\Skim\Helpers\Filter::int('abc'))->toBeFalse();
    });

    test('returns false when below min', function(): void {
        expect(\Skim\Helpers\Filter::int('5', min: 10))->toBeFalse();
    });

    test('returns false when above max', function(): void {
        expect(\Skim\Helpers\Filter::int('150', max: 100))->toBeFalse();
    });

    test('returns value when within min/max range', function(): void {
        expect(\Skim\Helpers\Filter::int('50', min: 1, max: 100))->toBe(50);
    });

    test('intPositive rejects zero and negative', function(): void {
        expect(\Skim\Helpers\Filter::intPositive('0'))->toBeFalse();
        expect(\Skim\Helpers\Filter::intPositive('-1'))->toBeFalse();
        expect(\Skim\Helpers\Filter::intPositive('1'))->toBe(1);
    });

    test('intNatural accepts zero', function(): void {
        expect(\Skim\Helpers\Filter::intNatural('0'))->toBe(0);
        expect(\Skim\Helpers\Filter::intNatural('-1'))->toBeFalse();
    });

    test('returns false for float string', function(): void {
        expect(\Skim\Helpers\Filter::int('1.5'))->toBeFalse();
        expect(\Skim\Helpers\Filter::int('1.0'))->toBeFalse();
        expect(\Skim\Helpers\Filter::int('0.9'))->toBeFalse();
    });

    test('accepts string with leading plus sign', function(): void {
        expect(\Skim\Helpers\Filter::int('+5'))->toBe(5);
    });

});

describe('Filter::float()', function(): void {

    test('returns float for numeric value', function(): void {
        expect(\Skim\Helpers\Filter::float('3.14'))->toBe(3.14);
    });

    test('returns false for non-numeric input', function(): void {
        expect(\Skim\Helpers\Filter::float('abc'))->toBeFalse();
    });

    test('returns false outside range', function(): void {
        expect(\Skim\Helpers\Filter::float('1.5', min: 2.0))->toBeFalse();
    });

});

describe('Filter::bool()', function(): void {

    test('accepts truthy string values', function(): void {
        foreach (['1', 'true', 'yes', 'on'] as $v) {
            expect(\Skim\Helpers\Filter::bool($v))->toBeTrue("Expected '{$v}' to be true");
        }
    });

    test('accepts falsy string values', function(): void {
        foreach (['0', 'false', 'no', 'off'] as $v) {
            expect(\Skim\Helpers\Filter::bool($v))->toBeFalse("Expected '{$v}' to be false");
        }
    });

    test('returns false for unrecognised string', function(): void {
        expect(\Skim\Helpers\Filter::bool('maybe'))->toBeFalse();
    });

});

describe('Filter::email()', function(): void {

    test('returns lowercased email for valid address', function(): void {
        expect(\Skim\Helpers\Filter::email('USER@Example.COM'))->toBe('user@example.com');
    });

    test('returns false for invalid email', function(): void {
        expect(\Skim\Helpers\Filter::email('not-an-email'))->toBeFalse();
    });

});

describe('Filter::url()', function(): void {

    test('returns url string for valid URL', function(): void {
        expect(\Skim\Helpers\Filter::url('https://example.com/path'))->toBe('https://example.com/path');
    });

    test('returns false for missing scheme', function(): void {
        expect(\Skim\Helpers\Filter::url('example.com'))->toBeFalse();
    });

});

describe('Filter::slug()', function(): void {

    test('accepts lowercase alphanumeric dash', function(): void {
        expect(\Skim\Helpers\Filter::slug('hello-world-123'))->toBe('hello-world-123');
    });

    test('rejects uppercase or spaces', function(): void {
        expect(\Skim\Helpers\Filter::slug('Hello World'))->toBeFalse();
    });

});

describe('Filter::in()', function(): void {

    test('returns value when in allowed list', function(): void {
        expect(\Skim\Helpers\Filter::in('admin', ['admin', 'user']))->toBe('admin');
    });

    test('returns false when not in list', function(): void {
        expect(\Skim\Helpers\Filter::in('superuser', ['admin', 'user']))->toBeFalse();
    });

});

describe('Filter::arrInt()', function(): void {

    test('filters out non-numeric values', function(): void {
        expect(\Skim\Helpers\Filter::arrInt([1, 'x', '3', null, 0]))->toBe([1, 3, 0]);
    });

    test('arrIntPositive filters out zero and negative', function(): void {
        expect(\Skim\Helpers\Filter::arrIntPositive([0, 1, -1, 2]))->toBe([1, 2]);
    });

});

describe('Filter::arrIn()', function(): void {

    test('returns only values present in allowed list', function(): void {
        expect(\Skim\Helpers\Filter::arrIn(['a', 'x', 'b'], ['a', 'b', 'c']))->toBe(['a', 'b']);
    });

});
