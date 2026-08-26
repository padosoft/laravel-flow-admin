import { test, expect } from '@playwright/test';

async function dragConnect(page, fromTestId, toTestId) {
  const from = page.getByTestId(fromTestId);
  const to = page.getByTestId(toTestId);
  const fromBox = await from.boundingBox();
  const toBox = await to.boundingBox();

  if (!fromBox || !toBox) {
    throw new Error(`dragConnect: missing bounding box (from=${fromTestId} → to=${toTestId}); the handle may not be rendered/visible yet.`);
  }

  await page.mouse.move(fromBox.x + fromBox.width / 2, fromBox.y + fromBox.height / 2);
  await page.mouse.down();
  await page.mouse.move(toBox.x + toBox.width / 2, toBox.y + toBox.height / 2, { steps: 12 });
  await page.mouse.up();
}

/**
 * `targetPosition` matters: dragTo() defaults to the dropzone's centre, so
 * two nodes dropped in a row land exactly on top of each other and the
 * second one swallows every click meant for the first. Spacing them is
 * test hygiene, not a product concern.
 */
async function dropNode(page, type, targetPosition) {
  await page.getByTestId(`palette-item-${type}`).dragTo(
    page.getByTestId('studio-canvas-dropzone'),
    { targetPosition },
  );
}

async function nodeIdFor(page, type) {
  const testId = await page.locator(`[data-testid^="studio-node-${type}-"]`).first().getAttribute('data-testid');

  return testId.replace('studio-node-', '');
}

/**
 * The canvas half of core's provenance analysis.
 *
 * `GraphValidator` already refuses to publish a graph in which a model can
 * reach a port that decides what gets executed, so nothing here changes an
 * outcome. What it changes is when the author finds out: amber as they
 * draw the wire, instead of a 422 naming node ids after they press Save.
 */
test.describe('flow-admin studio — provenance / taint', () => {
  // WebKit's automation protocol does not reliably dispatch the native
  // HTML5 dragstart/dragover/drop sequence the palette relies on — the
  // same known Playwright/WebKit gap already skipped in studio-editor.
  test.skip(({ browserName }) => browserName === 'webkit', 'WebKit does not reliably simulate native HTML5 drag-and-drop via Playwright.');

  test('wiring a model into a command port turns the wire amber and blocks Save', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    await expect(page.getByTestId('studio-node-start')).toBeVisible();
    await expect(page.getByTestId('studio-save-button')).toBeEnabled();

    await dropNode(page, 'demo.summarise', { x: 160, y: 300 });
    await dropNode(page, 'demo.run_command', { x: 520, y: 300 });
    await expect(page.locator('.react-flow__node')).toHaveCount(6);

    const summariseId = await nodeIdFor(page, 'demo.summarise');
    const runCommandId = await nodeIdFor(page, 'demo.run_command');

    await dragConnect(page, `handle-out-${summariseId}-summary`, `handle-in-${runCommandId}-command`);

    // Amber, not red: the ports connect fine, it is the DATA that is the
    // problem — and the fix is usually not at this end of the wire.
    const stroke = await page.locator('.react-flow__edge-path').last().evaluate((path) => path.style.stroke);
    expect(stroke).toBe('rgb(245, 158, 11)');

    const warning = page.getByTestId('studio-taint-warning');
    await expect(warning).toBeVisible();
    await expect(warning).toContainText('requires trusted data');
    await expect(warning).toContainText(`${summariseId}.summary`);

    // Save is blocked, because GraphValidator would reject it anyway —
    // better here than as a 422 the author has to map back to the canvas.
    await expect(page.getByTestId('studio-save-button')).toBeDisabled();

    // The red-wire message must NOT appear: "fix the invalid connection
    // (shown in red)" is actively misleading when nothing is red.
    await expect(page.getByTestId('studio-invalid-wire-warning')).toHaveCount(0);
  });

  test('the same model output into a port that accepts untrusted data is fine', async ({ page }) => {
    // The gate has to leave the normal case alone. Most ports are supposed
    // to carry other people's text; a check that fired on all of them is a
    // check somebody disables.
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    await expect(page.getByTestId('studio-node-start')).toBeVisible();

    await dropNode(page, 'demo.summarise', { x: 160, y: 300 });
    await dropNode(page, 'demo.run_command', { x: 520, y: 300 });

    const summariseId = await nodeIdFor(page, 'demo.summarise');
    const runCommandId = await nodeIdFor(page, 'demo.run_command');

    await dragConnect(page, `handle-out-${summariseId}-summary`, `handle-in-${runCommandId}-note`);

    await expect(page.getByTestId('studio-taint-warning')).toHaveCount(0);
    await expect(page.getByTestId('studio-save-button')).toBeEnabled();
  });

  test('removing the source node re-enables Save', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    await expect(page.getByTestId('studio-node-start')).toBeVisible();

    await dropNode(page, 'demo.summarise', { x: 160, y: 300 });
    await dropNode(page, 'demo.run_command', { x: 520, y: 300 });

    const summariseId = await nodeIdFor(page, 'demo.summarise');
    const runCommandId = await nodeIdFor(page, 'demo.run_command');

    await dragConnect(page, `handle-out-${summariseId}-summary`, `handle-in-${runCommandId}-command`);
    await expect(page.getByTestId('studio-save-button')).toBeDisabled();

    // Delete the SOURCE, not the wire. That is what an author actually
    // does — and it exercises the harder path: taint has to be recomputed
    // across the whole graph after a node removal, not just re-checked on
    // the edge that was flagged. A per-edge cache would pass the
    // delete-the-wire version of this test and fail this one.
    // Backspace, not Delete: this canvas passes no `deleteKeyCode`, so
    // ReactFlow's default applies and Delete is a no-op here. Worth the
    // comment — pressing Delete looks like it works, because nothing
    // errors; the node simply stays and the warning stays with it.
    await page.getByTestId(`studio-node-${summariseId}`).click();
    await page.keyboard.press('Backspace');

    // Assert the removal itself, so this test fails loudly if node
    // deletion ever stops working rather than passing for the wrong
    // reason on an unchanged canvas.
    await expect(page.locator('.react-flow__node')).toHaveCount(5);

    await expect(page.getByTestId('studio-taint-warning')).toHaveCount(0);
    await expect(page.getByTestId('studio-save-button')).toBeEnabled();
  });
});
