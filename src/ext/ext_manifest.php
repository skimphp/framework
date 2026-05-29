<?php declare(strict_types=1);

namespace skim\ext;

/**
 * Generates a JSON manifest file from an extension's runtime metadata.
 *
 * Use during extension development or CI to produce a skim.json snapshot
 * that ext_registry can read without instantiating the extension class.
 * Writes a pretty-printed JSON file to the specified output path.
 *
 * Example:
 *   ext_manifest::generate($extension, __DIR__ . '/skim.json');
 *
 * #AI:class
 */
final class ext_manifest {
    /**
     * Writes extension metadata as pretty-printed JSON to disk. #AI:generate
     *
     * Collects name, version, capabilities, config, commands, env keys, and
     * migration presence from the extension instance. Overwrites any existing
     * file at the output path.
     *
     * Example:
     *   ext_manifest::generate($ext, '/path/to/vendor/acme/auth/skim.json');
     *
     * @param extension $ext         Instantiated extension to extract metadata from.
     * @param string    $output_path Absolute file path for the JSON output.
     */
    public static function generate(extension $ext, string $output_path): void {
        $data = [
            'name'         => $ext->name,
            'version'      => $ext->version,
            'description'  => $ext->description,
            'requires'     => $ext->requires,
            'provides'     => $ext->provides,
            'conflicts'    => $ext->conflicts,
            'capabilities' => $ext->capabilities,
            'config'       => $ext->config(),
            'migrations'   => $ext->migrations() !== '',
            'commands'     => array_keys($ext->commands()),
            'env_keys'     => $ext->env_keys(),
            'post_install' => $ext->post_install(),
        ];

        file_put_contents($output_path, json_encode($data, JSON_PRETTY_PRINT));
    }
}

#AI:class
#AI symbol: skim\ext\ext_manifest
#AI source_path: src/ext/ext_manifest.php
#AI title: ext_manifest
#AI description: Generates a JSON manifest snapshot from an extension's runtime metadata.
#AI role: manifest generator
#AI layer: ext
#AI badges: [extension; manifest; json; generator]
#AI intro: `ext_manifest` serializes an extension instance's metadata into a pretty-printed JSON file. The generated skim.json allows ext_registry to read extension data without class instantiation.
#AI lifecycle: stateless, one-shot file write
#AI test_seam: pass a mock extension and temp path
#AI invariants: [Overwrites existing file at output_path; JSON is pretty-printed]
#AI core_behaviors: [Extracts all public metadata from extension instance and writes JSON]
#AI owns: nothing
#AI entry_points: [generate]
#AI config_reads: []
#AI non_goals: [Does not validate metadata; Does not read existing manifests]
#AI side_effects: [Writes file to disk at output_path]
#AI section_order: [Generation]

#AI:generate
#AI group: Generation
#AI frequency: low
#AI signature: public static function generate(extension $ext, string $output_path): void
#AI contract: Serializes extension metadata to a JSON file. Overwrites any existing file at the target path.
#AI param_details: [{name: $ext | type: extension | required: true | desc: Instantiated extension to extract metadata from.}; {name: $output_path | type: string | required: true | desc: Absolute file path for the JSON output.}]
#AI side_effects: [Writes JSON file to disk]
