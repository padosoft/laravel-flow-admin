import { test, expect } from '@playwright/test';

/**
 * Macro 3.2 — theme toggle persists across navigations via the
 * `flow_admin_theme` cookie. This spec exercises the full round-trip:
 * default render → click toggle button → cookie set → reload → still
 * the new theme.
 */
test.describe('flow-admin theme toggle', () => {
  test('default render is dark + toggle switches to light + persists across reload', async ({ page, context }) => {
    // Start clean: no theme cookie carried in from previous specs.
    await context.clearCookies();

    await page.goto('/flow');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

    const toggle = page.locator('[data-testid="flow-admin-theme-toggle"]');
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('data-current-theme', 'dark');

    // Click and wait for the navigation that the POST → 302 triggers.
    await Promise.all([
      page.waitForURL(/\/flow$/),
      toggle.click(),
    ]);

    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');

    const cookies = await context.cookies();
    const themeCookie = cookies.find((c) => c.name === 'flow_admin_theme');
    expect(themeCookie?.value, 'flow_admin_theme cookie must be persisted by the POST').toBe('light');

    // Reload and verify the theme survives a fresh navigation.
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  });

  test('sidebar + topbar render with the design-system structure', async ({ page, context }) => {
    await context.clearCookies();
    await page.goto('/flow');

    await expect(page.locator('[data-testid="flow-admin-sidebar"]')).toBeVisible();
    await expect(page.locator('[data-testid="flow-admin-topbar"]')).toBeVisible();
    await expect(page.locator('[data-testid="flow-admin-breadcrumbs"]')).toBeVisible();
    await expect(page.locator('[data-testid="flow-admin-overview-page"]')).toBeVisible();

    // The icon component must inline an SVG with the matching data-icon
    // attribute — drift in the icon resolver should be a test failure
    // long before it reaches a code review.
    await expect(page.locator('[data-icon="home"]').first()).toBeVisible();
  });
});
