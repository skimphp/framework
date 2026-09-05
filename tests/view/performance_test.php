<?php declare(strict_types=1);

use Skim\View\View;

beforeEach(function(): void {
    \Skim\View\View::reset();
    \Skim\View\View::setPath(dirname(__DIR__) . '/Fixtures/Views');
});

describe('fragment render performance', function(): void {

    test('fragment render is faster than full render', function(): void {
        $data = ['name' => 'Alice', 'stats_value' => '42', 'users' => ['Bob', 'Charlie']];

        // Warmup
        \Skim\View\View::render('pages/dashboard', $data, 'stats_widget');
        \Skim\View\View::render('pages/dashboard', $data);

        $runs = 200;

        $t1 = microtime(true);
        for ($i = 0; $i < $runs; $i++) {
            \Skim\View\View::render('pages/dashboard', $data, 'stats_widget');
        }
        $fragment_time = microtime(true) - $t1;

        $t2 = microtime(true);
        for ($i = 0; $i < $runs; $i++) {
            \Skim\View\View::render('pages/dashboard', $data);
        }
        $full_time = microtime(true) - $t2;

        $ratio = $full_time / max($fragment_time, 0.00001);
        expect($ratio)->toBeGreaterThan(1.5,
            "Fragment render ({$fragment_time}s) should be faster than full render ({$full_time}s), ratio was {$ratio}"
        );
    });

    test('fragment mode does not call layout methods', function(): void {
        // When fragment is requested, layout should be bypassed.
        // We verify by checking the output does not contain layout elements.
        $data = ['name' => 'Alice', 'stats_value' => '42', 'users' => ['Bob', 'Charlie']];
        $html = \Skim\View\View::render('pages/dashboard', $data, 'stats_widget');
        expect($html)->not->toContain('<html>');
        expect($html)->not->toContain('<main>');
    });

});
