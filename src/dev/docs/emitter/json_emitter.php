<?php declare(strict_types=1);

namespace skim\dev\docs\emitter;

use skim\dev\docs\value\extracted_class;

// Serializes extracted_class[] to llm.json — the single source of truth for all doc outputs.
// All other emitters (llm_md_emitter, mdx_emitter) and the MCP server read from this file.
// No framework dependencies — plain PHP only.
class json_emitter {
    /**
     * @ai-contract accepts extracted_class[], writes structured JSON to $output_path
     * @ai-contract creates parent directories if they do not exist
     * @ai-contract throws \RuntimeException if the file cannot be written
     * @ai-contract JSON is pretty-printed for readability and diff-friendliness
     */
    public function emit(array $classes, string $output_path): void {
        $data = [
            'generated_at' => date('c'),
            'classes'      => array_map(fn(extracted_class $c) => $c->to_array(), $classes),
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('json_emitter: json_encode failed: ' . json_last_error_msg());
        }

        $dir = dirname($output_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        if (file_put_contents($output_path, $json) === false) {
            throw new \RuntimeException("json_emitter: cannot write to {$output_path}");
        }
    }

    /**
     * @ai-contract reads and decodes llm.json from $path
     * @ai-contract returns decoded array on success
     * @ai-contract throws \RuntimeException if file is missing or JSON is invalid
     */
    public function load(string $path): array {
        if (!file_exists($path)) {
            throw new \RuntimeException("json_emitter: llm.json not found at {$path} — run docs:extract first");
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("json_emitter: cannot read {$path}");
        }
        $data = json_decode($raw, associative: true);
        if (!is_array($data)) {
            throw new \RuntimeException("json_emitter: invalid JSON in {$path}");
        }
        return $data;
    }
}
