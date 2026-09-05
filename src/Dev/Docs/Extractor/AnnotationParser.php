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
 *   $parser = new annotation_parser();
 *   $tags = $parser->parse($docblock_string);
 *   $inline = $parser->parse_inline($source_lines);
 *   $hash = $parser->parse_hash_ai('#AI role: cache facade');
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
        $lines  = $this->strip_lines($docblock);
        $result = [];
        $current_key   = null;
        $current_value = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, '#AI')) {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                    $current_key = null;
                    $current_value = '';
                }
                $hash_tags = $this->parse_hash_ai($line);
                foreach ($hash_tags as $k => $v) {
                    $result[$k][] = $v;
                }
                continue;
            }

            if (preg_match_all('/@ai[.-](\w+)\s+([^@#]*)/', $line, $matches, PREG_SET_ORDER)) {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                    $current_key = null;
                    $current_value = '';
                }
                $last_match = array_pop($matches);
                foreach ($matches as $match) {
                    $result[$match[1]][] = trim($match[2]);
                }
                $current_key = $last_match[1];
                $current_value = trim($last_match[2]);
            } elseif (str_starts_with($line, '@')) {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                    $current_key   = null;
                    $current_value = '';
                }
            } elseif ($current_key !== null && $line !== '') {
                $current_value .= ' ' . $line;
            } else {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                    $current_key   = null;
                    $current_value = '';
                }
            }
        }

        if ($current_key !== null) {
            $result[$current_key][] = trim($current_value);
        }

        return $result;
    }

    /**
     * Parses inline // comments and extracts @ai.* tags and preceding summary text. #AI:parse_inline
     *
     * Lines before the first @ai.* or #AI tag are captured as 'summary'.
     *
     * @param string $source Raw source lines starting with //.
     * @return array<string, mixed> Tags plus 'summary' key.
     */
    public function parse_inline(string $source): array {
        $lines = explode("\n", $source);
        $summary_lines = [];
        $tag_lines = [];
        $in_tags = false;

        foreach ($lines as $line) {
            if (!preg_match('/^\s*\/\/(.*)$/', $line, $matches)) {
                break;
            }
            $content = $matches[1];
            $trimmed = trim($content);

            if (str_starts_with($trimmed, '@ai.') || str_starts_with($trimmed, '@ai-') || str_starts_with($trimmed, '#AI')) {
                $in_tags = true;
            }

            if ($in_tags) {
                if ($trimmed !== '') {
                    $tag_lines[] = $trimmed;
                }
            } else {
                $summary_lines[] = $trimmed;
            }
        }

        $result = [];
        $current_key   = null;
        $current_value = '';

        foreach ($tag_lines as $line) {
            if (str_starts_with($line, '#AI')) {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                    $current_key = null;
                    $current_value = '';
                }
                $hash_tags = $this->parse_hash_ai($line);
                foreach ($hash_tags as $k => $v) {
                    $result[$k][] = $v;
                }
                continue;
            }

            if (preg_match_all('/@ai[.-](\w+)\s+([^@#]*)/', $line, $matches, PREG_SET_ORDER)) {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                    $current_key = null;
                    $current_value = '';
                }
                $last_match = array_pop($matches);
                foreach ($matches as $match) {
                    $result[$match[1]][] = trim($match[2]);
                }
                $current_key = $last_match[1];
                $current_value = trim($last_match[2]);
            } elseif (str_starts_with($line, '@')) {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                    $current_key   = null;
                    $current_value = '';
                }
            } elseif ($current_key !== null && $line !== '') {
                $current_value .= ' ' . $line;
            } else {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                    $current_key   = null;
                    $current_value = '';
                }
            }
        }

        if ($current_key !== null) {
            $result[$current_key][] = trim($current_value);
        }

        while (count($summary_lines) > 0 && end($summary_lines) === '') {
            array_pop($summary_lines);
        }

        $summary = implode("\n", $summary_lines);
        $result['summary'] = trim($summary);

        return $result;
    }

    /**
     * Parses a #AI hash annotation block into key-value pairs. #AI:parse_hash_ai
     *
     * Handles `#AI:{target}` section headers and `#AI key: value` pairs.
     * Semicolons split top-level items; brackets and braces are preserved.
     *
     * @param string $source One or more lines starting with #AI.
     * @return array<string, mixed> Parsed key-value pairs.
     *
     * @throws \UnexpectedValueException When $strict is true and an unknown key is encountered.
     */
    public function parse_hash_ai(string $source): array {
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

            foreach ($this->split_top_level($content, ';') as $part) {
                if (!str_contains($part, ':')) {
                    continue;
                }
                [$key, $raw_value] = explode(':', $part, 2);
                $key = trim($key);
                $val = $this->coerce_value($raw_value);
                if (static::$strict && !in_array($key, self::VOCABULARY, true)) {
                    throw new \UnexpectedValueException("Unknown #AI key: {$key}");
                }
                $result[$key] = $val;
            }
        }
        return $result;
    }

    /**
     * Parses a bracket-delimited list into a trimmed array of coerced values. #AI:parse_bracket_list
     *
     * Supports both comma and semicolon delimiters (semicolon preferred).
     *
     * @param string $value Bracket-delimited string like `[a; b; c]`.
     */
    public function parse_bracket_list(string $value): array {
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
        foreach ($this->split_top_level($trimmed, $delimiter) as $item) {
            $items[] = $this->coerce_value($item);
        }
        return $items;
    }

    /**
     * Parses a pipe-delimited record into a typed associative array. #AI:parse_record
     *
     * Input format: `{key: value | key: value}`.
     *
     * @param string $value Record string with optional braces.
     */
    public function parse_record(string $value): array {
        $trimmed = trim($value);
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            $trimmed = substr($trimmed, 1, -1);
        }

        $record = [];
        foreach ($this->split_top_level($trimmed, '|') as $part) {
            if (!str_contains($part, ':')) {
                continue;
            }
            [$key, $raw_value] = explode(':', $part, 2);
            $record[trim($key)] = $this->coerce_value($raw_value);
        }

        return $record;
    }

    /**
     * Coerces a string annotation value into its PHP equivalent. #AI:coerce_value
     *
     * Bracket lists become arrays, brace records become arrays, and
     * true/false/yes/no/1/0 become booleans.
     *
     * @param string $value Raw string value from an annotation.
     */
    public function coerce_value(string $value): mixed {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            return $this->parse_bracket_list($trimmed);
        }
        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            return $this->parse_record($trimmed);
        }

        return match (strtolower($trimmed)) {
            'true', 'yes', '1' => true,
            'false', 'no', '0' => false,
            default => $trimmed,
        };
    }

    /**
     * Splits a string at top-level delimiters only, preserving nested brackets and braces. #AI:split_top_level
     *
     * @param string $value     String to split.
     * @param string $delimiter Single-character delimiter.
     */
    public function split_top_level(string $value, string $delimiter): array {
        $items = [];
        $buffer = '';
        $square_depth = 0;
        $brace_depth = 0;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '[') {
                $square_depth++;
            } elseif ($char === ']') {
                $square_depth = max(0, $square_depth - 1);
            } elseif ($char === '{') {
                $brace_depth++;
            } elseif ($char === '}') {
                $brace_depth = max(0, $brace_depth - 1);
            }

            if ($char === $delimiter && $square_depth === 0 && $brace_depth === 0) {
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
     * Extracts the first non-tag, non-empty lines as the summary sentence. #AI:extract_summary
     *
     * @param string $docblock Raw docblock string.
     * @return string Summary text, or empty string if none found.
     */
    public function extract_summary(string $docblock): string {
        $summary_lines = [];
        foreach ($this->strip_lines($docblock) as $line) {
            if (str_starts_with($line, '@') || str_starts_with($line, '#AI')) {
                break;
            }
            $summary_lines[] = $line;
        }
        while (count($summary_lines) > 0 && trim(reset($summary_lines)) === '') {
            array_shift($summary_lines);
        }
        while (count($summary_lines) > 0 && trim(end($summary_lines)) === '') {
            array_pop($summary_lines);
        }
        return implode("\n", $summary_lines);
    }

    /**
     * Extracts Example: blocks from a raw docblock. #AI:extract_examples
     *
     * @param string $docblock Raw docblock string.
     * @return array<array{label: string, code: string}> Array of parsed examples.
     */
    public function extract_examples(string $docblock): array {
        $lines = explode("\n", $docblock);
        $examples = [];
        $current_example = null;
        $indent_to_strip = null;

        foreach ($lines as $line) {
            $clean_line = $line;
            // Remove leading /** or */
            $clean_line = preg_replace('#^\s*/\*\*|^\s*\*/#', '', $clean_line);
            // Remove leading asterisk and up to one space if present
            $clean_line = preg_replace('#^\s*\*\s?#', '', $clean_line);

            // Check for an Example header: "Example:" or "Example: Some label"
            if (preg_match('/^\s*Example:\s*(.*)$/i', $clean_line, $matches)) {
                if ($current_example !== null) {
                    $examples[] = $current_example;
                }
                $label = trim($matches[1]);
                $current_example = [
                    'label' => $label !== '' ? $label : 'Basic usage',
                    'lines' => []
                ];
                $indent_to_strip = null;
                continue;
            }

            if ($current_example !== null) {
                $trimmed = trim($clean_line);
                // Check if we hit a docblock tag or #AI
                if (str_starts_with($trimmed, '@') || str_starts_with($trimmed, '#AI') || str_starts_with($trimmed, '#ai')) {
                    $examples[] = $current_example;
                    $current_example = null;
                    continue;
                }

                if ($trimmed !== '') {
                    // If it starts with non-space, it marks the end of the example block
                    if (preg_match('/^\S/', $clean_line)) {
                        $examples[] = $current_example;
                        $current_example = null;
                        continue;
                    }

                    // Determine indentation to strip based on the first non-empty line
                    if ($indent_to_strip === null) {
                        preg_match('/^(\s*)/', $clean_line, $spaces);
                        $indent_to_strip = strlen($spaces[1] ?? '');
                    }

                    // Strip the base indentation
                    if ($indent_to_strip > 0) {
                        if (str_starts_with($clean_line, str_repeat(' ', $indent_to_strip))) {
                            $clean_line = substr($clean_line, $indent_to_strip);
                        } else {
                            $clean_line = ltrim($clean_line);
                        }
                    }
                    $current_example['lines'][] = $clean_line;
                } else {
                    $current_example['lines'][] = '';
                }
            }
        }

        if ($current_example !== null) {
            $examples[] = $current_example;
        }

        $processed = [];
        foreach ($examples as $ex) {
            $code_lines = $ex['lines'];
            while (count($code_lines) > 0 && trim(end($code_lines)) === '') {
                array_pop($code_lines);
            }
            while (count($code_lines) > 0 && trim(reset($code_lines)) === '') {
                array_shift($code_lines);
            }
            if (count($code_lines) > 0) {
                $processed[] = [
                    'label' => $ex['label'],
                    'code' => implode("\n", $code_lines)
                ];
            }
        }

        return $processed;
    }

    /**
     * Strips docblock delimiters and leading * characters from each line. #AI:strip_lines
     *
     * @param string $docblock Raw docblock string.
     * @return string[] Trimmed content lines.
     */
    private function strip_lines(string $docblock): array {
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
#AI source_path: src/dev/docs/extractor/annotation_parser.php
#AI title: annotation_parser
#AI description: Parses PHPDoc blocks, inline comments, and #AI hash blocks to extract @ai.* tags into typed arrays.
#AI role: annotation parser
#AI layer: dev
#AI badges: [extractor; parser; annotations; no-framework-deps]
#AI intro: `annotation_parser` is the low-level parsing engine for the docs extraction pipeline. It handles three input formats (PHPDoc, inline //, and #AI hash blocks) and coerces values into PHP types (arrays, records, booleans).
#AI lifecycle: instantiated per-use by class_visitor, no state retained between calls
#AI fallback: unknown tags silently ignored unless $strict is true
#AI test_seam: instantiate directly; set $strict = true for validation testing
#AI invariants: [unknown tags silently ignored by default; $strict mode throws on unknown keys; continuation lines appended to previous tag; semicolons split top-level items only]
#AI core_behaviors: [Parses PHPDoc @ai.* tags with continuation line support; Parses inline // comments with summary extraction; Parses #AI hash blocks with nested bracket/brace awareness; Coerces values to PHP types]
#AI scope_items: [{name: $strict | mutable: true | desc: When true, throws UnexpectedValueException on unknown #AI keys. Default false.}]
#AI owns: VOCABULARY constant
#AI entry_points: [parse; parse_inline; parse_hash_ai; parse_bracket_list; parse_record; coerce_value; split_top_level; extract_summary]
#AI config_reads: []
#AI non_goals: [Does not read files; Does not traverse AST; Does not validate tag semantics]
#AI side_effects: []
#AI flow: parse/parse_inline/parse_hash_ai -> split_top_level -> coerce_value -> typed result
#AI lifecycle_steps: [parse(); -> strip_lines(); -> iterate lines; -> match @ai.* or #AI; -> split_top_level; -> coerce_value; parse_inline(); -> split summary vs tags; -> parse tags; parse_hash_ai(); -> detect __target or key:value; -> split_top_level; -> coerce_value]
#AI section_order: [Parsing; Value Coercion; Utilities; Architecture]
#AI architectural_notes: No framework dependencies — plain PHP only. The VOCABULARY constant defines the known key set for strict mode validation.

#AI:parse
#AI group: Parsing
#AI frequency: high
#AI signature: public function parse(string $docblock): array
#AI contract: Accepts a raw docblock string and extracts all @ai.* and #AI tags into an associative array keyed by tag suffix. Each value is an array of strings. Continuation lines starting with whitespace are appended to the previous tag.
#AI param_details: [{name: $docblock | type: string | required: true | desc: Raw docblock string, with or without /** delimiters.}]
#AI return_detail: {type: array<string, array<string>> | desc: Tag values keyed by tag suffix (e.g. 'contract', 'invariant').}

#AI:parse_inline
#AI group: Parsing
#AI frequency: medium
#AI signature: public function parse_inline(string $source): array
#AI contract: Parses inline // comments, extracting @ai.* tags and capturing preceding non-tag lines as a 'summary' key. Stops at the first non-// line.
#AI param_details: [{name: $source | type: string | required: true | desc: Raw source lines starting with //.}]
#AI return_detail: {type: array<string, mixed> | desc: Tags plus 'summary' key containing preceding comment text.}

#AI:parse_hash_ai
#AI group: Parsing
#AI frequency: high
#AI signature: public function parse_hash_ai(string $source): array
#AI contract: Parses #AI hash annotation blocks. Detects `#AI:{target}` section headers (stored as __target) and `#AI key: value` pairs split by semicolons. Nested brackets and braces are preserved during splitting.
#AI param_details: [{name: $source | type: string | required: true | desc: One or more lines starting with #AI.}]
#AI return_detail: {type: array<string, mixed> | desc: Parsed key-value pairs, with __target for section headers.}
#AI throws_details: [{type: \UnexpectedValueException | desc: When $strict is true and an unknown key is encountered.}]

#AI:parse_bracket_list
#AI group: Value Coercion
#AI frequency: medium
#AI signature: public function parse_bracket_list(string $value): array
#AI contract: Parses a bracket-delimited list string into an array of coerced values. Supports both comma and semicolon delimiters.
#AI param_details: [{name: $value | type: string | required: true | desc: Bracket-delimited string like `[a; b; c]`.}]
#AI return_detail: {type: array | desc: Array of coerced values.}

#AI:parse_record
#AI group: Value Coercion
#AI frequency: medium
#AI signature: public function parse_record(string $value): array
#AI contract: Parses a pipe-delimited record string `{key: value | key: value}` into an associative array with coerced values.
#AI param_details: [{name: $value | type: string | required: true | desc: Record string with optional braces.}]
#AI return_detail: {type: array | desc: Associative array of coerced values.}

#AI:coerce_value
#AI group: Value Coercion
#AI frequency: high
#AI signature: public function coerce_value(string $value): mixed
#AI contract: Coerces a string annotation value into its PHP equivalent. Bracket lists become arrays, brace records become associative arrays, and true/false/yes/no/1/0 become booleans.
#AI param_details: [{name: $value | type: string | required: true | desc: Raw string value from an annotation.}]
#AI return_detail: {type: mixed | desc: Coerced PHP value (string, bool, array).}

#AI:split_top_level
#AI group: Utilities
#AI frequency: high
#AI signature: public function split_top_level(string $value, string $delimiter): array
#AI contract: Splits a string at top-level delimiters only, preserving content inside square brackets and curly braces. Tracks nesting depth to avoid splitting nested structures.
#AI param_details: [{name: $value | type: string | required: true | desc: String to split.}; {name: $delimiter | type: string | required: true | desc: Single-character delimiter.}]
#AI return_detail: {type: array<string> | desc: Trimmed parts split at top-level delimiters only.}

#AI:extract_summary
#AI group: Utilities
#AI frequency: medium
#AI signature: public function extract_summary(string $docblock): string
#AI contract: Extracts the first non-tag, non-empty lines from a docblock as the summary sentence. Stops at the first @tag or #AI line.
#AI param_details: [{name: $docblock | type: string | required: true | desc: Raw docblock string.}]
#AI return_detail: {type: string | desc: Summary text, or empty string if none found.}

#AI:strip_lines
#AI group: Architecture
#AI frequency: internal
#AI signature: private function strip_lines(string $docblock): array
#AI contract: Strips PHPDoc delimiters (/** and */) and leading * characters from each line, returning trimmed content lines.
