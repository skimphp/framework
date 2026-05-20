<?php declare(strict_types=1);

namespace skim\dev\docs\extractor;

// Parses raw PHPDoc string and extracts all @ai.* tags into typed arrays.
// Unknown tags are silently ignored — never thrown.
// No framework dependencies — plain PHP only.
class annotation_parser {
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
            $prefix = match (true) {
                str_starts_with($line, '@ai.') => 4,   // @ai.contract  → key offset 4
                str_starts_with($line, '@ai-') => 4,   // @ai-contract  → key offset 4
                default                        => null,
            };
            if ($prefix !== null) {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                }
                $space = strpos($line, ' ');
                if ($space === false) {
                    $current_key   = substr($line, $prefix);
                    $current_value = '';
                } else {
                    $current_key   = substr($line, $prefix, $space - $prefix);
                    $current_value = substr($line, $space + 1);
                }
            } elseif ($current_key !== null && $line !== '' && !str_starts_with($line, '@')) {
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

            if (str_starts_with($trimmed, '@ai.') || str_starts_with($trimmed, '@ai-')) {
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
            $prefix = match (true) {
                str_starts_with($line, '@ai.') => 4,
                str_starts_with($line, '@ai-') => 4,
                default                        => null,
            };
            if ($prefix !== null) {
                if ($current_key !== null) {
                    $result[$current_key][] = trim($current_value);
                }
                $space = strpos($line, ' ');
                if ($space === false) {
                    $current_key   = substr($line, $prefix);
                    $current_value = '';
                } else {
                    $current_key   = substr($line, $prefix, $space - $prefix);
                    $current_value = substr($line, $space + 1);
                }
            } elseif ($current_key !== null && $line !== '' && !str_starts_with($line, '@')) {
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
     * @ai-contract extracts the first non-tag, non-empty line as the summary sentence
     * @ai-contract returns empty string if no such line exists
     */
    public function extract_summary(string $docblock): string {
        $summary_lines = [];
        foreach ($this->strip_lines($docblock) as $line) {
            if (str_starts_with($line, '@')) {
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
