import { test, expect } from '@playwright/test';

// Saves the currently-loaded editor graph as a new draft and returns the
// version number parsed from the success banner ("Draft version N saved.").
async function saveDraft(page) {
  await expect(page.getByTestId('studio-save-button')).toBeEnabled();
  await page.getByTestId('studio-save-button').click();

  const success = page.getByTestId('studio-save-success');
  await expect(success).toBeVisible();

  const text = (await success.textContent()) ?? '';
  const match = text.match(/version\s+(\d+)/i);
  expect(match, `expected a version number in "${text}"`).not.toBeNull();

  return Number(match[1]);
}

test.describe('flow-admin studio versioning (E-PR4)', () => {
  test('the read-only canvas links into the versions page', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow');
    await page.getByTestId('studio-versions-link').click();

    await expect(page).toHaveURL(/\/flow\/studio\/OrderCheckoutFlow\/versions$/);
    await expect(page.getByTestId('flow-studio-versions')).toBeVisible();
  });

  test('the versions page lists a saved draft with its status', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    const version = await saveDraft(page);

    await page.goto('/flow/studio/OrderCheckoutFlow/versions');
    await expect(page.getByTestId('flow-studio-versions')).toBeVisible();

    const row = page.getByTestId(`version-row-${version}`);
    await expect(row).toBeVisible();
    await expect(page.getByTestId(`version-status-${version}`)).toHaveText('draft');
  });

  test('publishing a draft goes through the immutability modal and marks it published', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    const version = await saveDraft(page);

    await page.goto('/flow/studio/OrderCheckoutFlow/versions');
    await expect(page.getByTestId(`version-status-${version}`)).toHaveText('draft');

    await page.getByTestId(`publish-btn-${version}`).click();

    const modal = page.getByTestId('publish-modal');
    await expect(modal).toBeVisible();
    await expect(modal).toContainText('immutable');

    await page.getByTestId('publish-confirm-btn').click();

    await expect(page.getByTestId('publish-success')).toBeVisible();
    // The row's status flips to published and its publish button disappears.
    await expect(page.getByTestId(`version-status-${version}`)).toHaveText('published');
    await expect(page.getByTestId(`publish-btn-${version}`)).toHaveCount(0);
  });

  test('comparing two versions renders a changed-node diff on the canvas', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    const from = await saveDraft(page);

    // Flip one node's config so the next version differs by exactly one
    // CHANGED node — no structural change, so the graph stays valid and this
    // works cross-browser (unlike a palette drag, which is chromium-only).
    // react-flow occasionally drops a single node-selection click, so retry
    // until the inspector populates (same pattern as the editor delete test).
    await expect(async () => {
      await page.getByTestId('studio-node-charge').click({ force: true });
      await expect(page.getByTestId('config-authorized')).toBeVisible({ timeout: 1000 });
    }).toPass({ timeout: 15000 });

    const authorized = page.getByTestId('config-authorized');
    const before = await authorized.isChecked();
    await authorized.setChecked(!before);
    const to = await saveDraft(page);

    await page.goto('/flow/studio/OrderCheckoutFlow/versions');
    await expect(page.getByTestId('flow-studio-versions')).toBeVisible();

    await page.getByTestId('diff-from-select').selectOption(String(from));
    await page.getByTestId('diff-to-select').selectOption(String(to));
    await page.getByTestId('diff-compare-btn').click();

    await expect(page.getByTestId('diff-summary')).toBeVisible();
    await expect(page.getByTestId('diff-changed-count')).toContainText('1');
    await expect(page.getByTestId('diff-added-count')).toContainText('0');
    await expect(page.getByTestId('diff-removed-count')).toContainText('0');
    // The diff canvas renders the union graph — all 4 unchanged/changed nodes.
    await expect(page.getByTestId('diff-canvas').locator('.react-flow__node')).toHaveCount(4);
    // Exactly one node carries the "changed" diff class the overlay colors by.
    await expect(page.getByTestId('diff-canvas').locator('.react-flow__node.diff-node-changed')).toHaveCount(1);
  });
});
