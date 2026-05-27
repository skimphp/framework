<?php declare(strict_types=1);

namespace skim\dev\docs\extractor;

// Parses raw PHPDoc string and extracts all @ai.* tags into typed arrays.
// Unknown tags are silently ignored — never thrown.
// No framework dependencies — plain PHP only.
class annotation_parser {
    public static bool $strict = false;

    public const VOCABULARY = [
        'role', 'layer', 'lifecycle', 'owns', 'entry_points', 'config_reads',
        'invariants', 'side_effects', 'non_goals', 'contract', 'input', 'returns',
        'reads', 'mutates', 'calls', 'throws', 'warning', 'example', 'group', 'frequency',
        'perf', 'symbol', 'source_path', 'title', 'description', 'badges', 'intro',
        'fallback', 'test_seam', 'drivers', 'core_behaviors', 'warnings', 'notes',
        'scope_items', 'flow', 'lifecycle_steps', 'section_order', 'architectural_notes',
        'signature', 'param_details', 'return_detail', 'throws_details', 'required',
    ];

    /**
     * @ai-contract accepts raw docblock string (including /** delimiters or stripped)
     * @ai-contract returns associative array keyed by tag suffix: 'contract', 'invariant', etc.
     * @ai-contract each value is an array of strings (one entry per tag occurrence)
     * @ai-contract unknown tags and non-@ai.* tags are silently ignored
     * @ai-contract tag values are trimmed; continuation lines starting with whitespace are appended
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

            // Check if there are any @ai. or @ai- tags on this line
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
     * @ai-contract parses inline comments (starting with //) and extracts @ai.* tags
     * @ai-contract extracts any human comment lines preceding @ai.* tags as 'summary'
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

            // Check if there are any @ai. or @ai- tags on this line
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
     * @ai-contract parses a single or multi-line #AI semicolon-delimited annotation block
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
     * @ai-contract splits [a,b,c] into a trimmed array of strings
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
     * @ai-contract parses {key: value | key: value} into a typed associative array
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
     * @ai-contract coerces scalar/list/record annotation values into PHP values
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
     * @ai-contract splits only at top-level delimiters, preserving delimiters inside [] and {}
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
     * @ai-contract extracts the first non-tag, non-empty line as the summary sentence
     * @ai-contract returns empty string if no such line exists
     */
    public function extract_summary(string $docblock): string {
        $summary_lines = [];
        foreach ($this->strip_lines($docblock) as $line) {
            if (str_starts_with($line, '@') || str_starts_with($line, '#AI')) {
                break;
            }
            $summary_lines[] = $line;
        }
        // Trim leading and trailing empty lines from the array
        while (count($summary_lines) > 0 && trim(reset($summary_lines)) === '') {
            array_shift($summary_lines);
        }
        while (count($summary_lines) > 0 && trim(end($summary_lines)) === '') {
            array_pop($summary_lines);
        }
        return implode("\n", $summary_lines);
    }

    /**
     * @ai-contract strips docblock delimiters and leading * characters from each line
     * @ai-contract returns array of trimmed content lines
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
