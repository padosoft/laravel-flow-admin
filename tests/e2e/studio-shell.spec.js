import { test, expect } from '@playwright/test';

test.describe('flow-admin studio shell (E-PR1 React island pipeline + E-PR2 read-only canvas)', () => {
  test('GET /flow/studio lists flow definitions with a link into their canvas', async ({ page }) => {
    const response = await page.goto('/flow/studio');

    expect(response, 'GET /flow/studio returned a response').not.toBeNull();
    expect(response?.status(), 'GET /flow/studio returns 200').toBe(200);
    await expect(page.locator('h1')).toContainText('Studio');

    const table = page.getByTestId('studio-definitions-table');
    await expect(table).toBeVisible();

    const link = table.getByRole('link', { name: 'OrderCheckoutFlow' });
    await expect(link).toBeVisible();
    // The linked name is the fixture's human-readable display name
    // ("OrderCheckoutFlow"), NOT its internal id ("order_checkout_flow") —
    // ReadModel::graph() accepts either, since that's the same value
    // FlowDefinition::$name (and this link) actually carries.
    await expect(link).toHaveAttribute('href', /\/flow\/studio\/OrderCheckoutFlow$/);
  });

  test('GET /flow/studio/{name} loads the built bundle and renders the fixture graph with typed wire colors', async ({ page }) => {
    const response = await page.goto('/flow/studio/OrderCheckoutFlow');

    expect(response, 'GET /flow/studio/order_checkout_flow returned a response').not.toBeNull();
    expect(response?.status(), 'GET /flow/studio/order_checkout_flow returns 200').toBe(200);

    // The mount point is server-rendered; its content is not — proving it
    // renders proves the built JS bundle (resources/js/studio.jsx, served
    // via flow-admin.assets.studio-js) actually executed in the browser
    // AND fetched real data from flow-admin.studio.graph.
    const canvas = page.getByTestId('flow-studio-canvas');
    await expect(canvas).toBeVisible();

    // `.react-flow` is @xyflow/react's own root wrapper class — its
    // presence proves the library initialized, not just a placeholder div.
    await expect(canvas.locator('.react-flow')).toBeVisible();

    // The ArrayReadModel fixture graph has 4 nodes and 3 wires. Edges only
    // render once @xyflow/react has measured node dimensions (a
    // ResizeObserver callback — not synchronous with the container
    // becoming visible), so wait for a node to report ready before
    // asserting edge count/color, or this races and reads an empty edge
    // layer.
    await expect(canvas.locator('.react-flow__node').first()).toBeVisible();
    await expect(canvas.locator('.react-flow__node')).toHaveCount(4);
    const edges = canvas.locator('.react-flow__edge');
    await expect(edges).toHaveCount(3);

    // 3 distinct PortTypes (json/bool/text) across the fixture's 3 wires —
    // assert each edge's stroke color, proving type-to-color mapping works,
    // not just that "some color" got applied. @xyflow/react sets color via
    // the path's inline style (not a `stroke` attribute), and the browser
    // normalizes hex to `rgb(r, g, b)` when read back through `.style`.
    const strokes = await canvas.locator('.react-flow__edge-path').evaluateAll(
      (paths) => paths.map((path) => path.style.stroke),
    );
    expect(new Set(strokes).size, 'each wire has a distinct color for its distinct PortType').toBe(3);
    expect(strokes).toEqual(expect.arrayContaining([
      'rgb(6, 182, 212)', // json -> #06b6d4
      'rgb(16, 185, 129)', // bool -> #10b981
      'rgb(59, 130, 246)', // text -> #3b82f6
    ]));
  });

  test('GET /flow/studio/{unknown} renders the not-published empty state', async ({ page }) => {
    const response = await page.goto('/flow/studio/does-not-exist');

    expect(response?.status()).toBe(200);

    const notPublished = page.getByTestId('flow-studio-not-published');
    await expect(notPublished).toBeVisible();
    await expect(notPublished).toContainText('no published version');
  });
});
