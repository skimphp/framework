<?php declare(strict_types=1);

use Skim\Session\FileSessionDriver;
use Skim\Session\Session;

describe('Session facade', function(): void {

    beforeEach(function(): void {
        $this->sessDir = sys_get_temp_dir() . '/skim_sess_' . uniqid();
        Session::setDriver(new FileSessionDriver($this->sessDir));
    });

    afterEach(function(): void {
        Session::reset();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        array_map('unlink', glob($this->sessDir . '/*') ?: []);
        @rmdir($this->sessDir);
    });

    test('set/get/has/delete round-trip through file driver', function(): void {
        Session::set('user', 'alice');
        expect(Session::has('user'))->toBeTrue();
        expect(Session::get('user'))->toBe('alice');
        expect(Session::get('missing', 'd'))->toBe('d');

        Session::delete('user');
        expect(Session::has('user'))->toBeFalse();
    });

    test('flash value is consumed on first get', function(): void {
        Session::flash('notice', 'saved');
        expect(Session::has('notice'))->toBeTrue();
        expect(Session::get('notice'))->toBe('saved');
        expect(Session::get('notice'))->toBeNull();
    });

    test('id returns non-empty session id after start', function(): void {
        expect(Session::id())->not->toBe('');
    });

    test('regenerate changes the session id', function(): void {
        $old = Session::id();
        Session::regenerate();
        expect(Session::id())->not->toBe($old);
    });

    test('flush destroys all session data', function(): void {
        Session::set('a', 1);
        Session::flush();
        expect(Session::has('a'))->toBeFalse();
    });

    test('start creates the session directory', function(): void {
        Session::start();
        expect(is_dir($this->sessDir))->toBeTrue();
    });

});
