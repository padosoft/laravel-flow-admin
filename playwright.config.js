import { defineConfig, devices } from '@playwright/test';

const PORT = process.env.FLOW_ADMIN_E2E_PORT ?? '8001';
const HOST = process.env.FLOW_ADMIN_E2E_HOST ?? '127.0.0.1';
const BASE_URL = process.env.FLOW_ADMIN_E2E_BASE_URL ?? `http://${HOST}:${PORT}`;

// Build the healthcheck URL via `new URL` so a BASE_URL with a trailing slash
// or an inline path (`http://localhost:8001/admin/`) produces a single
// well-formed `…/flow`, never `//flow` or `//admin/flow`. Both shapes would
// have the Playwright webServer poll fail silently until the 120s timeout.
const HEALTH_URL = new URL('/flow', BASE_URL).toString();

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
  },
});
