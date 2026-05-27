<?php declare(strict_types=1);

namespace skim\ext;

use skim\core\app;

abstract class extension {
    public string $name        = '';
    public string $version     = '';
    public string $description = '';

    public array $requires     = [];
    public array $provides     = [];
    public array $conflicts    = [];
    public array $capabilities = [];

    abstract public function register(app $app): void;
    abstract public function boot(app $app): void;

    public function migrations(): string  { return ''; }
    public function config(): array       { return []; }
    public function commands(): array     { return []; }
    public function env_keys(): array     { return []; }
    public function post_install(): array { return []; }

    /**
     * @ai-contract returns static metadata without instantiation — override in subclasses
     * @ai-contract empty array signals no static manifest; registry falls back to instance properties
     * @ai-contract capabilities key may be a flat list or an associative map of capability => details
     */
    public static function manifest(): array { return []; }
}
