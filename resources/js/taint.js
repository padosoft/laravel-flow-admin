/**
 * Client-side mirror of core's `Padosoft\LaravelFlow\Provenance\TaintAnalyzer`.
 *
 * Why this exists at all: `GraphValidator` already rejects a graph in which
 * untrusted data reaches a port that declared `requires_trusted`, so the
 * server is the authority and this file changes no outcome. What it changes
 * is WHEN the author finds out. Without it, you wire an LLM into a shell
 * command, the canvas stays green, you press Save, and a 422 comes back
 * naming node ids you now have to hunt for. With it, the wire turns amber
 * as you draw it and the panel names the path.
 *
 * Two things make this a mirror rather than a re-implementation, and both
 * matter for keeping it honest:
 *
 *  - The rule is the same one sentence — untrusted in, untrusted out,
 *    unless an output explicitly claims `trusted`.
 *  - It reads the SAME catalog fields core serialises (`provenance` on an
 *    output, `requires_trusted` on an input), so there is no second
 *    vocabulary to drift.
 *
 * It is deliberately advisory. If this file and the server ever disagree,
 * the server wins and the author sees a 422 — the same outcome they get
 * today, which is the floor this is built above rather than a guarantee it
 * replaces.
 */

const UNTRUSTED = 'untrusted';
const TRUSTED = 'trusted';

/**
 * Kahn order over the current canvas.
 *
 * Returns null when the graph does not fully order — a cycle, which
 * ReactFlow will happily let someone draw even though core's
 * `GraphDefinition` refuses to construct one. Propagation has no meaning
 * without an order, and a partial answer read as complete would report
 * untrusted data as trusted, which is the single mistake this module must
 * never make. Callers treat null as "no taint information", not as "clean".
 */
function topologicalOrder(nodes, edges) {
  const inDegree = new Map(nodes.map((node) => [node.id, 0]));
  const adjacency = new Map();

  for (const edge of edges) {
    if (!inDegree.has(edge.source) || !inDegree.has(edge.target)) continue;
    adjacency.set(edge.source, [...(adjacency.get(edge.source) ?? []), edge.target]);
    inDegree.set(edge.target, inDegree.get(edge.target) + 1);
  }

  const queue = [...inDegree.entries()].filter(([, degree]) => degree === 0).map(([id]) => id);
  const order = [];

  for (let i = 0; i < queue.length; i += 1) {
    const id = queue[i];
    order.push(id);

    for (const next of adjacency.get(id) ?? []) {
      inDegree.set(next, inDegree.get(next) - 1);
      if (inDegree.get(next) === 0) queue.push(next);
    }
  }

  return order.length === nodes.length ? order : null;
}

function portOf(catalogEntry, direction, key) {
  return catalogEntry?.[direction]?.find((candidate) => candidate.key === key) ?? null;
}

/**
 * Which ports on this canvas carry data nobody here wrote, and by what
 * route. Keys are `nodeId.portKey`; values are the hop list, source first.
 *
 * A port absent from a map is trusted — the absence IS the answer, so
 * there is no third "unknown" state for a caller to mishandle.
 */
export function computeTaint(nodes, edges, catalog) {
  const order = topologicalOrder(nodes, edges);
  if (order === null) return { inputs: new Map(), outputs: new Map(), ordered: false };

  const nodeById = new Map(nodes.map((node) => [node.id, node]));
  const wiresByTarget = new Map();
  for (const edge of edges) {
    wiresByTarget.set(edge.target, [...(wiresByTarget.get(edge.target) ?? []), edge]);
  }

  const inputs = new Map();
  const outputs = new Map();

  for (const nodeId of order) {
    const entry = catalog[nodeById.get(nodeId)?.data?.nodeType];
    if (!entry) continue;

    for (const edge of wiresByTarget.get(nodeId) ?? []) {
      const upstream = outputs.get(`${edge.source}.${edge.sourceHandle}`);
      if (!upstream) continue;

      const key = `${nodeId}.${edge.targetHandle}`;
      // First tainted wire into a port wins. A `multiple` (fan-in) port
      // coalesces several wires into one list, and one untrusted element
      // makes the whole list untrusted: a handler holding that list cannot
      // be assumed to keep the clean items apart from the dirty one.
      if (!inputs.has(key)) inputs.set(key, [...upstream, key]);
    }

    const anyInputTainted = (entry.inputs ?? []).some((port) => inputs.has(`${nodeId}.${port.key}`));
    const firstTaintedPath = (entry.inputs ?? [])
      .map((port) => inputs.get(`${nodeId}.${port.key}`))
      .find(Boolean);

    for (const port of entry.outputs ?? []) {
      const key = `${nodeId}.${port.key}`;

      if (port.provenance === UNTRUSTED) {
        // A declared source is untrusted whether or not anything fed it,
        // so the path STARTS here. That also makes it the actionable
        // origin: sanitizing further upstream would fix nothing, because
        // this port would be untrusted with nothing wired into it at all.
        outputs.set(key, [key]);
        continue;
      }

      // An explicit sanitization claim stops propagation dead, and is the
      // only thing that does.
      if (port.provenance === TRUSTED) continue;

      if (anyInputTainted && firstTaintedPath) outputs.set(key, [...firstTaintedPath, key]);
    }
  }

  return { inputs, outputs, ordered: true };
}

/**
 * The wires that land untrusted data on a port which refuses it — the
 * things `GraphValidator` will reject on save.
 *
 * Keyed by edge so the canvas can colour the offending wire, and carrying
 * the full path because the fix is almost never at the sink: it is a
 * sanitizer somewhere along the route, or a wire that should not exist.
 */
export function taintViolations(nodes, edges, catalog, taint) {
  if (!taint.ordered) return new Map();

  const nodeById = new Map(nodes.map((node) => [node.id, node]));
  const violations = new Map();

  for (const edge of edges) {
    const entry = catalog[nodeById.get(edge.target)?.data?.nodeType];
    const targetPort = portOf(entry, 'inputs', edge.targetHandle);
    if (!targetPort?.requires_trusted) continue;

    const path = taint.inputs.get(`${edge.target}.${edge.targetHandle}`);
    if (!path) continue;

    violations.set(edge.id, {
      nodeId: edge.target,
      portKey: edge.targetHandle,
      origin: path[0],
      path,
    });
  }

  return violations;
}

/** The sentence shown to the author. Mirrors `TaintViolation::message()`. */
export function taintMessage(violation) {
  return `Input [${violation.portKey}] on node [${violation.nodeId}] requires trusted data but receives untrusted data originating at [${violation.origin}] (path: ${violation.path.join(' -> ')}).`;
}
