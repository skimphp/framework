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
     * @ai-contract extracts the first non-tag, non-empty line as the summary sentence
     * @ai-contract returns empty string if no such line exists
     */
    public function extract_summary(string $docblock): string {
        foreach ($this->strip_lines($docblock) as $line) {
            if ($line !== '' && !str_starts_with($line, '@')) {
                return $line;
            }
        }
        return '';
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
