<?php declare(strict_types=1);

namespace skim\ext;

final class ext_manifest {
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
