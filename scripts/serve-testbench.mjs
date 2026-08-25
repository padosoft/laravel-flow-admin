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
  // by default, so one slow request blocks every concurrent request. The
  // previous answer was PHP_CLI_SERVER_WORKERS=4, which pre-forks N workers —
  // but that is PHP's EXPERIMENTAL forking built-in server, and it does not
  // merely risk a crash, it takes one: a forked worker segfaults under load and
  // the request it was serving returns EMPTY. The browser reports
  // `net::ERR_EMPTY_RESPONSE`, Playwright reports whatever assertion was
  // waiting, and the failure moves between browsers run to run. Nothing
  // downstream can recover a response that was never written, so the
  // supervisor below (which restarts a dead MASTER) cannot help.
  //
  // So: remove the concurrency instead of surviving it. The dominant load was
  // the layout's 4-second `/flow/api/live` heartbeat firing on every page of
  // every shard, and no test needs it — testbench.yaml now pushes
  // FLOW_ADMIN_POLLING_MS past any run's lifetime. What remains is one
  // navigation at a time plus the run-monitor's own 2.5s poll during two
  // specs, which a single worker serialises in milliseconds.
  //
  // That is the trade: a single worker BLOCKS (bounded, deterministic, and
  // invisible at these durations) where the forking worker CRASHED (unbounded,
  // nondeterministic, and fatal to the request). If a slow endpoint ever does
  // stall a shard, the answer is a real server — FrankenPHP or php-fpm — not
  // the experimental code path.
  //
  // Still overridable, so a bisect can put the old behaviour back in one env
  // var: `PHP_CLI_SERVER_WORKERS=4 npm run e2e`.
  PHP_CLI_SERVER_WORKERS: process.env.PHP_CLI_SERVER_WORKERS ?? '1',
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

// `--no-reload` is kept for two reasons. It is what makes PHP_CLI_SERVER_WORKERS
// take effect at all (ServeCommand::initialize() silently falls back to a single
// worker, warning only, unless it is passed and we are not under Sail), so an
// override back to the forked mode still behaves as advertised; and the
// dev-server's restart-on-.env-change is meaningless for a short-lived CI
// server, so disabling it costs nothing either way.
//
// SUPERVISOR (crash resilience). This was written as "the definitive fix for
// the residual E2E flake" while the forked worker mode was the default, and it
// is worth being precise about what it can and cannot do:
//
//   - It CAN recover a dead MASTER. When the `php -S` process dies, `artisan
//     serve` does not restart it (ServeCommand's loop only restarts on a `.env`
//     change), so the port stays unbound and every later request gets
//     `NS_ERROR_CONNECTION_REFUSED` — the first test eats its 30s timeout and
//     the shard cascades. Respawning turns that into a sub-second port-rebind
//     blip Playwright's retries absorb.
//   - It CANNOT recover a dead WORKER. A forked worker that segfaults mid-
//     request leaves that connection closed with nothing written, which reaches
//     the browser as `net::ERR_EMPTY_RESPONSE`. The master is still alive, so
//     there is nothing to respawn and nothing to retry — the response was never
//     going to exist. That is the failure this supervisor was credited with
//     fixing and did not, and it is why the worker default is now 1 (see the
//     env block above).
//
// The supervisor stays: a master can still die for reasons that have nothing to
// do with worker mode, and anyone overriding PHP_CLI_SERVER_WORKERS back to 4
// wants it. Migration + WAL ran once above against a persistent DB file and the
// demo ReadModel is stateless, so a respawn preserves all test state.
let shuttingDown = false;
let restarts = 0;
let rapidDeaths = 0;
let lastStartAt = 0;
let handlingFailure = false;
// Backstop against a genuine crash-loop: give up rather than respawn forever.
const maxRestarts = Number(process.env.FLOW_ADMIN_E2E_MAX_RESTARTS ?? '100');
// A server that dies within this window of starting is almost certainly a
// deterministic boot-time fatal (missing extension, bad flag, port permanently
// taken), NOT a load-induced segfault — bail fast instead of burning the whole
// count budget on doomed relaunches.
const rapidDeathMs = 2000;
const maxRapidDeaths = 5;

