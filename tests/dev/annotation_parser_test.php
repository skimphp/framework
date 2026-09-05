<?php declare(strict_types=1);

use Skim\Dev\Docs\Extractor\AnnotationParser;

// ---------------------------------------------------------------------------
// annotation_parser — legacy @ai- / @ai. style
// ---------------------------------------------------------------------------

describe('AnnotationParser::parse — legacy @ai- style', function () {

    it('extracts single @ai-contract tag', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $result = $parser->parse('/** @ai-contract returns cached value or default */');
        expect($result)->toHaveKey('contract')
            ->and($result['contract'][0])->toBe('returns cached value or default');
    });

    it('extracts single @ai. tag (dot separator)', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $result = $parser->parse('/** @ai.invariant driver reused until reset */');
        expect($result['invariant'][0])->toBe('driver reused until reset');
    });

    it('collects multiple occurrences of the same tag', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $doc = <<<'DOC'
        /**
         * @ai-contract first guarantee
         * @ai-contract second guarantee
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result['contract'])->toHaveCount(2)
            ->and($result['contract'][0])->toBe('first guarantee')
            ->and($result['contract'][1])->toBe('second guarantee');
    });

    it('appends continuation lines to current tag value', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $doc = <<<'DOC'
        /**
         * @ai-contract returns existing value
         *   or stores callback result on miss
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result['contract'][0])->toContain('stores callback result on miss');
    });

    it('ignores unknown tags silently', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $result = $parser->parse('/** @param string $key @ai-contract stores value */');
        expect($result)->toHaveKey('contract')
            ->and($result)->not->toHaveKey('param');
    });

    it('flushes current tag when a new @tag line starts (not only blank lines)', function () {
        // regression: old code lost value when next line started with @
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $doc = <<<'DOC'
        /**
         * @ai-contract first contract
         * @ai-throws RuntimeException on missing driver
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result['contract'][0])->toBe('first contract')
            ->and($result['throws'][0])->toBe('RuntimeException on missing driver');
    });

    it('extracts summary before first tag', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $doc = <<<'DOC'
        /**
         * Returns the active driver instance.
         *
         * @ai-contract returns cached driver or resolves one
         */
        DOC;
        $summary = $parser->extractSummary($doc);
        expect($summary)->toContain('Returns the active driver instance');
    });

    it('returns empty summary when no pre-tag lines exist', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $result = $parser->extractSummary('/** @ai-contract only a tag */');
        expect($result)->toBe('');
    });

    it('preserves multi-line summary', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $doc = <<<'DOC'
        /**
         * Static cache facade over the configured backend driver.
         * Swap the backend in config/cache.php without changing application code.
         *
         * @ai-contract stores value
         */
        DOC;
        $summary = $parser->extractSummary($doc);
        expect($summary)->toContain('Static cache facade')
            ->and($summary)->toContain('Swap the backend');
    });

});

// ---------------------------------------------------------------------------
// annotation_parser — new #AI style (better-commenting-v3)
// ---------------------------------------------------------------------------

describe('AnnotationParser::parseHashAi — #AI semicolon style', function () {

    it('parses single-line #AI with multiple key:value pairs', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $source = '#AI role:static cache facade; layer:cache; lifecycle:driver resolved lazily;';
        $result = $parser->parseHashAi($source);
        expect($result['role'])->toBe('static cache facade')
            ->and($result['layer'])->toBe('cache')
            ->and($result['lifecycle'])->toBe('driver resolved lazily');
    });

    it('parses bracket list values without splitting them', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $source = '#AI owns:[driver instance]; entry_points:[remember,get,set,has];';
        $result = $parser->parseHashAi($source);
        expect($result['owns'])->toBe(['driver instance'])
            ->and($result['entry_points'])->toBe(['remember', 'get', 'set', 'has']);
    });

    it('parses multi-line #AI block', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $block = <<<'BLOCK'
        #AI role:static cache facade; layer:cache;
        #AI owns:[driver instance cache]; entry_points:[remember,get,set];
        #AI invariants:[driver reused until reset,remember computes only on miss];
        BLOCK;
        $result = $parser->parseHashAi($block);
        expect($result['role'])->toBe('static cache facade')
            ->and($result['owns'])->toBe(['driver instance cache'])
            ->and($result['invariants'])->toBe(['driver reused until reset', 'remember computes only on miss']);
    });

    it('ignores lines that do not start with #AI', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $source = "some prose\n#AI role:facade;\nmore prose";
        $result = $parser->parseHashAi($source);
        expect($result)->toHaveKey('role')
            ->and($result)->not->toHaveKey('some prose');
    });

});

