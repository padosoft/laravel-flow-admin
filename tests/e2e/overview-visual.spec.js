import { test, expect } from '@playwright/test';

/**
 * Macro 3 subtask 3.1 — design-token stylesheet wiring smoke + visual
 * baseline (per-browser, full-page screenshot). The actual PNG diff
 * comparison is gated on a committed baseline; the first CI run that
 * sees a missing baseline produces the artifact under
 * `tests/e2e/__snapshots__/overview-visual.spec.js-snapshots/` and
 * subsequent runs validate. Until baselines are committed (Macro 3.2,
 * generated on the Linux CI runner via `--update-snapshots`), the
 * snapshot assertion is gated behind FLOW_ADMIN_VISUAL_BASELINE=on.
 */
test.describe('flow-admin overview visual baseline', () => {
  test('design-token stylesheet is reachable and applied to /flow', async ({ page, request }) => {
    const styleResponse = await request.get('/_flow-admin/assets/admin.css');
    expect(styleResponse.status(), '/_flow-admin/assets/admin.css must serve 200').toBe(200);
    expect(styleResponse.headers()['content-type']).toContain('text/css');

    const css = await styleResponse.text();
    // A handful of must-have tokens — sanity that the port is intact.
    expect(css).toContain('--font-sans');
    expect(css).toContain('--bg-elevated');
    expect(css).toContain('--radius-md');

    await page.goto('/flow');
    await expect(page.locator('html')).toHaveAttribute('data-theme', /dark|light/);
    await expect(page.locator('[data-testid="flow-admin-overview-shell"]')).toBeVisible();
    await page.waitForLoadState('networkidle');
  });

  test('GET /flow matches the visual baseline (gated)', async ({ page }) => {
    test.skip(
      process.env.FLOW_ADMIN_VISUAL_BASELINE !== 'on',
      'Visual baseline gated until tests/e2e/__snapshots__ is seeded on the Linux CI runner — set FLOW_ADMIN_VISUAL_BASELINE=on to enforce.',
    );

    await page.goto('/flow');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveScreenshot('overview-3.1-skeleton.png', {
      fullPage: true,
      maxDiffPixelRatio: 0.02,
    });
  });
});
