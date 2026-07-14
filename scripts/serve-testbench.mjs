#!/usr/bin/env node
/**
 * Boots Orchestra Testbench's bundled Laravel app via `php vendor/bin/testbench serve`
 * so Playwright can run E2E tests against the package's real route stack.
 *
 * Provider registration: `vendor/bin/testbench serve` does NOT honour the
 * `extra.laravel.providers` block in `composer.json` (that block is consumed
 * only by Laravel's package-discovery in a *consumer* app). Inside the package
 * itself, providers are registered through `testbench.yaml`'s `providers:`
 * list, which Testbench reads when `vendor/bin/testbench serve` boots.
 *
 * `testbench.yaml`'s `env:` block is the most reliable channel for Laravel's
 * `env()` helper to see overrides under Testbench: it is applied after the
 * bundled `vendor/orchestra/testbench-core/laravel/.env` Dotenv load, so it
 * always wins over the pre-set defaults there. We also pass
 * `FLOW_ADMIN_MIDDLEWARE=web`, `FLOW_ADMIN_ADAPTER=array`, and
 * `FLOW_ADMIN_AUTHORIZER=allow` on the spawned PHP process environment below
 * for belt-and-suspenders.
 *
 * Database: before serving, this script migrates core's (`padosoft/laravel-flow`)
 * tables into a fresh, persistent SQLite file (`storage/testing/flow-admin-e2e.sqlite`,
 * recreated on every run) and points `DB_DATABASE` at it for BOTH the migrate
 * step and the served app. A real file, not `:memory:`, is required: PHP's
 * built-in dev server spawns a fresh process per request, which would wipe
 * an in-memory database between every HTTP request. This is what lets the
 * Studio editor's "save as draft" E2E scenario (E-PR3) actually persist —
 * `Contracts\DefinitionRepository` is core's own binding and is NOT
 * swappable via `FLOW_ADMIN_ADAPTER` the way `ReadModel` is.
 *
 * Cross-platform launcher:
 *   - POSIX: `spawn('php', ['vendor/bin/testbench', 'serve', …])`.
 *   - Windows: `spawn('cmd.exe', ['/c', 'php "vendor/bin/testbench" serve …'])`.
 *     cmd.exe handles PATH/PATHEXT lookup for `php` and tolerates spaces in
 *     the repository path (which Node's bare spawn cannot).
 */
import { spawn, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const repoRoot = resolve(here, '..');
const testbench = resolve(repoRoot, 'vendor/bin/testbench');

// A real, persistent SQLite file (not `:memory:`, which PHP's built-in
// dev server — a fresh process per request — would wipe between every
// HTTP request) so E-PR3's Studio editor "save as draft" flow has a real
// `flow_definitions` table to write into during Playwright E2E runs.
// `storage/` is git-ignored package-wide; recreated fresh on every run
// for a clean E2E state, same spirit as the `array` ReadModel adapter's
// deterministic seed-42 fixtures for reads.
const e2eDatabasePath = resolve(repoRoot, 'storage/testing/flow-admin-e2e.sqlite');

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
  FLOW_ADMIN_AUTHORIZER: process.env.FLOW_ADMIN_AUTHORIZER ?? 'allow',
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: e2eDatabasePath,
};

mkdirSync(dirname(e2eDatabasePath), { recursive: true });
rmSync(e2eDatabasePath, { force: true });
writeFileSync(e2eDatabasePath, '');

const migrationArgs = [
  'migrate',
  '--path=vendor/padosoft/laravel-flow/database/migrations',
  '--realpath',
  '--force',
];

const migration =
  process.platform === 'win32'
    ? spawnSync(
        'cmd.exe',
        ['/d', '/s', '/c', `php "${testbench}" ${migrationArgs.join(' ')}`],
        { cwd: repoRoot, stdio: 'inherit', env, windowsVerbatimArguments: true },
      )
    : spawnSync('php', [testbench, ...migrationArgs], { cwd: repoRoot, stdio: 'inherit', env });

if (migration.status !== 0) {
  console.error(
    `[serve-testbench] Migrating core's tables into ${e2eDatabasePath} failed ` +
      `(exit ${migration.status}) — the Studio editor's save-as-draft E2E scenario ` +
      'needs a real flow_definitions table. Aborting before starting the server.',
  );
  process.exit(migration.status ?? 1);
}

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
