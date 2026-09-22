<?php declare(strict_types=1);

namespace Skim\View;

use Skim\View\Exceptions\ViewException;

/**
 * State-machine fragment parser for robust HTML comment extraction. #AI:class
 *
 * Use when View::render() is asked for a named fragment. Replaces the old
 * regex approach with tokenization, validation, and deterministic extraction.
 *
 * Tokenizes <!-- @fragment name --> and <!-- @end --> markers, validates
 * against nesting and mismatched markers, then extracts the named block.
 * HTML comments inside fragments do not break parsing.
 *
 * Throws view_exception on nested fragments, unmatched @end, unclosed
 * @fragment, or missing fragment name.
 *
 * Example:
 *   FragmentExtractor::extract($html, 'stats_widget');
 *
 * Testing: Pass raw HTML strings — no file system or template engine needed.
 *
 * #AI:class
 */
class FragmentExtractor {

    /**
     * Extracts a named fragment from rendered HTML. #AI:extract
     *
     * Tokenizes fragment markers, validates structure, and returns the content
     * between the matching @fragment and @end pair. Whitespace is trimmed.
     *
     * @param string $html Rendered HTML containing fragment markers.
     * @param string $name Fragment identifier to extract.
     * @throws \Skim\View\Exceptions\ViewException On structural errors or missing fragment.
     */
    public static function extract(string $html, string $name): string {
        $tokens = self::tokenize($html);
        self::validateNoNesting($tokens);

        return self::extractByName($html, $tokens, $name);
    }

    /**
     * Scans HTML for <!-- @fragment ... --> and <!-- @end --> markers. #AI:tokenize
     *
     * @param string $html Rendered HTML string.
     * @return array List of token arrays with type, name, offset, length.
     */
    private static function tokenize(string $html): array {
        $pattern = '/<!--\s*@(fragment|end)\s*([^>]*)?\s*-->/';
        preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $tokens = [];
        foreach ($matches as $match) {
            $tokens[] = [
                'type'   => $match[1][0],
                'name'   => isset($match[2]) ? trim($match[2][0]) : '',
                'offset' => $match[0][1],
                'length' => strlen($match[0][0]),
            ];
        }

        return $tokens;
    }

    /**
     * Validates flat fragment structure — no nesting, no mismatched markers. #AI:validateNoNesting
     *
     * Depth must never exceed 1 (flat fragments only) and must return to 0.
     *
     * @param array $tokens Token list from tokenize().
     * @throws \Skim\View\Exceptions\ViewException On nested fragments, unmatched @end, or unclosed @fragment.
     */
    private static function validateNoNesting(array $tokens): void {
        $depth = 0;

        foreach ($tokens as $token) {
            if ($token['type'] === 'fragment') {
                $depth++;
                if ($depth > 1) {
                    throw new \Skim\View\Exceptions\ViewException("Nested fragments are not allowed");
                }
            } else { // end
                $depth--;
                if ($depth < 0) {
                    throw new \Skim\View\Exceptions\ViewException("Unmatched @end marker");
                }
            }
        }

        if ($depth !== 0) {
            throw new \Skim\View\Exceptions\ViewException("Unclosed @fragment marker");
        }
    }

    /**
     * Extracts content between the named @fragment and its closing @end. #AI:extractByName
     *
     * @param string $html   Original HTML string.
     * @param array  $tokens Validated token list.
     * @param string $name   Fragment identifier to extract.
     * @throws \Skim\View\Exceptions\ViewException When the named fragment is not found.
     */
    private static function extractByName(string $html, array $tokens, string $name): string {
        foreach ($tokens as $i => $token) {
            if ($token['type'] === 'fragment' && $token['name'] === $name) {
                $start     = $token['offset'] + $token['length'];
                $endToken = $tokens[$i + 1]; // next token must be @end
                $end       = $endToken['offset'];

                return trim(substr($html, $start, $end - $start));
            }
        }

        throw new \Skim\View\Exceptions\ViewException("Fragment '{$name}' not found");
    }
}

