#!/usr/bin/env node
'use strict';

// Thin Node.js wrapper around the PHP stdio MCP server.
// All logic lives in PHP — this file is transport only.
// Spawns `php skim mcp:serve` and pipes stdin/stdout.

const { spawn } = require('child_process');
const path = require('path');

const skimRoot = process.env.SKIM_ROOT ?? process.cwd();
const skimBin  = path.join(skimRoot, 'bin', 'skim');

const php = spawn('php', [skimBin, 'mcp:serve'], {
    cwd:   skimRoot,
    stdio: ['pipe', 'pipe', 'inherit'],
    env:   { ...process.env, APP_ENV: process.env.APP_ENV ?? 'development' },
});

php.on('error', (err) => {
    process.stderr.write(`[skim-mcp] Failed to start PHP server: ${err.message}\n`);
    process.exit(1);
});

php.on('exit', (code) => {
    process.exit(code ?? 0);
});

process.stdin.pipe(php.stdin);
php.stdout.pipe(process.stdout);

process.on('SIGINT',  () => { php.kill('SIGINT');  });
process.on('SIGTERM', () => { php.kill('SIGTERM'); });
