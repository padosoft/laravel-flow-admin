#!/usr/bin/env node
/**
 * Boots Orchestra Testbench's bundled Laravel app via `php vendor/bin/testbench serve`
 * so Playwright can run E2E tests against the package's real route stack
 * (FlowAdminServiceProvider auto-discovered through composer.json `extra.laravel.providers`).
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

const port = process.env.FLOW_ADMIN_E2E_PORT ?? '8001';
const host = process.env.FLOW_ADMIN_E2E_HOST ?? '127.0.0.1';

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
