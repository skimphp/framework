<?php declare(strict_types=1);

use skim\helpers\filter;

describe('filter::int()', function(): void {

    test('returns int for valid numeric string', function(): void {
        expect(filter::int('42'))->toBe(42);
    });

    test('returns false for non-numeric string', function(): void {
        expect(filter::int('abc'))->toBeFalse();
    });

    test('returns false when below min', function(): void {
        expect(filter::int('5', min: 10))->toBeFalse();
    });

    test('returns false when above max', function(): void {
        expect(filter::int('150', max: 100))->toBeFalse();
    });

    test('returns value when within min/max range', function(): void {
        expect(filter::int('50', min: 1, max: 100))->toBe(50);
    });

    test('int_positive rejects zero and negative', function(): void {
        expect(filter::int_positive('0'))->toBeFalse();
        expect(filter::int_positive('-1'))->toBeFalse();
        expect(filter::int_positive('1'))->toBe(1);
    });

    test('int_natural accepts zero', function(): void {
        expect(filter::int_natural('0'))->toBe(0);
        expect(filter::int_natural('-1'))->toBeFalse();
    });

    test('returns false for float string', function(): void {
        expect(filter::int('1.5'))->toBeFalse();
        expect(filter::int('1.0'))->toBeFalse();
        expect(filter::int('0.9'))->toBeFalse();
    });

    test('accepts string with leading plus sign', function(): void {
        expect(filter::int('+5'))->toBe(5);
    });

});

describe('filter::float()', function(): void {

    test('returns float for numeric value', function(): void {
        expect(filter::float('3.14'))->toBe(3.14);
    });

    test('returns false for non-numeric input', function(): void {
        expect(filter::float('abc'))->toBeFalse();
    });

    test('returns false outside range', function(): void {
        expect(filter::float('1.5', min: 2.0))->toBeFalse();
    });

});

describe('filter::bool()', function(): void {

    test('accepts truthy string values', function(): void {
        foreach (['1', 'true', 'yes', 'on'] as $v) {
            expect(filter::bool($v))->toBeTrue("Expected '{$v}' to be true");
        }
    });

    test('accepts falsy string values', function(): void {
        foreach (['0', 'false', 'no', 'off'] as $v) {
            expect(filter::bool($v))->toBeFalse("Expected '{$v}' to be false");
        }
    });

    test('returns false for unrecognised string', function(): void {
        expect(filter::bool('maybe'))->toBeFalse();
    });

});

describe('filter::email()', function(): void {

    test('returns lowercased email for valid address', function(): void {
        expect(filter::email('USER@Example.COM'))->toBe('user@example.com');
    });

    test('returns false for invalid email', function(): void {
        expect(filter::email('not-an-email'))->toBeFalse();
    });

});

describe('filter::url()', function(): void {

    test('returns url string for valid URL', function(): void {
        expect(filter::url('https://example.com/path'))->toBe('https://example.com/path');
    });

    test('returns false for missing scheme', function(): void {
        expect(filter::url('example.com'))->toBeFalse();
    });

});

describe('filter::slug()', function(): void {

    test('accepts lowercase alphanumeric dash', function(): void {
        expect(filter::slug('hello-world-123'))->toBe('hello-world-123');
    });

    test('rejects uppercase or spaces', function(): void {
        expect(filter::slug('Hello World'))->toBeFalse();
    });

});

describe('filter::in()', function(): void {

    test('returns value when in allowed list', function(): void {
        expect(filter::in('admin', ['admin', 'user']))->toBe('admin');
    });

    test('returns false when not in list', function(): void {
        expect(filter::in('superuser', ['admin', 'user']))->toBeFalse();
    });

});

describe('filter::arr_int()', function(): void {

    test('filters out non-numeric values', function(): void {
        expect(filter::arr_int([1, 'x', '3', null, 0]))->toBe([1, 3, 0]);
    });

    test('arr_int_positive filters out zero and negative', function(): void {
        expect(filter::arr_int_positive([0, 1, -1, 2]))->toBe([1, 2]);
    });

});

describe('filter::arr_in()', function(): void {

    test('returns only values present in allowed list', function(): void {
        expect(filter::arr_in(['a', 'x', 'b'], ['a', 'b', 'c']))->toBe(['a', 'b']);
    });

});
