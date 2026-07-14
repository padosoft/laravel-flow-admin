import { test, expect } from '@playwright/test';

test.describe('flow-admin studio shell (E-PR1 React island pipeline)', () => {
  test('GET /flow/studio loads the built React + @xyflow/react bundle and mounts the canvas', async ({ page }) => {
    const response = await page.goto('/flow/studio');

    expect(response, 'GET /flow/studio returned a response').not.toBeNull();
    expect(response?.status(), 'GET /flow/studio returns 200').toBe(200);
    await expect(page.locator('h1')).toContainText('Studio');

    // The mount point is server-rendered; its content is not — proving it
    // renders proves the built JS bundle (resources/js/studio.jsx, served
    // via flow-admin.assets.studio-js) actually executed in the browser.
    const root = page.locator('#flow-studio-root');
    await expect(root).toBeVisible();

    const canvas = page.getByTestId('flow-studio-canvas');
    await expect(canvas).toBeVisible();

    // `.react-flow` is @xyflow/react's own root wrapper class — its
    // presence proves the library initialized, not just a placeholder div.
    await expect(canvas.locator('.react-flow')).toBeVisible();
  });
});
