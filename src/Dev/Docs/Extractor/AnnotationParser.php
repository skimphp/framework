<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Extractor;

/**
 * Parses raw PHPDoc strings and inline comments, extracting all @ai.* and #AI tags into typed arrays. #AI:class
 *
 * Use as the low-level parser for annotation extraction. Handles three input
 * formats: PHPDoc blocks, inline // comments, and detached #AI hash blocks.
 * Unknown tags are silently ignored unless $strict is enabled.
 *
 * Example:
 *   $parser = new AnnotationParser();
 *   $tags = $parser->parse($docblock_string);
 *   $inline = $parser->parseInline($source_lines);
 *   $hash = $parser->parseHashAi('#AI role: cache facade');
 *
 * Testing: Instantiate directly; no framework dependencies.
 *
 * #AI:class
 */
class AnnotationParser {
    public static bool $strict = false;

    public const VOCABULARY = [
        'role', 'layer', 'lifecycle', 'owns', 'entry_points', 'config_reads',
        'invariants', 'side_effects', 'non_goals', 'contract', 'input', 'returns',
        'reads', 'mutates', 'calls', 'throws', 'warning', 'example', 'group', 'frequency',
        'perf', 'symbol', 'source_path', 'title', 'description', 'badges', 'intro',
        'fallback', 'test_seam', 'drivers', 'core_behaviors', 'warnings', 'notes',
        'scope_items', 'flow', 'lifecycle_steps', 'section_order', 'architectural_notes',
        'signature', 'param_details', 'return_detail', 'throws_details', 'required',
        'see_also', 'aliases',
    ];

