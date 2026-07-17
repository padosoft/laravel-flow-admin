import { test, expect } from '@playwright/test';

test.describe('flow-admin Advisor inbox (E-PR8b)', () => {
  test('the Advisor page exposes a scan action reachable from the sidebar', async ({ page }) => {
    const response = await page.goto('/flow/advisor');
    expect(response?.status()).toBe(200);

    await expect(page.getByTestId('flow-advisor-page')).toBeVisible();
    await expect(page.getByTestId('advisor-scan-button')).toBeVisible();
    // Nothing has been scanned yet — the initial invitation is shown.
    await expect(page.getByTestId('advisor-initial')).toBeVisible();

    // The sidebar links here (active state).
    await expect(page.locator('[data-route-key="advisor"].active')).toBeVisible();
  });

  test('scanning completes the round-trip and renders a result state without error', async ({ page }) => {
    await page.goto('/flow/advisor');
    await expect(page.getByTestId('advisor-scan-button')).toBeVisible();

    await page.getByTestId('advisor-scan-button').click();

    // The served E2E app (array adapter) has no persisted run history, so the
    // deterministic outcome is the empty state — the point is that the gated
    // POST /advisor/scan round-trips successfully (AllowAllAuthorizer in
    // testbench.yaml) and the UI resolves to a terminal, non-error state.
    await expect(page.getByTestId('advisor-empty')).toBeVisible();
    await expect(page.getByTestId('advisor-error')).toBeHidden();
    await expect(page.getByTestId('advisor-loading')).toBeHidden();
  });
});
