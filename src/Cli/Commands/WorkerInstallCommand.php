<?php declare(strict_types=1);

namespace Skim\Cli\Commands;

use Skim\Cli\Command;

/**
 * CLI command that generates FrankenPHP worker mode files.
 *
 * Creates:
 * - public/worker.php
 * - Caddyfile
 * - docker/Dockerfile.frankenphp
 *
 * Example:
 *   php skim worker:install
 *
 * #AI:class
 */
class WorkerInstallCommand extends \Skim\Cli\Command {
    /**
     * Generates the worker entrypoint, Caddyfile, and Dockerfile. #AI:handle
     */
    public function handle(): int {
        $root = SKIM_ROOT;

        $workerSource = __DIR__ . '/../../../public/worker.php';
        if (!is_file($workerSource)) {
            $this->error('public/worker.php not found — cannot install worker files.');
            return 1;
        }

        if (!is_dir($root . '/docker')) {
            mkdir($root . '/docker', 0755, true);
        }

        $workerDest = $root . '/public/worker.php';
        if (!is_file($workerDest)) {
            copy($workerSource, $workerDest);
        }

        file_put_contents($root . '/Caddyfile', $this->caddyfile());
        file_put_contents($root . '/docker/Dockerfile.frankenphp', $this->dockerfile());

        $this->success('FrankenPHP worker files installed.');
        return 0;
    }

    /**
     * Caddyfile with FrankenPHP worker directive.
     */
    private function caddyfile(): string {
        return <<<CADDY
        {
            frankenphp {
                worker {
                    file public/worker.php
                    num 1
                }
            }
        }

        :80 {
            root * /app/public
            php_server
        }
        CADDY;
    }

    /**
     * Dockerfile based on the official FrankenPHP image.
     */
    private function dockerfile(): string {
        return <<<DOCKER
        FROM dunglas/frankenphp:latest

        WORKDIR /app

        COPY . /app

        ENV FRANKENPHP_CONFIG="worker /app/public/worker.php"

        EXPOSE 80 443
        DOCKER;
    }
}

#AI:class
#AI symbol: Skim\Cli\Commands\WorkerInstallCommand
#AI source_path: src/Cli/Commands/WorkerInstallCommand.php
#AI title: WorkerInstallCommand
#AI description: CLI command that generates FrankenPHP worker mode files.
#AI role: CLI worker install command
#AI layer: cli
#AI badges: [cli; command; worker; frankenphp]
#AI section_order: [Command Execution]
#AI entry_points: [handle]
