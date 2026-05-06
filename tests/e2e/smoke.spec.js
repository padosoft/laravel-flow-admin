import { test, expect } from '@playwright/test';

test.describe('flow-admin baseline smoke', () => {
  test('GET /flow renders the overview stub', async ({ page }) => {
    const response = await page.goto('/flow');

    expect(response, 'GET /flow returned a response').not.toBeNull();
    expect(response?.status(), 'GET /flow returns 200').toBe(200);
    expect(page.url()).toContain('/flow');
    await expect(page.locator('h1')).toContainText('Overview');
  });
});
