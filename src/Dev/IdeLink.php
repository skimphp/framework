<?php declare(strict_types=1);

namespace Skim\Dev;

use Skim\Core\Config;

/**
 * Builds deep-link URLs that open a file:line in the developer's editor of choice. #AI:class
 *
 * The active IDE is read from `app.debug_ide` config (default: `phpstorm`).
 * Supported identifiers cover the most popular editors on macOS / Linux / Windows —
 * JetBrains family, VS Code family, Sublime, TextMate, Emacs, MacVim, Atom (legacy).
 *
 * Each IDE has a known URL scheme. The URL is rendered into anchor `href` attributes
 * in the error page and toolbar; the OS launches the registered protocol handler.
 *
 * Example:
 *   <a href="<?= IdeLink::url('/app/src/Foo.php', 42) ?>">Open in IDE</a>
 *   <a href="<?= IdeLink::url('/app/src/Foo.php', 42, ide: 'vscode') ?>">VS Code</a>
 *
 * Testing: pure string output — assert url() format and name() for known/unknown IDEs.
 *
 * #AI:class
 */
final class IdeLink {
    /**
     * Order in which supported() returns IDE identifiers. Display order in pickers. #AI:ORDER
     */
    private const ORDER = [
        'phpstorm', 'idea', 'webstorm',
        'vscode', 'cursor',
        'sublime', 'textmate', 'emacs', 'macvim', 'atom',
    ];

    /**
     * Maps IDE identifier → human display name. #AI:NAMES
     */
    private const NAMES = [
        'phpstorm' => 'PhpStorm',
        'idea'     => 'IntelliJ IDEA',
        'webstorm' => 'WebStorm',
        'vscode'   => 'VS Code',
        'cursor'   => 'Cursor',
        'sublime'  => 'Sublime Text',
        'textmate' => 'TextMate',
        'emacs'    => 'Emacs',
        'macvim'   => 'MacVim',
        'atom'     => 'Atom (legacy)',
    ];

    /**
     * Returns the list of supported IDE identifiers. #AI:supported
     *
     * Order matches the canonical display order. Use this to populate config
     * pickers, documentation, or `IdeLink::isSupported()` validation.
     *
     * @return string[] IDE identifier keys (e.g. ['phpstorm', 'vscode', ...]).
     */
    public static function supported(): array {
        return self::ORDER;
    }

    /**
     * Returns the human-friendly display name for an IDE identifier. #AI:name
     *
     * Falls back to the identifier itself for unknown values.
     */
    public static function name(string $ide): string {
        return self::NAMES[$ide] ?? $ide;
    }

    /**
     * Returns true when the given IDE identifier has a registered URL scheme. #AI:isSupported
     */
    public static function isSupported(string $ide): bool {
        return isset(self::NAMES[$ide]);
    }

    /**
     * Returns the IDE identifier that should be used for deep-links. #AI:resolve
     *
     * Resolution order: explicit $ide argument → `app.debug_ide` config → 'phpstorm'.
     * Unknown config values fall back silently to 'phpstorm' to avoid breaking
     * the error page if the config typo'd.
     */
    public static function resolve(?string $ide = null): string {
        $ide = $ide ?? (string) \Skim\Core\Config::get('app.debug_ide', 'phpstorm');
        if (!isset(self::NAMES[$ide])) {
            return 'phpstorm';
        }
        return $ide;
    }

    /**
     * Builds an IDE deep-link URL for a file:line. #AI:url
     *
     * @param string   $file   Absolute path to the source file.
     * @param int      $line   1-based line number.
     * @param int|null $column 1-based column number (default 1, used by VS Code/Cursor).
     * @param string|null $ide Optional IDE override; defaults to config('app.debug_ide').
     * @return string Full deep-link URL ready for an anchor href.
     */
    public static function url(string $file, int $line, ?int $column = 1, ?string $ide = null): string {
        $ide    = self::resolve($ide);
        $column = $column ?? 1;
        $enc    = rawurlencode($file);
        return match ($ide) {
            'phpstorm' => "phpstorm://open?file={$enc}&line={$line}&column={$column}",
            'idea'     => "idea://open?file={$enc}&line={$line}&column={$column}",
            'webstorm' => "webstorm://open?file={$enc}&line={$line}&column={$column}",
            'vscode'   => "vscode://file/{$enc}:{$line}:{$column}",
            'cursor'   => "cursor://file/{$enc}:{$line}:{$column}",
            'sublime'  => "subl://open?url=file://{$enc}&line={$line}&column={$column}",
            'textmate' => "txmt://open?url=file://{$enc}&line={$line}&column={$column}",
            'emacs'    => "emacs://open?url=file://{$enc}&line={$line}&column={$column}",
            'macvim'   => "mvim://open?url=file://{$enc}&line={$line}&column={$column}",
            'atom'     => "atom://core/open/file?filename={$enc}&line={$line}&column={$column}",
            default    => "phpstorm://open?file={$enc}&line={$line}&column={$column}",
        };
    }

    /**
     * Returns the icon name (tabler-icons) for the IDE, when a known mapping exists. #AI:icon
     *
     * JetBrains family shares a single brand icon. VS Code and Cursor have
     * distinct brand icons. Unknown IDEs return null — caller falls back to
     * a generic icon.
     */
    public static function icon(string $ide): ?string {
        return match (self::resolve($ide)) {
            'phpstorm', 'idea', 'webstorm' => 'brand-phpstorm',
            'vscode'                       => 'brand-vscode',
            'cursor'                       => 'cursor',
            'sublime'                      => 'sublime',
            'textmate'                     => 'file-text',
            'emacs'                        => 'brand-github',
            'macvim'                       => 'terminal-2',
            'atom'                         => 'atom',
            default                        => null,
        };
    }
}

