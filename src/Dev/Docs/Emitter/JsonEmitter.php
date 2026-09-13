<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Emitter;

use Skim\Dev\Docs\Value\ExtractedClass;

/**
 * Serializes ExtractedClass[] to llm.json — the single source of truth for all doc outputs. #AI:class
 *
 * Use when writing the intermediate JSON that all other emitters and the
 * MCP server consume. No framework dependencies — plain PHP only.
 *
 * Example:
 *   (new JsonEmitter())->emit($classes, 'llm.json', $capabilityMap, $extensions);
 *   $data = (new JsonEmitter())->load('llm.json');
 *
 * Testing: Instantiate directly; operates on filesystem paths.
 *
 * #AI:class
 */
class JsonEmitter {
    /**
     * Writes extracted classes as pretty-printed JSON. #AI:emit
     *
     * Creates parent directories if they do not exist. When $capability_map
     * is non-empty, it is written as a top-level "capabilities" section.
     * When $installed_extensions is non-empty, written under "extensions.installed".
     *
     * Example:
     *   (new JsonEmitter())->emit($classes, 'build/llm.json');
     *
     * @param \Skim\Dev\Docs\Value\ExtractedClass[] $classes Extracted class records to serialize.
     * @param string            $outputPath          Absolute or relative path for the JSON file.
     * @param array             $capabilityMap       Extension capability details, keyed by capability name.
     * @param string[]          $installedExtensions List of installed extension names.
     *
     * @throws \RuntimeException If json_encode fails or the file cannot be written.
     */
    public function emit(array $classes, string $outputPath, array $capabilityMap = [], array $installedExtensions = []): void {
        $data = [
            'generated_at' => date('c'),
            'classes'      => array_map(fn(\Skim\Dev\Docs\Value\ExtractedClass $c) => $c->toArray(), $classes),
        ];

        if ($capabilityMap !== []) {
            $data['capabilities'] = $capabilityMap;
        }

        if ($installedExtensions !== []) {
            $data['extensions'] = ['installed' => $installedExtensions];
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('json_emitter: json_encode failed: ' . json_last_error_msg());
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, recursive: true);
        }

        if (file_put_contents($outputPath, $json) === false) {
            throw new \RuntimeException("json_emitter: cannot write to {$outputPath}");
        }
    }

    /**
     * Reads and decodes llm.json from disk. #AI:load
     *
     * @param string $path Path to the llm.json file.
     * @return array Decoded JSON data.
     *
     * @throws \RuntimeException If file is missing, unreadable, or contains invalid JSON.
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

#AI:class
#AI symbol: Skim\Dev\Docs\Emitter\JsonEmitter
#AI source_path: src/Dev/Docs/Emitter/JsonEmitter.php
#AI title: JsonEmitter
#AI description: Serializes extracted class metadata to llm.json and loads it back — the single source of truth for all doc outputs.
#AI role: JSON serialization layer
#AI layer: dev
#AI badges: [emitter; json; docs; no-framework-deps]
#AI intro: `JsonEmitter` is the serialization boundary between the AST extraction pipeline and all downstream consumers (LlmMdEmitter, MdxEmitter, mcp_server). It writes pretty-printed JSON with optional extension capability enrichment.
#AI lifecycle: instantiated per-use by commands, no state retained
#AI fallback: none — throws on write/read failure
#AI test_seam: instantiate directly with temp file paths
#AI invariants: [emit() creates parent directories; load() throws when file is missing or JSON is invalid; JSON is pretty-printed with unescaped slashes and unicode]
#AI core_behaviors: [Serializes ExtractedClass[] to structured JSON; Optionally enriches with extension capabilities; Loads and validates llm.json for downstream consumers]
#AI owns: none — stateless
#AI entry_points: [emit; load]
#AI config_reads: []
#AI non_goals: [Does not extract classes; Does not generate Markdown or MDX]
#AI side_effects: [writes llm.json to disk; creates parent directories]
#AI flow: emit(classes, path) -> json_encode -> file_put_contents; load(path) -> file_get_contents -> json_decode
#AI lifecycle_steps: [emit(); -> build data array; -> json_encode; -> mkdir if needed; -> file_put_contents; load(); -> file_exists check; -> file_get_contents; -> json_decode]
#AI section_order: [Serialization; Deserialization; Architecture]
#AI architectural_notes: No framework dependencies — plain PHP only. All other emitters and the MCP server read from the file this class produces.

#AI:emit
#AI group: Serialization
#AI frequency: high
#AI signature: public function emit(array $classes, string $outputPath, array $capabilityMap = [], array $installedExtensions = []): void
#AI contract: Serializes ExtractedClass[] to pretty-printed JSON at the given path. Creates parent directories if needed. Optionally includes extension capability data and installed extension names as top-level sections.
#AI param_details: [{name: $classes | type: ExtractedClass[] | required: true | desc: Class records to serialize.}; {name: $outputPath | type: string | required: true | desc: Filesystem path for the output JSON file.}; {name: $capabilityMap | type: array | required: false | desc: Extension capability details written as top-level "capabilities" section when non-empty.}; {name: $installedExtensions | type: string[] | required: false | desc: Installed extension names written under "extensions.installed" when non-empty.}]
#AI throws_details: [{type: \RuntimeException | desc: When json_encode fails or the file cannot be written.}]
#AI side_effects: [writes JSON file to disk; creates parent directories]

#AI:load
#AI group: Deserialization
#AI frequency: high
#AI signature: public function load(string $path): array
#AI contract: Reads and decodes llm.json from the given path. Throws when the file is missing, unreadable, or contains invalid JSON.
#AI param_details: [{name: $path | type: string | required: true | desc: Path to the llm.json file.}]
#AI return_detail: {type: array | desc: Decoded JSON data as an associative array.}
#AI throws_details: [{type: \RuntimeException | desc: When file is missing, unreadable, or contains invalid JSON.}]
