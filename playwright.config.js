import { defineConfig, devices } from '@playwright/test';

const PORT = process.env.FLOW_ADMIN_E2E_PORT ?? '8001';
const HOST = process.env.FLOW_ADMIN_E2E_HOST ?? '127.0.0.1';
const BASE_URL = process.env.FLOW_ADMIN_E2E_BASE_URL ?? `http://${HOST}:${PORT}`;

// Build the healthcheck URL via `new URL` so a BASE_URL with a trailing slash
// (`http://localhost:8001/`) or an inline path (`http://localhost:8001/admin/`)
// produces a single well-formed `…/flow` instead of the malformed `…//flow`
// or `…/admin//flow` that string concatenation would yield. Both malformed
// shapes silently hang the Playwright webServer poll until the 120s timeout.
//
// `new URL` throws on a non-absolute base (e.g. `FLOW_ADMIN_E2E_BASE_URL=foo`
// with no scheme), which would otherwise prevent the config from loading at
// all. Mirror the recovery path in `scripts/serve-testbench.mjs`: log and
// fall back to the assembled-from-host/port form.
function buildHealthUrl(base) {
  try {
    return new URL('/flow', base).toString();
  } catch (error) {
    const fallback = `http://${HOST}:${PORT}/flow`;
    console.error(
      `[playwright.config] FLOW_ADMIN_E2E_BASE_URL=${base} is not a valid URL; ` +
        `falling back to ${fallback}.`,
      error,
    );
    return fallback;
  }
}
const HEALTH_URL = buildHealthUrl(BASE_URL);

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'off',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
  ],
  webServer: {
    command: 'node scripts/serve-testbench.mjs',
    url: HEALTH_URL,
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
    stdout: 'pipe',
    stderr: 'pipe',
    // POSIX only: send a TRAPPABLE SIGTERM on teardown instead of Playwright's
    // default process-group SIGKILL. The supervisor (scripts/serve-testbench.mjs)
    // spawns the POSIX serve process `detached` (its own process group) so it can
    // reap the master + its forked PHP_CLI_SERVER_WORKERS workers after a crash;
    // that same detaching means a group SIGKILL aimed at the Node supervisor would
    // NOT reach the server, leaving it orphaned on the port (and reused next run
    // via reuseExistingServer). A SIGTERM lets the supervisor's handler run and
    // tree-kill the detached group before exiting. On Windows the server is NOT
    // detached and Playwright's native tree teardown already reaps it cleanly, so
    // we leave the default there rather than risk a signal-mapping regression.
    ...(process.platform === 'win32'
      ? {}
      : { gracefulShutdown: { signal: 'SIGTERM', timeout: 10_000 } }),
  },
});