    /**
     * Parses a PHPDoc block and extracts all @ai.* and #AI tags. #AI:parse
     *
     * Each value is an array of strings (one entry per tag occurrence).
     * Continuation lines starting with whitespace are appended to the previous tag.
     *
     * @param string $docblock Raw docblock string (with or without delimiters).
     * @return array<string, array<string>> Tag values keyed by tag suffix.
     */
    public function parse(string $docblock): array {
        $lines  = $this->stripLines($docblock);
        $result = [];
        $currentKey   = null;
        $currentValue = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '#AI')) {
                if ($currentKey !== null) {
                    $result[$currentKey][] = trim($currentValue);
                    $currentKey = null;
                    $currentValue = '';
                }
                $hashTags = $this->parseHashAi($line);
                foreach ($hashTags as $k => $v) {
                    $result[$k][] = $v;
                }
                continue;
            }

            if (preg_match_all('/@ai[.-](\w+)\s+([^@#]*)/', $line, $matches, PREG_SET_ORDER)) {
                if ($currentKey !== null) {
                    $result[$currentKey][] = trim($currentValue);
                    $currentKey = null;
                    $currentValue = '';
                }
                $lastMatch = array_pop($matches);
                foreach ($matches as $match) {
                    $result[$match[1]][] = trim($match[2]);
                }
                $currentKey = $lastMatch[1];
                $currentValue = trim($lastMatch[2]);
            } elseif (str_starts_with($line, '@')) {
                if ($currentKey !== null) {
                    $result[$currentKey][] = trim($currentValue);
                    $currentKey   = null;
                    $currentValue = '';
                }
            } elseif ($currentKey !== null && $line !== '') {
                $currentValue .= ' ' . $line;
            } else {
                if ($currentKey !== null) {
                    $result[$currentKey][] = trim($currentValue);
                    $currentKey   = null;
                    $currentValue = '';
                }
            }
        }

        if ($currentKey !== null) {
            $result[$currentKey][] = trim($currentValue);
        }

        return $result;
    }

    /**
     * Parses inline // comments and extracts @ai.* tags and preceding summary text. #AI:parseInline
     *
     * Lines before the first @ai.* or #AI tag are captured as 'summary'.
     *
     * @param string $source Raw source lines starting with //.
     * @return array<string, mixed> Tags plus 'summary' key.
     */
    public function parseInline(string $source): array {
        $lines = explode("\n", $source);
        $summaryLines = [];
        $tagLines = [];
        $inTags = false;

        foreach ($lines as $line) {
            if (!preg_match('/^\s*\/\/(.*)$/', $line, $matches)) {
                break;
            }
            $content = $matches[1];
            $trimmed = trim($content);

            if (str_starts_with($trimmed, '@ai.') || str_starts_with($trimmed, '@ai-') || str_starts_with($trimmed, '#AI')) {
                $inTags = true;
            }

            if ($inTags) {
                if ($trimmed !== '') {
                    $tagLines[] = $trimmed;
                }
            } else {
                $summaryLines[] = $trimmed;
            }
        }

        $result = [];
        $currentKey   = null;
        $currentValue = '';

        foreach ($tagLines as $line) {
            if (str_starts_with($line, '#AI')) {
                if ($currentKey !== null) {
                    $result[$currentKey][] = trim($currentValue);
                    $currentKey = null;
                    $currentValue = '';
                }
                $hashTags = $this->parseHashAi($line);
                foreach ($hashTags as $k => $v) {
                    $result[$k][] = $v;
                }
                continue;
            }

            if (preg_match_all('/@ai[.-](\w+)\s+([^@#]*)/', $line, $matches, PREG_SET_ORDER)) {
                if ($currentKey !== null) {
                    $result[$currentKey][] = trim($currentValue);
                    $currentKey = null;
                    $currentValue = '';
                }
                $lastMatch = array_pop($matches);
                foreach ($matches as $match) {
                    $result[$match[1]][] = trim($match[2]);
                }
                $currentKey = $lastMatch[1];
                $currentValue = trim($lastMatch[2]);
            } elseif (str_starts_with($line, '@')) {
                if ($currentKey !== null) {
                    $result[$currentKey][] = trim($currentValue);
                    $currentKey   = null;
                    $currentValue = '';
                }
            } elseif ($currentKey !== null && $line !== '') {
                $currentValue .= ' ' . $line;
            } else {
                if ($currentKey !== null) {
                    $result[$currentKey][] = trim($currentValue);
                    $currentKey   = null;
                    $currentValue = '';
                }
            }
        }

        if ($currentKey !== null) {
            $result[$currentKey][] = trim($currentValue);
        }

        while (count($summaryLines) > 0 && end($summaryLines) === '') {
            array_pop($summaryLines);
        }

        $summary = implode("\n", $summaryLines);
        $result['summary'] = trim($summary);

        return $result;
    }

    /**
     * Parses a #AI hash annotation block into key-value pairs. #AI:parseHashAi
     *
     * Handles `#AI:{target}` section headers and `#AI key: value` pairs.
     * Semicolons split top-level items; brackets and braces are preserved.
     *
     * @param string $source One or more lines starting with #AI.
     * @return array<string, mixed> Parsed key-value pairs.
     *
     * @throws \UnexpectedValueException When $strict is true and an unknown key is encountered.
     */
    public function parseHashAi(string $source): array {
        $result = [];
        $lines = explode("\n", $source);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!str_starts_with($line, '#AI')) {
                continue;
            }
            $content = trim(substr($line, 3));
            if ($content === '') {
                continue;
            }
            if (str_starts_with($content, ':')) {
                $result['__target'] = trim(substr($content, 1));
                continue;
            }

            foreach ($this->splitTopLevel($content, ';') as $part) {
                if (!str_contains($part, ':')) {
                    continue;
                }
                [$key, $rawValue] = explode(':', $part, 2);
                $key = trim($key);
                $val = $this->coerceValue($rawValue);
                if (static::$strict && !in_array($key, self::VOCABULARY, true)) {
                    throw new \UnexpectedValueException("Unknown #AI key: {$key}");
                }
                $result[$key] = $val;
            }
        }
        return $result;
    }

    /**
     * Parses a bracket-delimited list into a trimmed array of coerced values. #AI:parseBracketList
     *
     * Supports both comma and semicolon delimiters (semicolon preferred).
     *
     * @param string $value Bracket-delimited string like `[a; b; c]`.
     */
    public function parseBracketList(string $value): array {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '[]') {
            return [];
        }
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            $trimmed = substr($trimmed, 1, -1);
        }
        if (trim($trimmed) === '') {
            return [];
        }
        $delimiter = str_contains($trimmed, ';') ? ';' : ',';
        $items = [];
        foreach ($this->splitTopLevel($trimmed, $delimiter) as $item) {
            $items[] = $this->coerceValue($item);
        }
        return $items;
    }

    /**
     * Parses a pipe-delimited record into a typed associative array. #AI:parseRecord
     *
     * Input format: `{key: value | key: value}`.
     *
     * @param string $value Record string with optional braces.
     */
    public function parseRecord(string $value): array {
        $trimmed = trim($value);
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $trimmed = substr($trimmed, 1, -1);
        }

        $record = [];
        foreach ($this->splitTopLevel($trimmed, '|') as $part) {
            if (!str_contains($part, ':')) {
                continue;
            }
            [$key, $rawValue] = explode(':', $part, 2);
            $record[trim($key)] = $this->coerceValue($rawValue);
        }

        return $record;
    }

    /**
     * Coerces a string annotation value into its PHP equivalent. #AI:coerceValue
     *
     * Bracket lists become arrays, brace records become arrays, and
     * true/false/yes/no/1/0 become booleans.
     *
     * @param string $value Raw string value from an annotation.
     */
    public function coerceValue(string $value): mixed {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            return $this->parseBracketList($trimmed);
        }
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            return $this->parseRecord($trimmed);
        }

        return match (strtolower($trimmed)) {
            'true', 'yes', '1' => true,
            'false', 'no', '0' => false,
            default => $trimmed,
        };
    }

    /**
     * Splits a string at top-level delimiters only, preserving nested brackets and braces. #AI:splitTopLevel
     *
     * @param string $value     String to split.
     * @param string $delimiter Single-character delimiter.
     */
    public function splitTopLevel(string $value, string $delimiter): array {
        $items = [];
        $buffer = '';
        $squareDepth = 0;
        $braceDepth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '[') {
                $squareDepth++;
            } elseif ($char === ']') {
                $squareDepth = max(0, $squareDepth - 1);
            } elseif ($char === '{') {
                $braceDepth++;
            } elseif ($char === '}') {
                $braceDepth = max(0, $braceDepth - 1);
            }

            if ($char === $delimiter && $squareDepth === 0 && $braceDepth === 0) {
                $item = trim($buffer);
                if ($item !== '') {
                    $items[] = $item;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $item = trim($buffer);
        if ($item !== '') {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Extracts the first non-tag, non-empty lines as the summary sentence. #AI:extractSummary
     *
     * @param string $docblock Raw docblock string.
     * @return string Summary text, or empty string if none found.
     */
    public function extractSummary(string $docblock): string {
        $summaryLines = [];
        foreach ($this->stripLines($docblock) as $line) {
            if (str_starts_with($line, '@') || str_starts_with($line, '#AI')) {
                break;
            }
            $summaryLines[] = $line;
        }
        while (count($summaryLines) > 0 && trim(reset($summaryLines)) === '') {
            array_shift($summaryLines);
        }
        while (count($summaryLines) > 0 && trim(end($summaryLines)) === '') {
            array_pop($summaryLines);
        }
        return implode("\n", $summaryLines);
    }

    /**
     * Extracts Example: blocks from a raw docblock. #AI:extract_examples
     *
     * @param string $docblock Raw docblock string.
     * @return array<array{label: string, code: string}> Array of parsed examples.
     */
    public function extractExamples(string $docblock): array {
        $lines = explode("\n", $docblock);
        $examples = [];
        $currentExample = null;
        $indentToStrip = null;

        foreach ($lines as $line) {
            $cleanLine = $line;
            // Remove leading /** or */
            $cleanLine = preg_replace('#^\s*/\*\*|^\s*\*/#', '', $cleanLine);
            // Remove leading asterisk and up to one space if present
            $cleanLine = preg_replace('#^\s*\*\s?#', '', $cleanLine);

            // Check for an Example header: "Example:" or "Example: Some label"
            if (preg_match('/^\s*Example:\s*(.*)$/i', $cleanLine, $matches)) {
                if ($currentExample !== null) {
                    $examples[] = $currentExample;
                }
                $label = trim($matches[1]);
                $currentExample = [
                    'label' => $label !== '' ? $label : 'Basic usage',
                    'lines' => []
                ];
                $indentToStrip = null;
                continue;
            }

            if ($currentExample !== null) {
                $trimmed = trim($cleanLine);
                // Check if we hit a docblock tag or #AI
                if (str_starts_with($trimmed, '@') || str_starts_with($trimmed, '#AI') || str_starts_with($trimmed, '#ai')) {
                    $examples[] = $currentExample;
                    $currentExample = null;
                    continue;
                }

                if ($trimmed !== '') {
                    // If it starts with non-space, it marks the end of the example block
                    if (preg_match('/^\S/', $cleanLine)) {
                        $examples[] = $currentExample;
                        $currentExample = null;
                        continue;
                    }

                    // Determine indentation to strip based on the first non-empty line
                    if ($indentToStrip === null) {
                        preg_match('/^(\s*)/', $cleanLine, $spaces);
                        $indentToStrip = strlen($spaces[1] ?? '');
                    }

                    // Strip the base indentation
                    if ($indentToStrip > 0) {
                        if (str_starts_with($cleanLine, str_repeat(' ', $indentToStrip))) {
                            $cleanLine = substr($cleanLine, $indentToStrip);
                        } else {
                            $cleanLine = ltrim($cleanLine);
                        }
                    }
                    $currentExample['lines'][] = $cleanLine;
                } else {
                    $currentExample['lines'][] = '';
                }
            }
        }

        if ($currentExample !== null) {
            $examples[] = $currentExample;
        }

        $processed = [];
        foreach ($examples as $ex) {
            $codeLines = $ex['lines'];
            while (count($codeLines) > 0 && trim(end($codeLines)) === '') {
                array_pop($codeLines);
            }
            while (count($codeLines) > 0 && trim(reset($codeLines)) === '') {
                array_shift($codeLines);
            }
            if (count($codeLines) > 0) {
                $processed[] = [
                    'label' => $ex['label'],
                    'code' => implode("\n", $codeLines)
                ];
            }
        }

        return $processed;
    }

    /**
     * Strips docblock delimiters and leading * characters from each line. #AI:stripLines
     *
     * @param string $docblock Raw docblock string.
     * @return string[] Trimmed content lines.
     */
    private function stripLines(string $docblock): array {
        $raw   = preg_replace('#^/\*\*|\*/$#m', '', $docblock) ?? $docblock;
        $lines = explode("\n", $raw);
        $out   = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '* ')) {
                $trimmed = substr($trimmed, 2);
            } elseif ($trimmed === '*') {
                $trimmed = '';
            }
            $out[] = trim($trimmed);
        }
        return $out;
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Extractor\AnnotationParser
#AI source_path: src/Dev/Docs/Extractor/AnnotationParser.php
#AI title: AnnotationParser
#AI description: Parses PHPDoc blocks, inline comments, and #AI hash blocks to extract @ai.* tags into typed arrays.
#AI role: annotation parser
#AI layer: dev
#AI badges: [extractor; parser; annotations; no-framework-deps]
#AI intro: `AnnotationParser` is the low-level parsing engine for the docs extraction pipeline. It handles three input formats (PHPDoc, inline //, and #AI hash blocks) and coerces values into PHP types (arrays, records, booleans).
#AI lifecycle: instantiated per-use by ClassVisitor, no state retained between calls
#AI fallback: unknown tags silently ignored unless $strict is true
#AI test_seam: instantiate directly; set $strict = true for validation testing
#AI invariants: [unknown tags silently ignored by default; $strict mode throws on unknown keys; continuation lines appended to previous tag; semicolons split top-level items only]
#AI core_behaviors: [Parses PHPDoc @ai.* tags with continuation line support; Parses inline // comments with summary extraction; Parses #AI hash blocks with nested bracket/brace awareness; Coerces values to PHP types]
#AI scope_items: [{name: $strict | mutable: true | desc: When true, throws UnexpectedValueException on unknown #AI keys. Default false.}]
#AI owns: VOCABULARY constant
#AI entry_points: [parse; parseInline; parseHashAi; parseBracketList; parseRecord; coerceValue; splitTopLevel; extractSummary]
#AI config_reads: []
#AI non_goals: [Does not read files; Does not traverse AST; Does not validate tag semantics]
#AI side_effects: []
#AI flow: parse/parseInline/parseHashAi -> splitTopLevel -> coerceValue -> typed result
#AI lifecycle_steps: [parse(); -> stripLines(); -> iterate lines; -> match @ai.* or #AI; -> splitTopLevel; -> coerceValue; parseInline(); -> split summary vs tags; -> parse tags; parseHashAi(); -> detect __target or key:value; -> splitTopLevel; -> coerceValue]
#AI section_order: [Parsing; Value Coercion; Utilities; Architecture]
#AI architectural_notes: No framework dependencies — plain PHP only. The VOCABULARY constant defines the known key set for strict mode validation.

#AI:parse
#AI group: Parsing
#AI frequency: high
#AI signature: public function parse(string $docblock): array
#AI contract: Accepts a raw docblock string and extracts all @ai.* and #AI tags into an associative array keyed by tag suffix. Each value is an array of strings. Continuation lines starting with whitespace are appended to the previous tag.
#AI param_details: [{name: $docblock | type: string | required: true | desc: Raw docblock string, with or without /** delimiters.}]
#AI return_detail: {type: array<string, array<string>> | desc: Tag values keyed by tag suffix (e.g. 'contract', 'invariant').}

#AI:parseInline
#AI group: Parsing
#AI frequency: medium
#AI signature: public function parseInline(string $source): array
#AI contract: Parses inline // comments, extracting @ai.* tags and capturing preceding non-tag lines as a 'summary' key. Stops at the first non-// line.
#AI param_details: [{name: $source | type: string | required: true | desc: Raw source lines starting with //.}]
#AI return_detail: {type: array<string, mixed> | desc: Tags plus 'summary' key containing preceding comment text.}

#AI:parseHashAi
#AI group: Parsing
#AI frequency: high
#AI signature: public function parseHashAi(string $source): array
#AI contract: Parses #AI hash annotation blocks. Detects `#AI:{target}` section headers (stored as __target) and `#AI key: value` pairs split by semicolons. Nested brackets and braces are preserved during splitting.
#AI param_details: [{name: $source | type: string | required: true | desc: One or more lines starting with #AI.}]
#AI return_detail: {type: array<string, mixed> | desc: Parsed key-value pairs, with __target for section headers.}
#AI throws_details: [{type: \UnexpectedValueException | desc: When $strict is true and an unknown key is encountered.}]

#AI:parseBracketList
#AI group: Value Coercion
#AI frequency: medium
#AI signature: public function parseBracketList(string $value): array
#AI contract: Parses a bracket-delimited list string into an array of coerced values. Supports both comma and semicolon delimiters.
#AI param_details: [{name: $value | type: string | required: true | desc: Bracket-delimited string like `[a; b; c]`.}]
#AI return_detail: {type: array | desc: Array of coerced values.}

#AI:parseRecord
#AI group: Value Coercion
#AI frequency: medium
#AI signature: public function parseRecord(string $value): array
#AI contract: Parses a pipe-delimited record string `{key: value | key: value}` into an associative array with coerced values.
#AI param_details: [{name: $value | type: string | required: true | desc: Record string with optional braces.}]
#AI return_detail: {type: array | desc: Associative array of coerced values.}

#AI:coerceValue
#AI group: Value Coercion
#AI frequency: high
#AI signature: public function coerceValue(string $value): mixed
#AI contract: Coerces a string annotation value into its PHP equivalent. Bracket lists become arrays, brace records become associative arrays, and true/false/yes/no/1/0 become booleans.
#AI param_details: [{name: $value | type: string | required: true | desc: Raw string value from an annotation.}]
#AI return_detail: {type: mixed | desc: Coerced PHP value (string, bool, array).}

#AI:splitTopLevel
#AI group: Utilities
#AI frequency: high
#AI signature: public function splitTopLevel(string $value, string $delimiter): array
#AI contract: Splits a string at top-level delimiters only, preserving content inside square brackets and curly braces. Tracks nesting depth to avoid splitting nested structures.
#AI param_details: [{name: $value | type: string | required: true | desc: String to split.}; {name: $delimiter | type: string | required: true | desc: Single-character delimiter.}]
#AI return_detail: {type: array<string> | desc: Trimmed parts split at top-level delimiters only.}

#AI:extractSummary
#AI group: Utilities
#AI frequency: medium
#AI signature: public function extractSummary(string $docblock): string
#AI contract: Extracts the first non-tag, non-empty lines from a docblock as the summary sentence. Stops at the first @tag or #AI line.
#AI param_details: [{name: $docblock | type: string | required: true | desc: Raw docblock string.}]
#AI return_detail: {type: string | desc: Summary text, or empty string if none found.}

#AI:stripLines
#AI group: Architecture
#AI frequency: internal
#AI signature: private function stripLines(string $docblock): array
#AI contract: Strips PHPDoc delimiters (/** and */) and leading * characters from each line, returning trimmed content lines.