#AI:class
#AI symbol: Skim\Dev\IdeLink
#AI source_path: src/Dev/IdeLink.php
#AI title: IdeLink
#AI description: Builds deep-link URLs that open a file:line in the developer's editor — phpstorm (default), vscode, cursor, sublime, idea, webstorm, textmate, emacs, macvim, atom.
#AI role: editor deep-link provider
#AI layer: dev
#AI badges: [dev; debug; ide; deep-link; protocol-handler; config]
#AI intro: `IdeLink` is a single source of truth for editor deep-links. It owns the registry of supported IDEs and the URL scheme each one uses, so the error page and toolbar never hard-code `phpstorm://` directly. The active IDE is read from `app.debug_ide` config (default: `phpstorm`); unknown values fall back silently to phpstorm.
#AI lifecycle: stateless — pure string lookups
#AI fallback: unknown IDE identifiers in config or arguments fall back to 'phpstorm' to keep the error page functional
#AI test_seam: assert url() format per IDE, supported() list, resolve() fallback, isSupported()
#AI invariants: [NAMES keys are the canonical supported IDE list; url() always returns a non-empty string; resolve() never throws; column defaults to 1 for VS Code/Cursor schemes that require it]
#AI core_behaviors: [Maps IDE identifier → display name and URL builder; Reads active IDE from app.debug_ide config; Builds JetBrains, VS Code, Sublime, TextMate, Emacs, MacVim, Atom URL schemes; Maps each IDE to a tabler-icons icon name]
#AI owns: IDE registry (identifier, name, URL scheme)
#AI entry_points: [supported; name; isSupported; resolve; url; icon]
#AI config_reads: [app.debug_ide]
#AI non_goals: [Does not check whether the IDE is installed locally; Does not launch the IDE — only generates the URL the OS protocol handler consumes; Does not support custom user-defined schemes (extension point lives in app code, not the framework)]
#AI side_effects: [none — pure string output]
#AI flow: url($file, $line) → resolve(ide?) → match on ide → URL string
#AI lifecycle_steps: [url($file, $line, $column, $ide); → resolve($ide) [config fallback → 'phpstorm']; → match on ide; → return URL]
#AI section_order: [Registry; Resolution; URL Building; Icons; Architecture]
#AI architectural_notes: The registry is a const map, not a config file — URL schemes are stable platform facts, not app preferences. Adding a new IDE is a one-line change in ORDER, NAMES, and the match in url() / icon(). Per-app customization (custom commands, env vars, project roots) belongs in app code, not here.

#AI:supported
#AI group: Registry
#AI frequency: low
#AI signature: public static function supported(): array
#AI contract: Returns the list of IDE identifiers this build knows about, in canonical display order.
#AI return_detail: {type: string[] | desc: IDE identifier keys (e.g. ['phpstorm', 'idea', 'webstorm', 'vscode', 'cursor', 'sublime', 'textmate', 'emacs', 'macvim', 'atom']).}

#AI:name
#AI group: Registry
#AI frequency: low
#AI signature: public static function name(string $ide): string
#AI contract: Returns the human-friendly display name for an IDE identifier. Returns the identifier itself for unknown values.
#AI param_details: [{name: $ide | type: string | required: true | desc: IDE identifier from supported().}]
#AI return_detail: {type: string | desc: Display name (e.g. 'PhpStorm', 'VS Code') or the identifier when unknown.}

#AI:isSupported
#AI group: Registry
#AI frequency: low
#AI signature: public static function isSupported(string $ide): bool
#AI contract: Returns true when the given IDE identifier has a registered URL scheme.
#AI param_details: [{name: $ide | type: string | required: true | desc: IDE identifier to check.}]
#AI return_detail: {type: bool | desc: True when the IDE is in the registry.}

#AI:resolve
#AI group: Resolution
#AI frequency: high
#AI signature: public static function resolve(?string $ide = null): string
#AI contract: Returns the IDE identifier that should be used for deep-links. Resolution order: explicit argument → app.debug_ide config → 'phpstorm'. Unknown values fall back to 'phpstorm' silently.
#AI param_details: [{name: $ide | type: ?string | required: false | desc: Optional explicit override. Null reads config.}]
#AI return_detail: {type: string | desc: Resolved IDE identifier — always a supported key, never an empty string.}

#AI:url
#AI group: URL Building
#AI frequency: high
#AI signature: public static function url(string $file, int $line, ?int $column = 1, ?string $ide = null): string
#AI contract: Builds a deep-link URL for the resolved IDE that opens file:line(:column) in the editor.
#AI param_details: [{name: $file | type: string | required: true | desc: Absolute path to the source file.}; {name: $line | type: int | required: true | desc: 1-based line number.}; {name: $column | type: ?int | required: false | desc: 1-based column number. Defaults to 1.}; {name: $ide | type: ?string | required: false | desc: Optional IDE override. Null uses config.}]
#AI return_detail: {type: string | desc: Full deep-link URL ready for an anchor href.}

#AI:icon
#AI group: Icons
#AI frequency: low
#AI signature: public static function icon(string $ide): ?string
#AI contract: Returns the tabler-icons icon name for the given IDE. Returns null when no icon mapping exists.
#AI param_details: [{name: $ide | type: string | required: true | desc: IDE identifier to check.}]
#AI return_detail: {type: ?string | desc: tabler-icons class suffix (e.g. 'brand-phpstorm', 'brand-vscode', 'cursor') or null.}
