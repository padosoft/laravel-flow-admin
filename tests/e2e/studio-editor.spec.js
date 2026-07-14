import { test, expect } from '@playwright/test';

async function dragConnect(page, fromTestId, toTestId) {
  const from = page.getByTestId(fromTestId);
  const to = page.getByTestId(toTestId);
  const fromBox = await from.boundingBox();
  const toBox = await to.boundingBox();

  await page.mouse.move(fromBox.x + fromBox.width / 2, fromBox.y + fromBox.height / 2);
  await page.mouse.down();
  await page.mouse.move(toBox.x + toBox.width / 2, toBox.y + toBox.height / 2, { steps: 12 });
  await page.mouse.up();
}

test.describe('flow-admin studio editor (E-PR3 canvas editor)', () => {
  test('GET /flow/studio/{name}/edit loads the palette, the fixture graph, and an empty inspector', async ({ page }) => {
    const response = await page.goto('/flow/studio/OrderCheckoutFlow/edit');

    expect(response?.status()).toBe(200);

    const editor = page.getByTestId('flow-studio-editor');
    await expect(editor).toBeVisible();

    const palette = page.getByTestId('studio-palette');
    await expect(palette.getByTestId('palette-item-demo.trigger')).toBeVisible();
    await expect(palette.getByTestId('palette-item-demo.validate')).toBeVisible();
    await expect(palette.getByTestId('palette-item-demo.charge')).toBeVisible();
    await expect(palette.getByTestId('palette-item-demo.notify')).toBeVisible();

    await expect(page.getByTestId('studio-node-start')).toBeVisible();
    await expect(page.locator('.react-flow__node')).toHaveCount(4);

    await expect(page.getByTestId('studio-inspector-empty')).toBeVisible();

    // The fixture graph is already valid (compatible wire types), so Save
    // starts enabled — proves "authorizer-gated (deny-by-default proves a
    // 403)" is a SERVER concern (covered by StudioControllerTest), not a
    // client-side block: the client only disables Save for actual graph
    // invalidity.
    await expect(page.getByTestId('studio-save-button')).toBeEnabled();
  });

  test('selecting a node populates the inspector panel with its config fields', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    await expect(page.getByTestId('studio-node-charge')).toBeVisible();

    await page.getByTestId('studio-node-charge').click();

    const inspector = page.getByTestId('studio-inspector');
    await expect(inspector).toBeVisible();
    await expect(inspector).toContainText('Charge Payment');
    await expect(inspector).toContainText('demo.charge');

    const authorizedField = page.getByTestId('config-authorized');
    await expect(authorizedField).toBeVisible();

    const before = await authorizedField.isChecked();
    await authorizedField.setChecked(!before);
    await expect(authorizedField).toBeChecked({ checked: !before });
  });

  test('an incompatible wire renders red/dashed and disables Save with a warning', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    await expect(page.getByTestId('studio-node-start')).toBeVisible();
    await expect(page.getByTestId('studio-save-button')).toBeEnabled();

    const edgesBefore = await page.locator('.react-flow__edge').count();

    // demo.trigger's `out` output is `json`; demo.charge's `authorized`
    // input is `bool` — PortType::accepts() rejects json->bool, so this
    // wire (bypassing demo.validate) must be flagged invalid.
    await dragConnect(page, 'handle-out-start-out', 'handle-in-charge-authorized');

    await expect(page.locator('.react-flow__edge')).toHaveCount(edgesBefore + 1);
    await expect(page.getByTestId('studio-invalid-wire-warning')).toBeVisible();
    await expect(page.getByTestId('studio-save-button')).toBeDisabled();

    const newEdgeStroke = await page.locator('.react-flow__edge-path').last().evaluate((path) => path.style.stroke);
    expect(newEdgeStroke).toBe('rgb(239, 68, 68)');
  });

  test('dragging a palette item onto the canvas adds a new node', async ({ page, browserName }) => {
    // WebKit's automation protocol does not reliably dispatch the native
    // dragstart/dragover/drop event sequence Playwright's locator.dragTo()
    // relies on for real HTML5 drag-and-drop (a known, longstanding
    // Playwright/WebKit gap, not an app bug) — verified working correctly
    // on both chromium and firefox for this exact interaction.
    test.skip(browserName === 'webkit', 'WebKit does not reliably simulate native HTML5 drag-and-drop via Playwright.');

    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    await expect(page.locator('.react-flow__node')).toHaveCount(4);

    await page.getByTestId('palette-item-demo.notify').dragTo(page.getByTestId('studio-canvas-dropzone'));

    await expect(page.locator('.react-flow__node')).toHaveCount(5);
  });

  test('saving an unmodified valid graph succeeds and shows a success message', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow/edit');
    await expect(page.getByTestId('studio-save-button')).toBeEnabled();

    await page.getByTestId('studio-save-button').click();

    await expect(page.getByTestId('studio-save-success')).toBeVisible();
    await expect(page.getByTestId('studio-save-success')).toContainText('Draft version');
  });

  test('the read-only canvas links into the editor', async ({ page }) => {
    await page.goto('/flow/studio/OrderCheckoutFlow');

    const editLink = page.getByTestId('studio-edit-link');
    await expect(editLink).toBeVisible();
    await expect(editLink).toHaveAttribute('href', /\/flow\/studio\/OrderCheckoutFlow\/edit$/);

    await editLink.click();
    await expect(page.getByTestId('flow-studio-editor')).toBeVisible();
  });
});