function startServer() {
  lastStartAt = Date.now();
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
  // `detached: true` makes the `php` master a process-GROUP leader so we can reap
  // the WHOLE group — master + its PHP_CLI_SERVER_WORKERS forked workers — via a
  // negative-pid signal, even after the master itself segfaults. A dead master
  // never reaps its forked children: they get reparented to init and (with the
  // worker server's inherited/`SO_REUSEPORT` listening socket) can keep the port
  // bound, which would make the respawn hit EADDRINUSE and hop to a different
  // port than Playwright polls. Reaping the group first prevents that.
  return spawn(
    'php',
    [testbench, 'serve', `--host=${host}`, `--port=${port}`, '--no-reload'],
    { cwd: repoRoot, stdio: 'inherit', env, detached: true },
  );
}

// Kill the whole tree, not just the tracked handle: on POSIX the negative pid
// targets the process group (see `detached` above); on Windows `child` is a
// `cmd.exe` wrapper whose `php.exe` grandchild survives a bare `child.kill()`,
// so use `taskkill /T` to walk the tree. Swallows ESRCH ("already gone").
function killTree(proc, signal) {
  if (!proc || proc.pid === undefined) {
    return;
  }
  try {
    if (process.platform === 'win32') {
      spawnSync('taskkill', ['/pid', String(proc.pid), '/T', '/F'], { stdio: 'ignore' });
    } else {
      process.kill(-proc.pid, signal);
    }
  } catch {
    // Process/group already exited — nothing to reap.
  }
}

// Both `exit` (process died) and `error` (spawn failed — EAGAIN/ENOENT, or
// cmd.exe failing to launch) must drive the supervisor; wiring only `exit`
// would let a spawn failure hang silently until Playwright's webServer poll
// times out with no explanation.
function supervise(proc) {
  proc.on('exit', (code, signal) => handleFailure(`exit code=${code} signal=${signal ?? 'none'}`));
  proc.on('error', (err) => handleFailure(`spawn error ${err.code ?? err.message}`));
  return proc;
}

let child = supervise(startServer());

const forward = (signal) => () => {
  shuttingDown = true;
  killTree(child, signal);
  // Exit directly rather than waiting for a child `'exit'` event: if the signal
  // lands DURING the respawn backoff, `child` is already dead (killTree throws
  // ESRCH and is swallowed) so no `'exit'` would ever fire, and the pending
  // respawn timer would just `return` on `shuttingDown` — leaving the supervisor
  // hung forever with the signal handlers keeping the event loop alive. The
  // `process.on('exit')` reap below still SIGKILLs whatever group is current.
  process.exit(0);
};
process.on('SIGINT', forward('SIGINT'));
process.on('SIGTERM', forward('SIGTERM'));
// Last-ditch reap on ANY exit path (give-up, clean shutdown) so a detached
// group can't outlive the supervisor. Sync-only work — safe in an exit handler.
process.on('exit', () => killTree(child, 'SIGKILL'));

function handleFailure(reason) {
  // Our own teardown (Playwright stopping the webServer): exit cleanly.
  if (shuttingDown) {
    process.exit(0);
    return;
  }
  // `exit` and `error` can both fire for one death — act on it once.
  if (handlingFailure) {
    return;
  }
  handlingFailure = true;

  // Reap any workers the dead master left behind BEFORE rebinding the port.
  killTree(child, 'SIGKILL');

  const uptime = Date.now() - lastStartAt;
  rapidDeaths = uptime < rapidDeathMs ? rapidDeaths + 1 : 0;
  restarts += 1;

  if (restarts >= maxRestarts || rapidDeaths >= maxRapidDeaths) {
    const why =
      rapidDeaths >= maxRapidDeaths
        ? `${rapidDeaths} back-to-back deaths within ${rapidDeathMs}ms of starting (a boot-time fatal, not a transient segfault)`
        : `the ${maxRestarts}-restart backstop`;
    console.error(
      `[serve-testbench] serve process failed (${reason}); giving up — hit ${why}.`,
    );
    process.exit(1);
    return;
  }

  console.error(
    `[serve-testbench] serve process failed (${reason}); respawning (restart #${restarts}) ` +
      "so Playwright never sees a dead port. Likely a silent segfault of PHP's " +
      'experimental multi-worker built-in server.',
  );

  // Brief pause so the listening socket is fully released before rebinding the
  // SAME port. `php -S` sets SO_REUSEADDR so immediate rebind usually works, but
  // a short wait (plus the group reap above) avoids the EADDRINUSE that would
  // make `artisan serve` hop to another port (canTryAnotherPort) and desync from
  // the URL Playwright polls.
  setTimeout(() => {
    if (shuttingDown) {
      return;
    }
    handlingFailure = false;
    child = supervise(startServer());
  }, 500);
}
