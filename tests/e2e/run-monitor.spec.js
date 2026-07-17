import { test, expect } from '@playwright/test';

// A minimal stand-in for Laravel Echo: records `.listen()` callbacks per
// channel and lets the test fire an event into them via window.__fireFlow.
// The E2E serve reports broadcasting as ENABLED (testbench.yaml), so the
// monitor enters live mode whenever this shim is present.
const FAKE_ECHO = `
window.Echo = {
  channels: {},
  private(name) {
    const channel = (this.channels[name] = this.channels[name] || { handlers: {} });
    return {
      listen(event, cb) { channel.handlers[event] = cb; return this; },
      stopListening(event) { delete channel.handlers[event]; return this; },
    };
  },
  leave() {},
};
window.__fireFlow = (channel, event, payload) => {
  const c = window.Echo.channels[channel];
  if (c && c.handlers[event]) c.handlers[event](payload);
};
`;

async function gotoFirstRunMonitor(page) {
  await page.goto('/flow/runs');
  await page.locator('table tbody tr').first().click();
  await expect(page).toHaveURL(/\/flow\/runs\/[^/]+$/);
  await page.getByTestId('run-monitor-link').click();
  await expect(page.getByTestId('flow-monitor-root')).toBeVisible();
  await expect(page.getByTestId('flow-monitor')).toBeVisible();
}

test.describe('flow-admin live run monitor (E-PR5)', () => {
  test('polling fallback: node states render without an Echo client', async ({ page }) => {
    await gotoFirstRunMonitor(page);

    // Broadcasting is enabled server-side, but with no window.Echo the monitor
    // must fall back to polling and still render the run's node states.
    await expect(page.getByTestId('flow-monitor')).toHaveAttribute('data-mode', 'polling');
    await expect(page.getByTestId('monitor-mode')).toHaveText('polling');
    await expect(page.getByTestId('monitor-progress')).toBeVisible();
    await expect(page.locator('[data-testid^="monitor-node-state-"]').first()).toBeVisible();
  });

  test('broadcast stub: a node lights up on a node.transitioned event', async ({ page }) => {
    await page.addInitScript(FAKE_ECHO);
    await gotoFirstRunMonitor(page);

    // With the Echo shim present and broadcasting enabled, the monitor is live.
    await expect(page.getByTestId('flow-monitor')).toHaveAttribute('data-mode', 'live');

    const channel = await page.getByTestId('flow-monitor-root').getAttribute('data-channel');
    const firstNodeTestId = await page.locator('[data-testid^="monitor-node-state-"]').first().getAttribute('data-testid');
    const nodeId = firstNodeTestId.replace('monitor-node-state-', '');

    // Simulate a broadcast: the node transitions to "running" and the pill
    // must visibly update to that state.
    await page.evaluate(
      ({ channel, nodeId }) => {
        window.__fireFlow(channel, '.node.transitioned', {
          run_id: 'x', node_id: nodeId, node_type: 't', state: 'running', sequence: 1, occurred_at: '',
        });
      },
      { channel, nodeId },
    );

    await expect(page.getByTestId(`monitor-node-state-${nodeId}`)).toHaveText('Running');
  });
});
