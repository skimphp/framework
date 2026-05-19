<?php declare(strict_types=1);

use skim\dev\docs\extractor\annotation_parser;

describe('annotation_parser — @ai.* tag extraction', function(): void {

    test('extracts a single @ai.contract tag', function(): void {
        $parser = new annotation_parser();
        $doc    = '/** @ai.contract returns null when key is absent */';
        $result = $parser->parse($doc);
        expect($result['contract'])->toBe(['returns null when key is absent']);
    });

    test('extracts multiple occurrences of the same tag', function(): void {
        $parser = new annotation_parser();
        $doc    = <<<'DOC'
        /**
         * @ai.contract first contract
         * @ai.contract second contract
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result['contract'])->toHaveCount(2);
        expect($result['contract'][0])->toBe('first contract');
        expect($result['contract'][1])->toBe('second contract');
    });

    test('extracts multiple different tag types', function(): void {
        $parser = new annotation_parser();
        $doc    = <<<'DOC'
        /**
         * @ai.contract does the thing
         * @ai.invariant state is always valid
         * @ai.non_goal does not validate input
         * @ai.side_effect writes to Redis
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result)->toHaveKey('contract');
        expect($result)->toHaveKey('invariant');
        expect($result)->toHaveKey('non_goal');
        expect($result)->toHaveKey('side_effect');
    });

    test('ignores non-@ai.* tags silently', function(): void {
        $parser = new annotation_parser();
        $doc    = <<<'DOC'
        /**
         * @param string $x
         * @return void
         * @ai.contract does the thing
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result)->toHaveKey('contract');
        expect($result)->not->toHaveKey('param');
        expect($result)->not->toHaveKey('return');
    });

    test('returns empty array for docblock with no @ai.* tags', function(): void {
        $parser = new annotation_parser();
        $result = $parser->parse('/** Just a summary. @param int $x */');
        expect($result)->toBe([]);
    });

    test('tag values are trimmed', function(): void {
        $parser = new annotation_parser();
        $doc    = '/**  @ai.contract   lots of whitespace   */';
        $result = $parser->parse($doc);
        expect($result['contract'][0])->toBe('lots of whitespace');
    });

    test('@ai-contract hyphen tag is parsed into the contract key', function(): void {
        $parser = new annotation_parser();
        $doc    = <<<'DOC'
        /**
         * @ai-contract returns null when not found
         * @ai-contract never throws on miss
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result)->toHaveKey('contract');
        expect($result['contract'])->toHaveCount(2);
        expect($result['contract'][0])->toBe('returns null when not found');
        expect($result['contract'][1])->toBe('never throws on miss');
    });

    test('hyphen and dot tags in the same docblock both parse correctly', function(): void {
        $parser = new annotation_parser();
        $doc    = <<<'DOC'
        /**
         * @ai-contract some contract
         * @ai.invariant some invariant
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result)->toHaveKey('contract');
        expect($result)->toHaveKey('invariant');
    });

});

describe('annotation_parser — extract_summary()', function(): void {

    test('returns first non-tag non-empty line', function(): void {
        $parser = new annotation_parser();
        $doc    = <<<'DOC'
        /**
         * This is the summary.
         * @ai.contract something
         */
        DOC;
        expect($parser->extract_summary($doc))->toBe('This is the summary.');
    });

    test('returns empty string when docblock has only tags', function(): void {
        $parser = new annotation_parser();
        $doc    = '/** @ai.contract only tags here */';
        expect($parser->extract_summary($doc))->toBe('');
    });

    test('returns empty string for empty docblock', function(): void {
        $parser = new annotation_parser();
        expect($parser->extract_summary('/**  */'))->toBe('');
    });

});
