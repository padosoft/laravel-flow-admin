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
 * tables into a fresh, persistent SQLite file — the testbench skeleton's OWN
 * `database_path('database.sqlite')` (recreated on every run). A real file,
 * not the `testing` connection's `:memory:`, is required so the Studio
 * editor's "save as draft" E2E scenario (E-PR3) actually persists across the
 * served app's per-request work; `Contracts\DefinitionRepository` is core's
 * own binding and is NOT swappable via `FLOW_ADMIN_ADAPTER` the way
 * `ReadModel` is. The served app does NOT receive `DB_DATABASE` from this
 * script's process env — `artisan serve` runs the `php -S` worker with CWD =
 * the skeleton's `public_path()` and forwards only a passthrough env
 * allowlist, so DB_* is dropped. Instead `testbench.yaml` forces
 * `DB_CONNECTION: sqlite` (a channel the served app DOES read), and both this
 * migrate step and the served app fall back to the SAME absolute
 * `database_path('database.sqlite')` because both boot the `@testbench`
 * skeleton. See the `e2eDatabasePath` comment below for the full rationale.
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

// A real, persistent SQLite file (not `:memory:`, which the `testing`
// connection uses and which cannot hold the migrated tables across the
// served app's requests) so E-PR3's Studio editor "save as draft" flow has
// a real `flow_definitions` table to write into during Playwright E2E runs.
//
// CRITICAL — this MUST be the testbench skeleton's OWN `database_path()`:
// `testbench serve` runs `artisan serve`, which spawns the `php -S` worker
// with its CWD set to `public_path()` of the skeleton (Laravel's
// ServeCommand::startProcess: `new Process(cmd, public_path(), …)`) and does
// NOT pass our `DB_DATABASE` env through to that worker (ServeCommand drops
// every non-passthrough var). So a relative `DB_DATABASE` resolves against
// the skeleton's dirs, not repo root, and an absolute one we set in the
// process env never reaches the served app at all — the app instead falls
// back to `env('DB_DATABASE', database_path('database.sqlite'))`. By writing
// and migrating THIS exact file, both the migration process and the served
// app (both boot the `@testbench` skeleton, so both compute the identical
// absolute `database_path('database.sqlite')`) target the same file with no
// relative-path, CWD, or env-inheritance dependency. Verified:
// base_path() = vendor/orchestra/testbench-core/laravel. Recreated fresh on
// every run for a clean E2E state.
const e2eDatabasePath = resolve(
  repoRoot,
  'vendor/orchestra/testbench-core/laravel/database/database.sqlite',
);

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
  // `testbench serve` → `artisan serve` runs a SINGLE-threaded `php -S` worker
  // by default, so one slow request (e.g. the ~500ms AI advisor scan) blocks
  // every concurrent request — asset loads, the `/flow/api/live` poll — and can
  // stall a browser shard. PHP_CLI_SERVER_WORKERS pre-forks N workers so the
  // built-in server handles requests concurrently. POSIX only (Linux/macOS CI);
  // PHP ignores it on Windows, so local Windows runs are single-worker.
  // Overridable; default 4. TWO things are required for this to be SAFE:
  //   (1) `--no-reload` on the serve command below, or ServeCommand silently
  //       falls back to a single worker (warning only); and
  //   (2) the crash SUPERVISOR below — this worker mode is EXPERIMENTAL and
  //       segfaults silently under load, and a crash otherwise leaves the port
  //       dead forever (artisan serve never restarts a crashed server).
  PHP_CLI_SERVER_WORKERS: process.env.PHP_CLI_SERVER_WORKERS ?? '4',
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

// Put the freshly migrated file into WAL journal mode BEFORE the server
// starts, so the Studio "save as draft" write can't collide with the
// concurrent `/flow/api/live` poll read on SQLite's default whole-file
// journal lock (which failed fast — "database is locked" — under CI's PHP
// built-in server, surfacing as an intermittent `500 Could not save the
// draft`). WAL is persisted in the file header, so every per-request
// connection the served app opens inherits it. See scripts/enable-wal.php.
const walScript = resolve(here, 'enable-wal.php');
const wal =
  process.platform === 'win32'
    ? spawnSync(
        'cmd.exe',
        ['/d', '/s', '/c', `php "${walScript}" "${e2eDatabasePath}"`],
        { cwd: repoRoot, stdio: 'inherit', env, windowsVerbatimArguments: true },
      )
    : spawnSync('php', [walScript, e2eDatabasePath], { cwd: repoRoot, stdio: 'inherit', env });

