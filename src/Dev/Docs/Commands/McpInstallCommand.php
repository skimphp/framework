<?php declare(strict_types=1);

namespace Skim\Dev\Docs\Commands;

/**
 * Downloads the platform-native skim-mcp binary from GitHub Releases. #AI:class
 *
 * Use to install or update the MCP server binary for your OS/architecture.
 * The binary is saved to .skim/bin/skim-mcp and made executable.
 *
 * Example:
 *   php skim mcp:install
 *   php skim mcp:install --force
 *
 * Testing: Instantiate directly and call handle(); checks filesystem writes.
 *
 * #AI:class
 */
class McpInstallCommand extends \Skim\Cli\Command {
    protected string $description = 'download the native skim-mcp binary for your platform';
    private const VERSION = 'mcp-v0.1.0';
    private const BASE_URL = 'https://github.com/skimphp/skim_mcp/releases/download';

    /**
     * Detects platform, downloads binary, writes to .skim/bin/skim-mcp. #AI:handle
     *
     * Skips download if binary already exists unless --force is passed.
     * Returns 1 on network or write failure.
     */
    public function handle(): int {
        $force = $this->flag('force', false);
        $binDir = basePath('.skim/bin');
        $binPath = $binDir . '/skim-mcp';

        if (PHP_OS_FAMILY === 'Windows') {
            $binPath .= '.exe';
        }

        if (!$force && file_exists($binPath)) {
            $this->info("Binary already exists at {$binPath}. Pass --force to reinstall.");
            return 0;
        }

        $platform = $this->detectPlatform();
        if ($platform === null) {
            $this->error('Unsupported platform: ' . PHP_OS_FAMILY . ' ' . php_uname('m'));
            return 1;
        }

        $this->info("Downloading skim-mcp for {$platform}…");

        $url = self::BASE_URL . '/' . self::VERSION . '/skim-mcp-' . $platform;
        if (PHP_OS_FAMILY === 'Windows') {
            $url .= '.exe';
        }

        if (!is_dir($binDir)) {
            mkdir($binDir, 0755, true);
        }

        $tmpFile = $binPath . '.tmp';
        $this->download($url, $tmpFile);

        if (!file_exists($tmpFile) || filesize($tmpFile) === 0) {
            $this->error('Download failed or empty response from ' . $url);
            @unlink($tmpFile);
            return 1;
        }

        rename($tmpFile, $binPath);
        chmod($binPath, 0755);

        $size = $this->formatBytes(filesize($binPath));
        $this->success("skim-mcp installed → {$binPath} ({$size})");
        return 0;
    }

    /**
     * Maps PHP OS family + machine type to release asset name. #AI:detectPlatform
     */
    private function detectPlatform(): ?string {
        $os = [
            'Darwin' => 'darwin',
            'Linux' => 'linux',
            'Windows' => 'windows',
        ][PHP_OS_FAMILY] ?? null;

        $arch = [
            'x86_64' => 'x64',
            'amd64' => 'x64',
            'arm64' => 'arm64',
            'aarch64' => 'arm64',
        ][php_uname('m')] ?? null;

        if ($os === null || $arch === null) {
            return null;
        }
        return "{$os}-{$arch}";
    }

    /**
     * Downloads a remote file to a local path using curl. #AI:download
     */
    private function download(string $url, string $dest): void {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException("Failed to init curl for {$url}");
        }

        $fp = fopen($dest, 'wb');
        if ($fp === false) {
            throw new \RuntimeException("Failed to open temp file {$dest}");
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, 'skim-mcp-installer/1.0');

        if (!curl_exec($ch)) {
            $error = curl_error($ch);
            fclose($fp);
            @unlink($dest);
            throw new \RuntimeException("Download failed: {$error}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);

        if ($httpCode !== 200) {
            @unlink($dest);
            throw new \RuntimeException("HTTP {$httpCode} for {$url}");
        }
    }

    /**
     * Formats bytes into human-readable string. #AI:formatBytes
     */
    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

#AI:class
#AI symbol: Skim\Dev\Docs\Commands\McpInstallCommand
#AI source_path: src/Dev/Docs/Commands/McpInstallCommand.php
#AI title: McpInstallCommand
#AI description: CLI command that downloads the platform-native skim-mcp binary from GitHub Releases into .skim/bin/.
#AI role: MCP binary installer
#AI layer: dev
#AI badges: [cli; mcp; installer; github-releases]
#AI intro: `McpInstallCommand` detects the host OS/architecture, downloads the matching `skim-mcp` release asset from the skim_mcp repository, and installs it to `.skim/bin/skim-mcp` with executable permissions.
#AI lifecycle: instantiated by CLI router per invocation
#AI fallback: returns 1 on unsupported platform, network failure, or empty download
#AI test_seam: instantiate directly, call setInput(), then handle(); skip-download path needs only a pre-existing binary file
#AI invariants: [never overwrites existing binary without --force; binary lands in .skim/bin]
#AI core_behaviors: [Skips download when binary exists; Maps PHP_OS_FAMILY+uname to release asset name; Streams download via curl; chmod 0755 after install]
#AI owns: .skim/bin/skim-mcp binary lifecycle
#AI entry_points: [handle]
#AI config_reads: []
#AI side_effects: [network download from GitHub Releases; writes .skim/bin/skim-mcp; chmod]
#AI flow: handle() -> check existing binary -> detectPlatform -> download(asset url) -> rename+chmod -> report size
