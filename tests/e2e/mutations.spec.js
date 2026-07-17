import { test, expect } from '@playwright/test';

// E-PR6 mutation actions. The E2E server runs the `array` read adapter with
// the allow-all authorizer, so every action button RENDERS and its click
// fires a real POST through the shared `[data-flow-action]` runner (CSRF
// header, confirm gating, toast feedback). The engine round-trip itself
// (approve→resumed, cancel→aborted, redeliver→requeued) is asserted against a
// real persisted database in the PHPUnit Feature suite — here we prove the
// UI/UX layer: eligibility rendering, the confirm step, and that the fetch
// fires at the right endpoint.

test.describe('flow-admin mutations (E-PR6)', () => {
  test('a pending approval shows Approve/Reject and Approve posts to the approve endpoint', async ({ page }) => {
    await page.goto('/flow/approvals');

    const approve = page.getByTestId('approval-approve').first();
    const reject = page.getByTestId('approval-reject').first();
    await expect(approve).toBeVisible();
    await expect(reject).toBeVisible();

    const [response] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && /\/flow\/approvals\/[^/]+\/approve$/.test(r.url())),
      approve.click(),
    ]);
    expect(response.request().method()).toBe('POST');
  });

  test('rejecting an approval is confirm-gated and posts to the reject endpoint', async ({ page }) => {
    await page.goto('/flow/approvals');

    let confirmed = false;
    page.on('dialog', (dialog) => {
      confirmed = true;
      dialog.accept();
    });

    const [response] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && /\/flow\/approvals\/[^/]+\/reject$/.test(r.url())),
      page.getByTestId('approval-reject').first().click(),
    ]);

    expect(confirmed).toBe(true);
    expect(response.request().method()).toBe('POST');
  });

  test('an active run offers Cancel (confirm-gated), not Replay', async ({ page }) => {
    await page.goto('/flow/runs?status=paused');
    await page.locator('table tbody tr').first().click();
    await expect(page).toHaveURL(/\/flow\/runs\/[^/]+$/);

    await expect(page.getByTestId('run-cancel')).toBeVisible();
    await expect(page.getByTestId('run-replay')).toHaveCount(0);

    page.on('dialog', (dialog) => dialog.accept());

    const [response] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && /\/flow\/runs\/[^/]+\/cancel$/.test(r.url())),
      page.getByTestId('run-cancel').click(),
    ]);
    expect(response.request().method()).toBe('POST');
  });

  test('a terminal run offers Replay, not Cancel', async ({ page }) => {
    await page.goto('/flow/runs?status=success');
    await page.locator('table tbody tr').first().click();
    await expect(page).toHaveURL(/\/flow\/runs\/[^/]+$/);

    await expect(page.getByTestId('run-replay')).toBeVisible();
    await expect(page.getByTestId('run-cancel')).toHaveCount(0);

    const [response] = await Promise.all([
      page.waitForResponse((r) => r.request().method() === 'POST' && /\/flow\/runs\/[^/]+\/replay$/.test(r.url())),
      page.getByTestId('run-replay').click(),
    ]);
    expect(response.request().method()).toBe('POST');
  });

  test('outbox renders an Actions column and gates Redeliver to failed rows only', async ({ page }) => {
    await page.goto('/flow/outbox');

    await expect(page.locator('table thead')).toContainText('Actions');

    // The demo fixture carries no `failed` outbox row (only redeliverable
    // state), so no Redeliver button renders; non-failed rows show the muted
    // placeholder. The button's click behaviour is the same shared runner the
    // approve/reject/cancel/replay tests already exercise, and its engine
    // round-trip is covered by OutboxMutationTest.
    await expect(page.getByTestId('outbox-redeliver')).toHaveCount(0);
    await expect(page.getByTestId('outbox-no-action').first()).toBeVisible();
  });
});