#AI:class
#AI symbol: Skim\View\FragmentExtractor
#AI source_path: src/View/FragmentExtractor.php
#AI title: FragmentExtractor
#AI description: State-machine fragment parser replacing regex extraction with tokenized validation.
#AI role: fragment parser
#AI layer: view
#AI badges: [fragment; parser; state-machine; validation]
#AI intro: `FragmentExtractor` tokenizes HTML comment markers, validates structural constraints (flat only, balanced pairs), and extracts named fragment content. More robust than the previous regex approach.
#AI lifecycle: stateless static class, invoked per fragment extraction
#AI fallback: n/a — stateless
#AI test_seam: pass raw HTML strings directly, no file system needed
#AI invariants: [Fragments are flat — nesting is forbidden; @fragment must be paired with @end; HTML comments inside fragments do not break parsing; Extraction is deterministic and order-preserving]
#AI core_behaviors: [Tokenizes <!-- @fragment name --> and <!-- @end --> markers; Validates flat structure and balanced markers; Extracts named block by offset arithmetic; Trims surrounding whitespace]
#AI warnings: [Nested fragments throw ViewException; Unmatched @end throws ViewException; Missing fragment name throws ViewException]
#AI notes: The parser intentionally limits fragments to one level. This prevents complex nesting bugs and keeps the mental model simple.
#AI owns: nothing
#AI entry_points: [extract]
#AI config_reads: []
#AI non_goals: [Does not support nested fragments; Does not validate HTML structure; Does not cache tokenized results]
#AI side_effects: [none — pure functions]
#AI flow: extract() -> tokenize() -> validateNoNesting() -> extractByName() -> return trimmed content
#AI lifecycle_steps: [extract($html, $name); -> tokenize($html); -> validateNoNesting($tokens); -> extractByName($html, $tokens, $name); -> return trim(substr(...))]
#AI section_order: [Extraction API; Tokenization; Validation]
#AI architectural_notes: Replaced the regex-based extractor to avoid backtracking issues and to provide meaningful error messages for malformed fragment markup.

#AI:extract
#AI group: Extraction API
#AI frequency: high
#AI signature: public static function extract(string $html, string $name): string
#AI contract: Tokenizes fragment markers, validates structure, and returns trimmed content for the named fragment.
#AI param_details: [{name: $html | type: string | required: true | desc: Rendered HTML containing fragment markers.}; {name: $name | type: string | required: true | desc: Fragment identifier to extract.}]
#AI return_detail: {type: string | desc: Trimmed fragment content.}
#AI throws_details: [{type: ViewException | desc: On nested fragments, unmatched @end, unclosed @fragment, or missing name.}]

#AI:tokenize
#AI group: Tokenization
#AI frequency: internal
#AI signature: private static function tokenize(string $html): array
#AI contract: Scans HTML for fragment markers and returns a list of token arrays with type, name, offset, and length.
#AI param_details: [{name: $html | type: string | required: true | desc: Rendered HTML string.}]
#AI return_detail: {type: array | desc: List of token arrays.}

#AI:validateNoNesting
#AI group: Validation
#AI frequency: internal
#AI signature: private static function validateNoNesting(array $tokens): void
#AI contract: Validates that fragments are flat (depth <= 1) and all markers are balanced.
#AI param_details: [{name: $tokens | type: array | required: true | desc: Token list from tokenize().}]
#AI throws_details: [{type: ViewException | desc: On nested fragments, unmatched @end, or unclosed @fragment.}]

#AI:extractByName
#AI group: Extraction API
#AI frequency: internal
#AI signature: private static function extractByName(string $html, array $tokens, string $name): string
#AI contract: Locates the named @fragment token and returns the substring between it and the following @end token.
#AI param_details: [{name: $html | type: string | required: true | desc: Original HTML string.}; {name: $tokens | type: array | required: true | desc: Validated token list.}; {name: $name | type: string | required: true | desc: Fragment identifier to extract.}]
#AI return_detail: {type: string | desc: Trimmed fragment content.}
#AI throws_details: [{type: ViewException | desc: When the named fragment is not found.}]
