import { test, expect } from '@playwright/test';

test.describe('flow-admin macro8 runtime', () => {
  test('command palette opens with Ctrl+K and shows results', async ({ page }) => {
    await page.goto('/flow');

    await page.keyboard.press('Control+k');
    await expect(page.locator('#flow-cmdk')).toBeVisible();

    await page.locator('#flow-cmdk-input').fill('run');
    await expect(page.locator('#flow-cmdk-list .palette-item').first()).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(page.locator('#flow-cmdk')).toBeHidden();
  });

  test('live toggle pauses and resumes polling control', async ({ page }) => {
    await page.goto('/flow');
    const toggle = page.locator('#flow-live-toggle');
    await expect(toggle).toBeVisible();

    await toggle.click();
    await expect(page.locator('#flow-toast-stack .toast').last()).toContainText('Auto-refresh paused');

    await toggle.click();
    await expect(page.locator('#flow-toast-stack .toast').last()).toContainText('Auto-refresh resumed');
  });
});
