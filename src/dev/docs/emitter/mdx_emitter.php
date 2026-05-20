<?php declare(strict_types=1);

namespace skim\dev\docs\emitter;

// Reads decoded llm.json data and generates one MDX file per class
// under docs/src/content/docs/api/ for the Starlight (Astro) docs site.
// No framework dependencies — plain PHP only.
class mdx_emitter {
    /**
     * @ai-contract accepts decoded llm.json array and output directory path
     * @ai-contract creates one MDX file per class: {class_name}.mdx
     * @ai-contract creates output directory if it does not exist
     * @ai-contract returns count of files written
     * @ai-contract throws \RuntimeException on write failure
     */
    public function emit(array $data, string $output_dir): int {
        $classes = $data['classes'] ?? [];

        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, recursive: true);
        }

        $count = 0;
        foreach ($classes as $class) {
            $content = $this->render_class($class);
            $path    = $output_dir . '/' . $class['class_name'] . '.mdx';
            if (file_put_contents($path, $content) === false) {
                throw new \RuntimeException("mdx_emitter: cannot write {$path}");
            }
            $count++;
        }
        return $count;
    }

    /**
     * @ai-contract renders a single class entry to Starlight-compatible MDX string
     * @ai-contract includes: title, summary, lifecycle, methods with signature+contracts+invariants,
     *              non-goals section, side effects section, owner badge
     */
    private function render_class(array $class): string {
        $name      = $class['class_name'] ?? '';
        $namespace = $class['namespace']  ?? '';
        $summary   = $class['summary']    ?? '';
        $lifecycle = $class['lifecycle']  ?? '';
        $owner     = $class['owner']      ?? '';

        $desc = '';
        if ($summary !== '') {
            $desc = $this->get_first_sentence($summary);
            $desc = str_replace(["\r", "\n"], ' ', $desc);
            $desc = preg_replace('/\s+/', ' ', $desc);
        } else {
            $parts = [];
            if ($owner !== '') {
                $parts[] = "{$owner}";
            }
            if ($lifecycle !== '') {
                $parts[] = "runs in {$lifecycle} lifecycle";
            }
            if ($parts !== []) {
                $desc = ucfirst(implode(' — ', $parts)) . '.';
            } else {
                $desc = "Class {$name}.";
            }
        }
        $desc_escaped = str_replace('"', '\"', $desc);

        $lines   = [];
        $lines[] = '---';
        $lines[] = "title: {$name}";
        $lines[] = "description: \"{$desc_escaped}\"";
        $lines[] = '---';
        $lines[] = '';

        $intro = '';
        if ($summary !== '') {
            $intro = $summary;
        } else {
            $intro = "`{$name}` is located in `{$namespace}`.";
            if ($owner !== '') {
                $intro .= " It is owned by {$owner}.";
            }
            if ($lifecycle !== '') {
                $intro .= " It is active during the {$lifecycle} lifecycle.";
            }
        }
        $lines[] = $this->escape_mdx($intro);
        $lines[] = '';

        $methods = $class['methods'] ?? [];
        $lifecycle_methods = [];
        foreach ($methods as $method) {
            if (($method['lifecycle'] ?? '') !== '') {
                if (str_contains($method['name'], 'test')) {
                    continue;
                }
                $lifecycle_methods[] = $method['name'] . '()';
            }
        }
        if (count($lifecycle_methods) >= 2) {
            $lines[] = '## Lifecycle';
            $lines[] = '';
            $lines[] = implode(' → ', $lifecycle_methods);
            if ($lifecycle !== '') {
                $lines[] = $lifecycle;
            }
            $lines[] = '';
        }

        if ($methods !== []) {
            $lines[] = '## Methods';
            $lines[] = '';
            $lines[] = '| Method | What it does |';
            $lines[] = '|--------|-------------|';
            foreach ($methods as $method) {
                $short_sig = $this->get_short_signature($method['signature'] ?? '');
                $short_sig = str_replace('|', '\|', $short_sig);
                $method_desc = $this->build_method_description($method);
                $lines[] = "| `{$short_sig}` | " . $this->escape_mdx($method_desc) . " |";
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private function get_first_sentence(string $text): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, 2);
        return $sentences[0] ?? $text;
    }

    private function get_short_signature(string $signature): string {
        if (preg_match('/function\s+(\w+)\s*\((.*?)\)/', $signature, $matches)) {
            $method_name = $matches[1];
            $params_str = $matches[2];
            
            if (trim($params_str) === '') {
                return $method_name . '()';
            }
            
            $params = explode(',', $params_str);
            $short_params = [];
            foreach ($params as $param) {
                $param = trim($param);
                if (str_contains($param, '=')) {
                    $parts = explode('=', $param, 2);
                    $param = trim($parts[0]);
                }
                $short_params[] = $param;
            }
            
            return $method_name . '(' . implode(', ', $short_params) . ')';
        }
        return $signature;
    }

    private function build_method_description(array $method): string {
        $parts = [];
        
        $base = '';
        if (!empty($method['contracts'])) {
            $base = trim($method['contracts'][0]);
            $base = $this->format_sentence($base);
        }
        
        if ($base !== '') {
            $parts[] = $base;
        }
        
        foreach ($method['invariants'] ?? [] as $inv) {
            $inv_lower = strtolower($inv);
            if (str_contains($inv_lower, 'never throws')) {
                if (str_contains($inv_lower, 'missing key')) {
                    $parts[] = 'Never throws — returns `$default` for missing keys.';
                } else {
                    $parts[] = $this->format_sentence($inv);
                }
            } else {
                $parts[] = $this->format_sentence($inv);
            }
        }
        
        foreach ($method['non_goals'] ?? [] as $ng) {
            $parts[] = $this->format_sentence($ng);
        }

        foreach ($method['throws'] ?? [] as $throw) {
            $throw_trimmed = trim($throw);
            if (preg_match('/^(\\\\?\w+)(?:\s+(.+))?$/', $throw_trimmed, $matches)) {
                $type = $matches[1];
                if (str_contains($base, $type)) {
                    continue;
                }
                $reason = $matches[2] ?? '';
                $reason = preg_replace('/^when\s+/', 'if ', $reason) ?? $reason;
                $parts[] = "Throws `{$type}`" . ($reason !== '' ? " {$reason}" : "") . ".";
            } else {
                $parts[] = "Throws " . $this->format_sentence($throw);
            }
        }

        foreach ($method['side_effects'] ?? [] as $se) {
            $se_lower = strtolower($se);
            if (str_contains($se_lower, 'clear') || str_contains($se_lower, 'install') || str_contains($se_lower, 'global')) {
                $se_formatted = $this->format_sentence($se);
                $already_covered = false;
                foreach ($parts as $part) {
                    if (str_contains(strtolower($part), 'clear') && str_contains($se_lower, 'clear')) {
                        $already_covered = true;
                        break;
                    }
                }
                if (!$already_covered) {
                    $parts[] = $se_formatted;
                }
            }
        }

        $full_desc = implode(' ', $parts);
        $full_desc = str_replace(["\r", "\n"], ' ', $full_desc);
        $full_desc = preg_replace('/\s+/', ' ', $full_desc);
        $full_desc = preg_replace('/\.+/', '.', $full_desc) ?? $full_desc;
        return str_replace('|', '\|', $full_desc);
    }

    private function format_sentence(string $str): string {
        $str = trim($str);
        if ($str === '') {
            return '';
        }
        $str = ucfirst($str);
        $last_char = substr($str, -1);
        if ($last_char !== '.' && $last_char !== '!' && $last_char !== '?') {
            $str .= '.';
        }
        return $str;
    }

    private function escape_mdx(string $text): string {
        // Split by inline code blocks (anything inside backticks)
        $parts = preg_split('/(`[^`]*`)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        foreach ($parts as $i => &$part) {
            // Even indices are outside of backticks
            if ($i % 2 === 0) {
                $part = str_replace(
                    ['<', '>', '{', '}'],
                    ['&lt;', '&gt;', '&#123;', '&#125;'],
                    $part
                );
            }
        }
        return implode('', $parts);
    }
}