if (wal.status !== 0) {
  console.error(
    `[serve-testbench] Enabling WAL journal mode on ${e2eDatabasePath} failed ` +
      `(exit ${wal.status}). Aborting: the save-as-draft E2E scenario is prone to ` +
      'intermittent SQLite "database is locked" 500s without it.',
  );
  process.exit(wal.status ?? 1);
}

// `--no-reload` is REQUIRED for PHP_CLI_SERVER_WORKERS to take effect: Laravel's
// ServeCommand::initialize() refuses to honour the worker count (falls back to a
// single worker with only a warning) unless `--no-reload` is passed and it isn't
// under Sail. The dev-server's restart-on-.env-change is irrelevant for a
// short-lived CI/E2E server, so disabling it is free here.
//
// SUPERVISOR (crash resilience) — the definitive fix for the residual E2E
// flake. `PHP_CLI_SERVER_WORKERS` runs PHP's EXPERIMENTAL forking built-in
// server, which segfaults SILENTLY under the E2E load (constant `/flow/api/live`
// polling + the run-monitor 2.5s poll + Firefox aborting in-flight requests as
// it navigates between tests). When that `php -S` process dies, `artisan serve`
// does NOT restart it — ServeCommand's loop only restarts on a `.env` change, so
// on a crash it just exits and the port stays unbound. Every later request then
// gets `NS_ERROR_CONNECTION_REFUSED`: the first test to hit the dead server eats
// its full 30s timeout, then the whole browser shard cascades (this was the
// recurring "a different single browser flakes each run" CI symptom — verified
// from a Firefox shard log: fast 0.04ms responses until the server went silent,
// then 26s of nothing, then CONNECTION_REFUSED on every retry, with no PHP error
// = a silent segfault). We supervise the serve process and respawn it on any
// UNEXPECTED exit, so a crash becomes a sub-second port-rebind blip that
// Playwright's own per-test retries absorb instead of a fatal shard-wide
// cascade. Migration + WAL ran once above against a persistent DB file and the
// demo ReadModel is stateless, so a respawn preserves all test state.
let shuttingDown = false;
let restarts = 0;
// Backstop against a genuine crash-loop (e.g. the port is permanently taken, or
// a boot-time fatal): give up rather than respawn forever.
const maxRestarts = Number(process.env.FLOW_ADMIN_E2E_MAX_RESTARTS ?? '100');

function startServer() {
  if (process.platform === 'win32') {
    // Quote the testbench path so spaces (e.g. "Visual Basic") survive.
    const cmdLine = `php "${testbench}" serve --host=${host} --port=${port} --no-reload`;
    return spawn('cmd.exe', ['/d', '/s', '/c', cmdLine], {
      cwd: repoRoot,
      stdio: 'inherit',
      env,
      windowsVerbatimArguments: true,
    });
  }
  return spawn(
    'php',
    [testbench, 'serve', `--host=${host}`, `--port=${port}`, '--no-reload'],
    { cwd: repoRoot, stdio: 'inherit', env },
  );
}

let child = startServer();

const forward = (signal) => () => {
  shuttingDown = true;
  if (!child.killed) {
    child.kill(signal);
  }
};
process.on('SIGINT', forward('SIGINT'));
process.on('SIGTERM', forward('SIGTERM'));

function handleExit(code, signal) {
  // Our own teardown (Playwright stopping the webServer): exit cleanly.
  if (shuttingDown) {
    process.exit(0);
    return;
  }

  if (restarts >= maxRestarts) {
    console.error(
      `[serve-testbench] serve process exited unexpectedly (code=${code} signal=${signal}) ` +
        `and hit the ${maxRestarts}-restart backstop — giving up instead of respawning forever.`,
    );
    process.exit(code ?? 1);
    return;
  }

  restarts += 1;
  console.error(
    `[serve-testbench] serve process exited unexpectedly (code=${code} signal=${signal}); ` +
      `respawning (restart #${restarts}) so Playwright never sees a dead port. ` +
      "Likely a silent segfault of PHP's experimental multi-worker built-in server.",
  );

  // Brief pause so the listening socket is fully released before rebinding the
  // SAME port. `php -S` sets SO_REUSEADDR so immediate rebind usually works, but
  // a short wait avoids a rare EADDRINUSE that would make `artisan serve` hop to
  // another port (canTryAnotherPort) and desync from the URL Playwright polls.
  setTimeout(() => {
    if (shuttingDown) {
      return;
    }
    child = startServer();
    child.on('exit', handleExit);
  }, 500);
}

child.on('exit', handleExit);
