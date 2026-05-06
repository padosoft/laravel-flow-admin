#!/usr/bin/env node
/**
 * Boots Orchestra Testbench's bundled Laravel app via `php vendor/bin/testbench serve`
 * so Playwright can run E2E tests against the package's real route stack.
 *
 * Provider registration: `vendor/bin/testbench serve` does NOT honour the
 * `extra.laravel.providers` block in `composer.json` (that block is consumed
 * only by Laravel's package-discovery in a *consumer* app). Inside the package
 * itself, providers are registered through `testbench.yaml` `providers:`,
 * which `Orchestra\Testbench\Foundation\Config::loadFromYaml` reads at boot.
 * `testbench.yaml` also pins `FLOW_ADMIN_MIDDLEWARE=web` and
 * `FLOW_ADMIN_ADAPTER=array` via its `env:` block — the only env channel that
 * survives the bundled Dotenv load reliably.
 *
 * Cross-platform launcher:
 *   - POSIX: `spawn('php', ['vendor/bin/testbench', 'serve', …])`.
 *   - Windows: `spawn('cmd.exe', ['/c', 'php "vendor/bin/testbench" serve …'])`.
 *     cmd.exe handles PATH/PATHEXT lookup for `php` and tolerates spaces in
 *     the repository path (which Node's bare spawn cannot).
 */
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(here, '..');
const testbench = resolve(repoRoot, 'vendor/bin/testbench');

// Single source of truth: when FLOW_ADMIN_E2E_BASE_URL is set, derive host
// and port from it so the webServer Playwright polls (`url`) and the
// PHP-built-in server we spawn here listen on the same address. Without this,
// pointing Playwright at e.g. `http://0.0.0.0:9999` while the script kept
// binding to 127.0.0.1:8001 produced an unreachable webServer that never
// became ready (Codex P2 review on PR #10, 2026-05-06).
let host = process.env.FLOW_ADMIN_E2E_HOST ?? '127.0.0.1';
let port = process.env.FLOW_ADMIN_E2E_PORT ?? '8001';

const baseUrl = process.env.FLOW_ADMIN_E2E_BASE_URL;
if (typeof baseUrl === 'string' && baseUrl.length > 0) {
  try {
    const parsed = new URL(baseUrl);
    host = parsed.hostname || host;
    port = parsed.port || port;
  } catch (error) {
    console.error(
      `[serve-testbench] FLOW_ADMIN_E2E_BASE_URL=${baseUrl} is not a valid URL; ` +
        `falling back to FLOW_ADMIN_E2E_HOST=${host} FLOW_ADMIN_E2E_PORT=${port}.`,
      error,
    );
  }
}

if (!existsSync(testbench)) {
  console.error(
    `[serve-testbench] vendor/bin/testbench not found at ${testbench}.\n` +
      'Run `composer install` first (orchestra/testbench is a dev dependency).',
  );
  process.exit(1);
}

const env = {
  ...process.env,
  FLOW_ADMIN_MIDDLEWARE: process.env.FLOW_ADMIN_MIDDLEWARE ?? 'web',
  FLOW_ADMIN_ADAPTER: process.env.FLOW_ADMIN_ADAPTER ?? 'array',
};

let child;
if (process.platform === 'win32') {
  // Quote the testbench path so spaces (e.g. "Visual Basic") survive.
  const cmdLine = `php "${testbench}" serve --host=${host} --port=${port}`;
  child = spawn('cmd.exe', ['/d', '/s', '/c', cmdLine], {
    cwd: repoRoot,
    stdio: 'inherit',
    env,
    windowsVerbatimArguments: true,
  });
} else {
  child = spawn(
    'php',
    [testbench, 'serve', `--host=${host}`, `--port=${port}`],
    { cwd: repoRoot, stdio: 'inherit', env },
  );
}

const forward = (signal) => () => {
  if (!child.killed) {
    child.kill(signal);
  }
};
process.on('SIGINT', forward('SIGINT'));
process.on('SIGTERM', forward('SIGTERM'));

child.on('exit', (code, signal) => {
  if (signal) {
    process.exit(0);
    return;
  }
  process.exit(code ?? 0);
});