// ---------------------------------------------------------------------------
// AnnotationParser::parseBracketList
// ---------------------------------------------------------------------------

describe('AnnotationParser::parseBracketList', function () {

    it('splits [a,b,c] into trimmed array', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $result = $parser->parseBracketList('[remember,get,set,has]');
        expect($result)->toBe(['remember', 'get', 'set', 'has']);
    });

    it('trims whitespace inside brackets', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $result = $parser->parseBracketList('[ driver instance , active backend ]');
        expect($result)->toBe(['driver instance', 'active backend']);
    });

    it('returns single-item array for value without brackets', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $result = $parser->parseBracketList('array_driver');
        expect($result)->toBe(['array_driver']);
    });

    it('returns empty array for empty brackets', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $result = $parser->parseBracketList('[]');
        expect($result)->toBe([]);
    });

});

// ---------------------------------------------------------------------------
// annotation_parser — #AI lines embedded inside /** */ docblock
// ---------------------------------------------------------------------------

describe('AnnotationParser::parse — #AI lines inside /** */ docblock', function () {

    it('extracts #AI contract inside docblock', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $doc = <<<'DOC'
        /**
         * Returns the cached value for a key or computes and stores it.
         *
         * #AI contract:returns existing value or stores callback result on miss;
         * #AI input:key is the backend lookup key;ttl is seconds for stored miss result;
         * #AI calls:[has,get,set,Profiler::cache];
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result['contract'][0])->toContain('returns existing value or stores callback result')
            ->and($result['calls'][0])->toBe(['has', 'get', 'set', 'Profiler::cache'])
            ->and($result['input'])->not->toBeEmpty();
    });

    it('handles mixed @ai- and #AI in same docblock', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $doc = <<<'DOC'
        /**
         * @ai-contract legacy contract line
         * #AI warning:clears entire backend when prefix is empty;
         */
        DOC;
        $result = $parser->parse($doc);
        expect($result['contract'][0])->toBe('legacy contract line')
            ->and($result['warning'][0])->toContain('clears entire backend');
    });

});

// ---------------------------------------------------------------------------
// AnnotationParser::parseInline — inline // comment style
// ---------------------------------------------------------------------------

describe('AnnotationParser::parseInline', function () {

    it('extracts summary from lines before @ai tags', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $source = <<<'SRC'
        // In-memory array driver — for tests only. No persistence, no Redis required.
        // Resets between requests naturally (process-scoped array).
        // @ai-contract stores values in process memory only
        SRC;
        $result = $parser->parseInline($source);
        expect($result['summary'])->toContain('In-memory array driver')
            ->and($result['contract'][0])->toBe('stores values in process memory only');
    });

    it('returns empty summary when no pre-tag lines', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $source = '// @ai-contract only a tag';
        $result = $parser->parseInline($source);
        expect($result['summary'])->toBe('');
    });

    it('stops collecting lines at first non-comment line', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $source = "// @ai-contract a tag\nclass foo {}";
        $result = $parser->parseInline($source);
        expect($result['contract'][0])->toBe('a tag');
    });

    it('parses #AI style inside inline comments', function () {
        $parser = new \Skim\Dev\Docs\Extractor\AnnotationParser();
        $source = '// #AI role:array driver; layer:cache;';
        $result = $parser->parseInline($source);
        expect($result)->toHaveKey('role');
    });

});
