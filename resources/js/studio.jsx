import { StrictMode, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
  ReactFlow,
  ReactFlowProvider,
  Background,
  Controls,
  MarkerType,
  Handle,
  Position,
  useReactFlow,
  addEdge,
  applyNodeChanges,
  applyEdgeChanges,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import '../css/studio.css';

// Mirrors core's Padosoft\LaravelFlow\Node\PortType (6 cases: text, int,
// float, bool, json, any) — one distinguishable color per wire type so a
// glance at the canvas tells you what's flowing through a connection.
const PORT_TYPE_COLORS = {
  text: '#3b82f6',
  int: '#8b5cf6',
  float: '#f59e0b',
  bool: '#10b981',
  json: '#06b6d4',
  any: '#6b7280',
};

const FALLBACK_COLOR = PORT_TYPE_COLORS.any;

function outputPortType(catalogEntry, portKey) {
  const port = catalogEntry?.outputs?.find((candidate) => candidate.key === portKey);

  return port?.type ?? null;
}

/**
 * Maps a `{graph, catalog}` API response (core's GraphSerializer envelope +
 * a node-type-scoped catalog subset) into `@xyflow/react`'s node/edge
 * shape. Nodes without a stored position (e.g. a programmatically-built
 * graph) fall back to a simple left-to-right grid — a real auto-layout
 * algorithm is out of scope for this read-only canvas.
 */
function toFlowElements({ graph, catalog }) {
  const nodesById = new Map(graph.nodes.map((node) => [node.id, node]));

  const nodes = graph.nodes.map((node, index) => {
    const entry = catalog[node.type];

    return {
      id: node.id,
      position: node.position ?? { x: index * 220, y: 0 },
      data: { label: entry?.name ?? node.type },
      style: {
        border: '1px solid var(--border, #333)',
        borderRadius: 8,
        padding: '8px 12px',
        background: 'var(--bg-elevated, #1a1a1a)',
        color: 'var(--text, #eee)',
        fontSize: 13,
      },
    };
  });

  const edges = graph.connections.map((wire) => {
    const sourceNode = nodesById.get(wire.sourceNodeId);
    const sourceCatalogEntry = sourceNode ? catalog[sourceNode.type] : undefined;
    const portType = outputPortType(sourceCatalogEntry, wire.sourcePortKey);
    const color = PORT_TYPE_COLORS[portType] ?? FALLBACK_COLOR;

    return {
      id: `${wire.sourceNodeId}.${wire.sourcePortKey}->${wire.targetNodeId}.${wire.targetPortKey}`,
      source: wire.sourceNodeId,
      target: wire.targetNodeId,
      style: { stroke: color, strokeWidth: 2 },
      markerEnd: { type: MarkerType.ArrowClosed, color },
      'data-port-type': portType ?? 'unknown',
    };
  });

  return { nodes, edges };
}

function StudioCanvas({ graphUrl }) {
  const [state, setState] = useState({ status: 'loading', nodes: [], edges: [] });

  useEffect(() => {
    let cancelled = false;

    fetch(graphUrl, { headers: { Accept: 'application/json' } })
      .then((response) => {
        if (response.status === 404) {
          return null;
        }

        if (!response.ok) {
          throw new Error(`Unexpected response: ${response.status}`);
        }

        return response.json();
      })
      .then((payload) => {
        if (cancelled) return;

        if (payload === null) {
          setState({ status: 'not-published', nodes: [], edges: [] });

          return;
        }

        const { nodes, edges } = toFlowElements(payload);
        setState({ status: 'ready', nodes, edges });
      })
      .catch(() => {
        if (!cancelled) {
          setState({ status: 'error', nodes: [], edges: [] });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [graphUrl]);

  if (state.status === 'loading') {
    return (
      <div className="empty" data-testid="flow-studio-loading">
        Loading graph…
      </div>
    );
  }

  if (state.status === 'not-published') {
    return (
      <div className="empty" data-testid="flow-studio-not-published">
        This flow has no published version yet.
      </div>
    );
  }

  if (state.status === 'error') {
    return (
      <div className="empty" data-testid="flow-studio-error">
        Could not load the graph. Try reloading the page.
      </div>
    );
  }

  return (
    <div data-testid="flow-studio-canvas" style={{ width: '100%', height: '100%' }}>
      <ReactFlowProvider>
        {/* hideAttribution is explicitly MIT-permitted, not a Pro-only
            feature — xyflow's own docs: "Anyone is free to remove this
            attribution whether they're a Pro subscriber or not." Keeping
            the admin panel's own chrome (sidebar/topbar) consistent takes
            priority over a third-party badge. */}
        <ReactFlow nodes={state.nodes} edges={state.edges} fitView proOptions={{ hideAttribution: true }}>
          <Background />
        </ReactFlow>
      </ReactFlowProvider>
    </div>
  );
}

// Mirrors core's Padosoft\LaravelFlow\Node\PortType::accepts() exactly:
// `any` is a universal source/target, int widens to float, otherwise the
// types must match. Client-side validation is advisory only — the server
// re-runs GraphValidator before persisting, so this only needs to catch
// the common cases fast enough to color a wire while dragging.
function portAccepts(targetType, sourceType) {
  if (targetType === 'any' || sourceType === 'any') return true;
  if (targetType === 'float' && sourceType === 'int') return true;

  return targetType === sourceType;
}

function findPort(catalogEntry, direction, key) {
  return catalogEntry?.[direction]?.find((candidate) => candidate.key === key) ?? null;
}

/**
 * Single source of truth for wire validity — used identically when
 * building the initial edge list from a loaded graph AND when the user
 * draws a new connection, so a graph that would render red interactively
 * never renders green just because it was loaded from the server. Mirrors
 * core's GraphValidator: a wire is valid only if the port TYPES are
 * compatible (portAccepts) AND the fan-in rule holds (a non-`multiple`
 * input accepts at most one incoming wire).
 */
function wireIsValid(sourcePort, targetPort, fanInOk) {
  const typeOk = Boolean(sourcePort) && Boolean(targetPort) && portAccepts(targetPort.type, sourcePort.type);

  return typeOk && fanInOk;
}

function wireVisuals(valid, sourcePortType) {
  const color = valid ? (PORT_TYPE_COLORS[sourcePortType] ?? FALLBACK_COLOR) : '#ef4444';

  return {
    style: { stroke: color, strokeWidth: 2, ...(valid ? {} : { strokeDasharray: '4 3' }) },
    markerEnd: { type: MarkerType.ArrowClosed, color },
  };
}

/**
 * Recomputes `data.valid` (and the matching red/dashed vs typed-color
 * visuals) for every edge from scratch, using the SAME rules as onConnect:
 * source→target port-type compatibility plus fan-in (a non-`multiple` input
 * accepts only its first incoming edge, in array order). Call this after an
 * edge or node removal so a wire that was flagged invalid purely because of
 * a fan-in conflict recovers once the conflicting edge is gone — otherwise
 * the stale `valid === false` keeps Save disabled even though the conflict
 * no longer exists (Codex review, PR #36).
 */
function recomputeEdgeValidity(edges, nodes, catalog) {
  const nodeById = new Map(nodes.map((node) => [node.id, node]));
  const occupiedInputs = new Set();

  return edges.map((edge) => {
    const sourceNode = nodeById.get(edge.source);
    const targetNode = nodeById.get(edge.target);
    const sourcePort = findPort(catalog[sourceNode?.data.nodeType], 'outputs', edge.sourceHandle);
    const targetPort = findPort(catalog[targetNode?.data.nodeType], 'inputs', edge.targetHandle);

    const inputKey = `${edge.target}.${edge.targetHandle}`;
    const fanInOk = Boolean(targetPort?.multiple) || !occupiedInputs.has(inputKey);
    if (! targetPort?.multiple) {
      occupiedInputs.add(inputKey);
    }

    const valid = wireIsValid(sourcePort, targetPort, fanInOk);

    return { ...edge, data: { ...edge.data, valid }, ...wireVisuals(valid, sourcePort?.type) };
  });
}

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Collision-free across reloads/sessions — a per-mount incrementing
 * counter (the prior approach) resets to 0 every time the editor page
 * loads, so dropping the same node type in two different sessions
 * produces the SAME id, which core's GraphDefinition rejects as a
 * "Duplicate node id" 422 on save.
 */
function generateNodeId(type) {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return `${type}-${crypto.randomUUID()}`;
  }

  return `${type}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Custom ReactFlow node that renders one Handle per catalog-declared
 * input/output port (keyed by the real port `key`, not an implicit single
 * handle) — required so `onConnect`'s `sourceHandle`/`targetHandle` map
 * onto real port keys for a node type with more than one port per side.
 */
function StudioEditorNode({ id, data }) {
  const { label, inputs, outputs, invalid } = data;

  return (
    <div
      data-testid={`studio-node-${id}`}
      style={{
        border: `1px solid ${invalid ? '#ef4444' : 'var(--border, #333)'}`,
        borderRadius: 8,
        padding: '8px 14px',
        background: 'var(--bg-elevated, #1a1a1a)',
        color: 'var(--text, #eee)',
        fontSize: 13,
        minWidth: 120,
        position: 'relative',
      }}
    >
      {label}
      {inputs.map((port, index) => (
        <Handle
          key={port.key}
          type="target"
          position={Position.Left}
          id={port.key}
          data-testid={`handle-in-${id}-${port.key}`}
          style={{ top: `${((index + 1) / (inputs.length + 1)) * 100}%`, background: PORT_TYPE_COLORS[port.type] ?? FALLBACK_COLOR }}
        />
      ))}
      {outputs.map((port, index) => (
        <Handle
          key={port.key}
          type="source"
          position={Position.Right}
          id={port.key}
          data-testid={`handle-out-${id}-${port.key}`}
          style={{ top: `${((index + 1) / (outputs.length + 1)) * 100}%`, background: PORT_TYPE_COLORS[port.type] ?? FALLBACK_COLOR }}
        />
      ))}
    </div>
  );
}

const EDITOR_NODE_TYPES = { studioNode: StudioEditorNode };

function editableGraphToFlowElements({ graph, catalog }) {
  const nodes = graph.nodes.map((node, index) => {
    const entry = catalog[node.type] ?? { name: node.type, inputs: [], outputs: [] };

    return {
      id: node.id,
      type: 'studioNode',
      position: node.position ?? { x: index * 220, y: 0 },
      data: { label: entry.name ?? node.type, nodeType: node.type, inputs: entry.inputs, outputs: entry.outputs, config: node.config ?? {} },
    };
  });

  // Precompute nodeId → nodeType once so each edge resolves its endpoints'
  // catalog entries in O(1) instead of scanning `nodes` twice per edge
  // (O(E·N) overall, which grows noticeably on larger graphs).
  const nodeTypeById = new Map(nodes.map((n) => [n.id, n.data.nodeType]));

  // Tracks non-`multiple` input ports already fed by an earlier wire in
  // this same list, so a loaded graph with a duplicate fan-in renders the
  // SECOND wire red exactly like drawing it interactively would (onConnect
  // applies the identical rule against the live edge list).
  const wiredInputs = new Set();

  const edges = graph.connections.map((wire) => {
    const sourceEntry = catalog[nodeTypeById.get(wire.sourceNodeId)];
    const targetEntry = catalog[nodeTypeById.get(wire.targetNodeId)];
    const sourcePort = findPort(sourceEntry, 'outputs', wire.sourcePortKey);
    const targetPort = findPort(targetEntry, 'inputs', wire.targetPortKey);
    const targetKey = `${wire.targetNodeId}.${wire.targetPortKey}`;
    const fanInOk = Boolean(targetPort?.multiple) || !wiredInputs.has(targetKey);
    wiredInputs.add(targetKey);

    const valid = wireIsValid(sourcePort, targetPort, fanInOk);

    return {
      id: `${wire.sourceNodeId}.${wire.sourcePortKey}->${wire.targetNodeId}.${wire.targetPortKey}`,
      source: wire.sourceNodeId,
      target: wire.targetNodeId,
      sourceHandle: wire.sourcePortKey,
      targetHandle: wire.targetPortKey,
      data: { valid },
      ...wireVisuals(valid, sourcePort?.type),
    };
  });

  return { nodes, edges };
}

function jsonFieldError(value) {
  try {
    JSON.parse(value);

    return null;
  } catch {
    return 'Invalid JSON';
  }
}

function ConfigField({ port, value, onChange }) {
  const testId = `config-${port.key}`;

  if (port.type === 'bool') {
    return (
      <input
        type="checkbox"
        checked={Boolean(value)}
        onChange={(event) => onChange(event.target.checked)}
        data-testid={testId}
      />
    );
  }

  if (port.type === 'int' || port.type === 'float') {
    return (
      <input
        type="number"
        step={port.type === 'int' ? '1' : 'any'}
        value={value ?? ''}
        onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
        data-testid={testId}
      />
    );
  }

  if (port.type === 'json') {
    const text = typeof value === 'string' ? value : JSON.stringify(value ?? null);
    const error = jsonFieldError(text);

    return (
      <div>
        <textarea rows={3} value={text} onChange={(event) => onChange(event.target.value)} data-testid={testId} />
        {error && (
          <div className="field-error" data-testid={`${testId}-error`}>
            {error}
          </div>
        )}
      </div>
    );
  }

  return (
    <input
      type="text"
      value={value ?? ''}
      onChange={(event) => onChange(event.target.value)}
      data-testid={testId}
    />
  );
}

function InspectorPanel({ node, onChangeConfig, onDeleteNode }) {
  if (!node) {
    return (
      <div className="studio-inspector empty" data-testid="studio-inspector-empty">
        Select a node to edit its configuration.
      </div>
    );
  }

  return (
    <div className="studio-inspector" data-testid="studio-inspector">
      <h3>{node.data.label}</h3>
      <p className="studio-inspector-type">{node.data.nodeType}</p>
      {node.data.inputs.length === 0 && <p>This node has no configurable inputs.</p>}
      {node.data.inputs.map((port) => (
        <label key={port.key} className="studio-inspector-field">
          <span>
            {port.label} {port.required && '*'}
          </span>
          <ConfigField
            port={port}
            value={node.data.config[port.key]}
            onChange={(value) => onChangeConfig(node.id, port.key, value)}
          />
        </label>
      ))}
      <button type="button" className="studio-inspector-delete" onClick={() => onDeleteNode(node.id)} data-testid="studio-delete-node-button">
        Delete node
      </button>
    </div>
  );
}

function PaletteItem({ entry }) {
  const onDragStart = (event) => {
    event.dataTransfer.setData('application/flow-node-type', entry.type);
    event.dataTransfer.effectAllowed = 'move';
  };

  return (
    <div
      className="studio-palette-item"
      draggable
      onDragStart={onDragStart}
      data-testid={`palette-item-${entry.type}`}
    >
      {entry.name}
    </div>
  );
}

// Serializes the live editor state into a GraphSerializer envelope. Throws
// (SyntaxError) if a JSON-typed config field holds invalid JSON — callers
// surface that as an editor error. Shared by save-as-draft and dry-run.
function buildGraphPayload(nodes, edges) {
  const graphNodes = nodes.map((node) => {
    const config = { ...node.data.config };
    node.data.inputs.forEach((port) => {
      if (port.type === 'json' && typeof config[port.key] === 'string') {
        config[port.key] = JSON.parse(config[port.key]);
      }
    });

    return { id: node.id, type: node.data.nodeType, config, position: node.position };
  });

  const connections = edges.map((edge) => ({
    sourceNodeId: edge.source,
    sourcePortKey: edge.sourceHandle,
    targetNodeId: edge.target,
    targetPortKey: edge.targetHandle,
  }));

  return { schema_version: 1, kind: 'laravel-flow', metadata: {}, nodes: graphNodes, connections };
}

function StudioEditorCanvas({ editGraphUrl, catalogUrl, draftUrl, dryRunUrl, aiBuildUrl }) {
  const [state, setState] = useState({ status: 'loading', nodes: [], edges: [], catalog: {} });
  const [dryRun, setDryRun] = useState(null);
  const [aiModalOpen, setAiModalOpen] = useState(false);
  // Monotonic id so a slow dry-run response that resolves AFTER the graph was
  // edited (which bumps this) is ignored instead of rendering a stale plan.
  // Bumped by both onDryRun and every structural-edit handler below.
  const dryRunReqRef = useRef(0);
  const [selectedNodeId, setSelectedNodeId] = useState(null);
  const [saveStatus, setSaveStatus] = useState({ kind: 'idle', message: '' });
  const { screenToFlowPosition } = useReactFlow();

  useEffect(() => {
    let cancelled = false;

    Promise.all([
      fetch(editGraphUrl, { headers: { Accept: 'application/json' } }),
      fetch(catalogUrl, { headers: { Accept: 'application/json' } }),
    ])
      .then(async ([graphResponse, catalogResponse]) => {
        if (cancelled) return;

        if (graphResponse.status === 403) {
          setState({ status: 'forbidden', nodes: [], edges: [], catalog: {} });

          return;
        }

        if (graphResponse.status === 404) {
          const catalogPayload = catalogResponse.ok ? await catalogResponse.json() : { catalog: {} };
          setState({ status: 'new', nodes: [], edges: [], catalog: catalogPayload.catalog ?? {} });

          return;
        }

        if (!graphResponse.ok || !catalogResponse.ok) {
          throw new Error('Unexpected response');
        }

        const graphPayload = await graphResponse.json();
        const catalogPayload = await catalogResponse.json();
        const catalog = catalogPayload.catalog ?? {};
        const { nodes, edges } = editableGraphToFlowElements({ graph: graphPayload.graph, catalog });
        setState({ status: 'ready', nodes, edges, catalog });
      })
      .catch(() => {
        if (!cancelled) setState({ status: 'error', nodes: [], edges: [], catalog: {} });
      });

    return () => {
      cancelled = true;
    };
  }, [editGraphUrl, catalogUrl]);

  const hasInvalidWire = useMemo(() => state.edges.some((edge) => edge.data?.valid === false), [state.edges]);
  const hasConfigError = useMemo(
    () =>
      state.nodes.some((node) =>
        node.data.inputs.some((port) => port.type === 'json' && jsonFieldError(
          typeof node.data.config[port.key] === 'string' ? node.data.config[port.key] : JSON.stringify(node.data.config[port.key] ?? null),
        )),
      ),
    [state.nodes],
  );
  const canSave = (state.status === 'ready' || state.status === 'new') && !hasInvalidWire && !hasConfigError;

  const onConnect = useCallback(
    (params) => {
      dryRunReqRef.current += 1;
      setDryRun(null); // a new edge changes the execution plan — drop any stale one
      setState((current) => {
        const sourceNode = current.nodes.find((n) => n.id === params.source);
        const targetNode = current.nodes.find((n) => n.id === params.target);
        const sourcePort = findPort(current.catalog[sourceNode?.data.nodeType], 'outputs', params.sourceHandle);
        const targetPort = findPort(current.catalog[targetNode?.data.nodeType], 'inputs', params.targetHandle);
        const fanInOk = Boolean(targetPort?.multiple) || !current.edges.some((e) => e.target === params.target && e.targetHandle === params.targetHandle);
        const valid = wireIsValid(sourcePort, targetPort, fanInOk);

        const newEdge = {
          ...params,
          data: { valid },
          ...wireVisuals(valid, sourcePort?.type),
        };

        return { ...current, edges: addEdge(newEdge, current.edges) };
      });
    },
    [],
  );

  const onNodesChange = useCallback((changes) => {
    // A node add/remove changes the plan; a pure position/selection change
    // does not, so only clear a stale dry-run on structural changes.
    if (changes.some((change) => change.type === 'remove' || change.type === 'add')) {
      dryRunReqRef.current += 1;
      setDryRun(null);
    }
    setState((current) => {
      const nodes = applyNodeChanges(changes, current.nodes);

      // A removed node's edges are NOT pruned by applyNodeChanges (it only
      // touches the nodes array) — without this, a dangling edge stays in
      // state.edges referencing a node id that no longer exists, which
      // canSave doesn't re-validate (so Save stays enabled) and the server
      // then rejects with a violation the user can't correlate to anything
      // still visible on the canvas.
      const removedIds = new Set(changes.filter((change) => change.type === 'remove').map((change) => change.id));
      const edges = removedIds.size > 0
        ? recomputeEdgeValidity(
            current.edges.filter((edge) => !removedIds.has(edge.source) && !removedIds.has(edge.target)),
            nodes,
            current.catalog,
          )
        : current.edges;

      return { ...current, nodes, edges };
    });
  }, []);

  const onEdgesChange = useCallback((changes) => {
    if (changes.some((change) => change.type === 'remove')) {
      dryRunReqRef.current += 1;
      setDryRun(null); // removing an edge changes the execution plan
    }
    setState((current) => {
      const edges = applyEdgeChanges(changes, current.edges);

      // Removing one edge of a fan-in conflict can make a surviving edge
      // valid again — recompute so Save re-enables instead of staying
      // stuck on the removed edge's stale flag (Codex review, PR #36).
      const hasRemoval = changes.some((change) => change.type === 'remove');

      return { ...current, edges: hasRemoval ? recomputeEdgeValidity(edges, current.nodes, current.catalog) : edges };
    });
  }, []);

  const onDrop = useCallback(
    (event) => {
      event.preventDefault();
      const type = event.dataTransfer.getData('application/flow-node-type');
      if (!type) return;

      dryRunReqRef.current += 1;
      setDryRun(null); // a new node changes the execution plan

      setState((current) => {
        const entry = current.catalog[type];
        if (!entry) return current;

        const position = screenToFlowPosition({ x: event.clientX, y: event.clientY });
        const id = generateNodeId(type);
        const config = {};
        entry.inputs.forEach((port) => {
          config[port.key] = port.type === 'json' ? '{}' : port.type === 'bool' ? false : port.type === 'text' || port.type === 'any' ? '' : null;
        });

        const node = {
          id,
          type: 'studioNode',
          position,
          data: { label: entry.name, nodeType: type, inputs: entry.inputs, outputs: entry.outputs, config },
        };

        return { ...current, nodes: [...current.nodes, node], status: current.status === 'new' ? 'ready' : current.status };
      });
    },
    [screenToFlowPosition],
  );

  const onChangeConfig = useCallback((nodeId, portKey, value) => {
    setState((current) => ({
      ...current,
      nodes: current.nodes.map((node) =>
        node.id === nodeId ? { ...node, data: { ...node.data, config: { ...node.data.config, [portKey]: value } } } : node,
      ),
    }));
  }, []);

  // Routed through the same onNodesChange the canvas itself uses (for
  // drag-driven position updates and @xyflow/react's native
  // click-to-select-then-Backspace deletion) — one code path, so a node
  // deleted via this button prunes its connected edges identically.
  const onDeleteNode = useCallback(
    (nodeId) => {
      // Explicit confirmation before a destructive action (rule-admin-ajax-pattern):
      // nothing is persisted until the user explicitly clicks "Save as
      // draft" afterward, but the canvas edit itself is otherwise
      // undoable-only-by-reload, so a one-click delete still warrants a
      // confirm.
      const node = state.nodes.find((n) => n.id === nodeId);
      const label = node?.data.label ?? nodeId;
      if (!window.confirm(`Delete node "${label}"? This cannot be undone.`)) {
        return;
      }

      onNodesChange([{ type: 'remove', id: nodeId }]);
      setSelectedNodeId(null);
    },
    [state.nodes, onNodesChange],
  );

  const onSave = useCallback(() => {
    setSaveStatus({ kind: 'saving', message: '' });

    // Defense-in-depth: canSave already gates the button on !hasConfigError,
    // but the two JSON checks are not literally the same call, so a future
    // drift between them must not surface as an uncaught SyntaxError.
    let payload;
    try {
      payload = buildGraphPayload(state.nodes, state.edges);
    } catch {
      setSaveStatus({ kind: 'error', message: 'One or more config fields contain invalid JSON.' });

      return;
    }

    fetch(draftUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify(payload),
    })
      .then(async (response) => {
        const body = await response.json();

        if (!response.ok) {
          const violations = body.data?.violations;
          setSaveStatus({ kind: 'error', message: violations ? `${body.message} ${violations.join(' | ')}` : body.message });

          return;
        }

        setSaveStatus({ kind: 'success', message: body.message });
      })
      .catch(() => setSaveStatus({ kind: 'error', message: 'Network error while saving.' }));
  }, [state.nodes, state.edges, draftUrl]);

  const onDryRun = useCallback(() => {
    if (!dryRunUrl) return;

    let payload;
    try {
      payload = buildGraphPayload(state.nodes, state.edges);
    } catch {
      setDryRun({ status: 'error', message: 'One or more config fields contain invalid JSON.' });

      return;
    }

    const reqId = (dryRunReqRef.current += 1);
    const isStale = () => dryRunReqRef.current !== reqId;

    setDryRun({ status: 'planning' });
    fetch(dryRunUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify(payload),
    })
      .then(async (response) => {
        const body = await response.json().catch(() => ({}));
        if (isStale()) return; // the graph was edited (or re-planned) mid-flight

        if (!response.ok) {
          const violations = body.data?.violations;
          setDryRun({ status: 'error', message: violations ? `${body.message} ${violations.join(' | ')}` : (body.message ?? 'Could not plan the graph.') });

          return;
        }

        setDryRun({ status: 'ready', plan: body.plan, cost: body.cost });
      })
      .catch(() => {
        if (!isStale()) setDryRun({ status: 'error', message: 'Network error while planning.' });
      });
  }, [state.nodes, state.edges, dryRunUrl]);

  // Loads an AI-generated (already server-validated) graph envelope onto the
  // canvas, REPLACING the current nodes/edges — the operator reviews it and
  // saves it through the normal draft flow. Any previous dry-run plan no
  // longer matches the new graph, so drop it (and bump the stale guard).
  const onAiGenerated = useCallback((graph) => {
    dryRunReqRef.current += 1;
    setDryRun(null);
    setState((current) => {
      const { nodes, edges } = editableGraphToFlowElements({ graph, catalog: current.catalog });

      return { ...current, status: 'ready', nodes, edges };
    });
    setSelectedNodeId(null);
    setSaveStatus({ kind: 'idle', message: '' });
    setAiModalOpen(false);
  }, []);

  if (state.status === 'loading') {
    return (
      <div className="empty" data-testid="flow-studio-loading">
        Loading editor…
      </div>
    );
  }

  if (state.status === 'forbidden') {
    return (
      <div className="empty" data-testid="flow-studio-forbidden">
        You are not authorized to edit this flow.
      </div>
    );
  }

  if (state.status === 'error') {
    return (
      <div className="empty" data-testid="flow-studio-error">
        Could not load the editor. Try reloading the page.
      </div>
    );
  }

  const selectedNode = state.nodes.find((n) => n.id === selectedNodeId) ?? null;

  return (
    <div className="studio-editor" data-testid="flow-studio-editor" style={{ display: 'flex', width: '100%', height: '100%' }}>
      <div className="studio-palette" data-testid="studio-palette">
        {Object.values(state.catalog).map((entry) => (
          <PaletteItem key={entry.type} entry={entry} />
        ))}
      </div>
      <div
        data-testid="studio-canvas-dropzone"
        style={{ flex: 1, position: 'relative' }}
        onDragOver={(event) => {
          event.preventDefault();
          event.dataTransfer.dropEffect = 'move';
        }}
        onDrop={onDrop}
      >
        <ReactFlow
          nodeTypes={EDITOR_NODE_TYPES}
          nodes={state.nodes}
          edges={state.edges}
          onNodesChange={onNodesChange}
          onEdgesChange={onEdgesChange}
          onConnect={onConnect}
          onNodeClick={(_, node) => setSelectedNodeId(node.id)}
          onPaneClick={() => setSelectedNodeId(null)}
          fitView
          proOptions={{ hideAttribution: true }}
        >
          <Background />
          <Controls />
        </ReactFlow>
        <div className="studio-save-bar" data-testid="studio-save-bar">
          <button type="button" onClick={onSave} disabled={!canSave || saveStatus.kind === 'saving'} data-testid="studio-save-button">
            Save as draft
          </button>
          {dryRunUrl ? (
            <button
              type="button"
              onClick={onDryRun}
              disabled={!(state.status === 'ready' || state.status === 'new') || dryRun?.status === 'planning'}
              data-testid="studio-dry-run-button"
            >
              {dryRun?.status === 'planning' ? 'Planning…' : 'Dry run'}
            </button>
          ) : null}
          {aiBuildUrl ? (
            <button
              type="button"
              onClick={() => setAiModalOpen(true)}
              disabled={!(state.status === 'ready' || state.status === 'new')}
              data-testid="studio-ai-build-button"
            >
              Build with AI
            </button>
          ) : null}
          {hasInvalidWire && (
            <span className="field-error" data-testid="studio-invalid-wire-warning">
              Fix the invalid connection (shown in red) before saving.
            </span>
          )}
          {saveStatus.kind === 'success' && (
            <span data-testid="studio-save-success">{saveStatus.message}</span>
          )}
          {saveStatus.kind === 'error' && (
            <span className="field-error" data-testid="studio-save-error">
              {saveStatus.message}
            </span>
          )}
        </div>
        {dryRun?.status === 'error' ? (
          <div className="field-error" data-testid="studio-dry-run-error" style={{ padding: '6px 10px' }}>{dryRun.message}</div>
        ) : null}
        {dryRun?.status === 'ready' ? (
          <div
            data-testid="studio-dry-run-panel"
            style={{ padding: '10px 12px', border: '1px solid var(--border, #333)', borderRadius: 8, marginTop: 8, fontSize: 13 }}
          >
            <div style={{ fontWeight: 600, marginBottom: 6 }}>Execution plan</div>
            <ol style={{ margin: '0 0 10px', paddingLeft: 18 }}>
              {(dryRun.plan?.waves ?? []).map((wave, index) => (
                <li key={index} data-testid={`dry-run-wave-${index}`}>
                  Wave {index}: {wave.join(', ')}
                </li>
              ))}
            </ol>
            <div data-testid="dry-run-cost-total">
              Estimated cost:{' '}
              {Object.keys(dryRun.cost?.total ?? {}).length === 0
                ? 'n/a'
                : Object.entries(dryRun.cost.total).map(([dim, value]) => `${value} ${dim}`).join(' · ')}
            </div>
          </div>
        ) : null}
      </div>
      <InspectorPanel node={selectedNode} onChangeConfig={onChangeConfig} onDeleteNode={onDeleteNode} />
      {aiModalOpen && aiBuildUrl ? (
        <AiBuildModal aiBuildUrl={aiBuildUrl} onClose={() => setAiModalOpen(false)} onGenerated={onAiGenerated} />
      ) : null}
    </div>
  );
}

function StudioEditor(props) {
  return (
    <ReactFlowProvider>
      <StudioEditorCanvas {...props} />
    </ReactFlowProvider>
  );
}

/**
 * "Build with AI" modal: an operator types a natural-language prompt, the
 * server (padosoft/laravel-flow-ai's FlowBuilderService) returns an
 * ALREADY-VALIDATED graph envelope, and the canvas loads it for review — the
 * AI never persists anything. A 422 carries the concrete validation
 * violations for the operator to see why a prompt didn't yield a usable graph.
 */
function AiBuildModal({ onClose, onGenerated, aiBuildUrl }) {
  const [prompt, setPrompt] = useState('');
  const [state, setState] = useState({ kind: 'idle', message: '', violations: [] });

  const submit = useCallback(async () => {
    const trimmed = prompt.trim();
    if (trimmed.length < 3) {
      setState({ kind: 'error', message: 'Describe the flow in a few words first.', violations: [] });

      return;
    }

    setState({ kind: 'building', message: '', violations: [] });
    try {
      const response = await fetch(aiBuildUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ prompt: trimmed }),
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok || !body.success) {
        setState({
          kind: 'error',
          message: body.message ?? 'Could not build the graph.',
          violations: Array.isArray(body.data?.violations) ? body.data.violations : [],
        });

        return;
      }

      onGenerated(body.graph);
    } catch {
      setState({ kind: 'error', message: 'Network error while contacting the AI builder.', violations: [] });
    }
  }, [prompt, aiBuildUrl, onGenerated]);

  return (
    <div
      data-testid="ai-build-modal"
      role="dialog"
      aria-modal="true"
      aria-labelledby="ai-build-modal-title"
      style={{
        position: 'fixed', inset: 0, background: 'var(--bg-overlay, rgba(0,0,0,0.6))',
        display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 50,
      }}
    >
      <div style={{ background: 'var(--bg-elevated, #1a1a1a)', color: 'var(--text, #eee)', borderRadius: 12, padding: 20, width: 'min(560px, 92vw)', border: '1px solid var(--border, #333)' }}>
        <h3 id="ai-build-modal-title" style={{ margin: '0 0 8px', fontSize: 16 }}>Build with AI</h3>
        <p style={{ fontSize: 13, color: 'var(--text-secondary, #999)', margin: '0 0 12px' }}>
          Describe the flow in plain language. The generated graph is validated and loaded onto the canvas for you to review — nothing is saved until you click <b style={{ color: 'var(--text, #eee)' }}>Save as draft</b>.
        </p>
        <textarea
          data-testid="ai-build-prompt"
          aria-label="Describe the flow to build"
          value={prompt}
          onChange={(event) => setPrompt(event.target.value)}
          rows={4}
          placeholder="e.g. When an order is placed, validate it, charge the card, then notify the customer."
          style={{ width: '100%', boxSizing: 'border-box', marginBottom: 12, fontSize: 13, padding: 8 }}
          disabled={state.kind === 'building'}
        />
        {state.kind === 'error' ? (
          <div className="field-error" data-testid="ai-build-error" style={{ marginBottom: 12 }}>
            {state.message}
            {state.violations.length > 0 ? (
              <ul style={{ margin: '6px 0 0', paddingLeft: 18 }}>
                {state.violations.map((v, index) => <li key={index}>{v}</li>)}
              </ul>
            ) : null}
          </div>
        ) : null}
        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
          <button type="button" className="btn" data-testid="ai-build-cancel" onClick={onClose} disabled={state.kind === 'building'}>
            Cancel
          </button>
          <button type="button" className="btn primary" data-testid="ai-build-submit" onClick={submit} disabled={state.kind === 'building'}>
            {state.kind === 'building' ? 'Building…' : 'Generate'}
          </button>
        </div>
      </div>
    </div>
  );
}

// E-PR4 visual-diff palette — added glows green, removed is red-dashed,
// changed is amber, unchanged keeps the default border (mirrors the design
// brief's diff-overlay styles).
const DIFF_COLORS = {
  added: '#10b981',
  removed: '#ef4444',
  changed: '#f59e0b',
  unchanged: 'var(--border, #333)',
};

function diffGraphToFlowElements(graph, catalog) {
  const nodes = (graph.nodes ?? []).map((node, index) => {
    const entry = catalog[node.type];
    const state = node.diff_state ?? 'unchanged';
    const color = DIFF_COLORS[state] ?? DIFF_COLORS.unchanged;
    const removed = state === 'removed';

    return {
      id: node.id,
      position: node.position ?? { x: index * 220, y: 0 },
      data: { label: entry?.name ?? node.type },
      style: {
        border: `2px ${removed ? 'dashed' : 'solid'} ${color}`,
        borderRadius: 8,
        padding: '8px 12px',
        background: 'var(--bg-elevated, #1a1a1a)',
        color: 'var(--text, #eee)',
        fontSize: 13,
        opacity: removed ? 0.6 : 1,
      },
      // React Flow's default node renderer applies `className` to the node
      // wrapper (arbitrary top-level keys like a `data-*` attr are dropped),
      // so this is the honored way to expose the diff state to CSS/tests.
      className: `diff-node-${state}`,
    };
  });

  const edges = (graph.connections ?? []).map((wire, index) => {
    const state = wire.diff_state ?? 'unchanged';
    const color = DIFF_COLORS[state] ?? DIFF_COLORS.unchanged;
    const removed = state === 'removed';

    return {
      id: `${wire.sourceNodeId}.${wire.sourcePortKey}->${wire.targetNodeId}.${wire.targetPortKey}-${index}`,
      source: wire.sourceNodeId,
      target: wire.targetNodeId,
      style: { stroke: color, strokeWidth: 2, ...(removed ? { strokeDasharray: '5 4' } : {}) },
      markerEnd: { type: MarkerType.ArrowClosed, color },
    };
  });

  return { nodes, edges };
}

function StudioVersions({ versionListUrl, diffUrl, publishUrl, catalogUrl, editUrl }) {
  const [state, setState] = useState({ status: 'loading', versions: [], catalog: {} });
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [diff, setDiff] = useState(null);
  const [publishTarget, setPublishTarget] = useState(null);
  const [publishing, setPublishing] = useState(false);
  const [message, setMessage] = useState(null);

  const fetchVersions = useCallback(async () => {
    const [versionsResponse, catalogResponse] = await Promise.all([
      fetch(versionListUrl, { headers: { Accept: 'application/json' } }),
      fetch(catalogUrl, { headers: { Accept: 'application/json' } }),
    ]);

    if (!versionsResponse.ok || !catalogResponse.ok) {
      throw new Error('Unexpected response');
    }

    const versionsBody = await versionsResponse.json();
    const catalogBody = await catalogResponse.json();

    return { versions: versionsBody.versions ?? [], catalog: catalogBody.catalog ?? {} };
  }, [versionListUrl, catalogUrl]);

  const applyLoaded = useCallback(({ versions, catalog }) => {
    setState({ status: 'ready', versions, catalog });

    // Default the diff selectors to the two newest versions.
    if (versions.length >= 2) {
      setTo((current) => current || String(versions[0].version));
      setFrom((current) => current || String(versions[1].version));
    }
  }, []);

  useEffect(() => {
    let cancelled = false;

    fetchVersions()
      .then((data) => {
        if (!cancelled) applyLoaded(data);
      })
      .catch(() => {
        if (!cancelled) setState({ status: 'error', versions: [], catalog: {} });
      });

    return () => {
      cancelled = true;
    };
  }, [fetchVersions, applyLoaded]);

  const compare = useCallback(async () => {
    if (!from || !to) return;

    setDiff({ status: 'loading' });
    try {
      const response = await fetch(`${diffUrl}?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`, {
        headers: { Accept: 'application/json' },
      });
      const body = await response.json().catch(() => ({}));

      if (!response.ok) {
        setDiff({ status: 'error', message: body.message ?? 'Could not compute the diff.' });

        return;
      }

      setDiff({ status: 'ready', summary: body.summary, graph: body.graph });
    } catch {
      setDiff({ status: 'error', message: 'Could not compute the diff.' });
    }
  }, [diffUrl, from, to]);

  const doPublish = useCallback(async (version) => {
    setPublishing(true);
    try {
      const response = await fetch(publishUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify({ version }),
      });
      const body = await response.json().catch(() => ({}));

      if (response.ok && body.success) {
        setMessage({ kind: 'success', text: body.message ?? `Version ${version} published.` });
        const data = await fetchVersions();
        applyLoaded(data);
      } else {
        setMessage({ kind: 'error', text: body.message ?? 'Could not publish.' });
      }
    } catch {
      setMessage({ kind: 'error', text: 'Could not publish. Try again.' });
    } finally {
      setPublishing(false);
      setPublishTarget(null);
    }
  }, [publishUrl, fetchVersions, applyLoaded]);

  if (state.status === 'loading') {
    return <div className="empty" data-testid="studio-versions-loading">Loading versions…</div>;
  }

  if (state.status === 'error') {
    return <div className="empty" data-testid="studio-versions-error">Could not load versions. Try reloading the page.</div>;
  }

  const diffElements = diff?.status === 'ready' ? diffGraphToFlowElements(diff.graph, state.catalog) : null;

  return (
    <div data-testid="flow-studio-versions" style={{ display: 'flex', flexDirection: 'column', gap: 16, height: '100%' }}>
      {message ? (
        <div
          data-testid={message.kind === 'success' ? 'publish-success' : 'publish-error'}
          role="status"
          style={{
            padding: '8px 12px',
            borderRadius: 8,
            fontSize: 13,
            border: `1px solid ${message.kind === 'success' ? '#10b981' : '#ef4444'}`,
            color: message.kind === 'success' ? '#10b981' : '#ef4444',
          }}
        >
          {message.text}
        </div>
      ) : null}

      <div style={{ display: 'flex', gap: 24, alignItems: 'flex-start', flexWrap: 'wrap' }}>
        <div style={{ flex: '1 1 320px', minWidth: 280 }}>
          <h2 style={{ fontSize: 14, margin: '0 0 8px' }}>Versions</h2>
          {state.versions.length === 0 ? (
            <div className="empty" data-testid="studio-versions-empty">No versions yet — save a draft in the editor first.</div>
          ) : (
            <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 6 }}>
              {state.versions.map((v) => (
                <li
                  key={v.version}
                  data-testid={`version-row-${v.version}`}
                  style={{
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12,
                    padding: '8px 12px', border: '1px solid var(--border, #333)', borderRadius: 8,
                  }}
                >
                  <span>
                    <b>v{v.version}</b>
                    {' · '}
                    <span data-testid={`version-status-${v.version}`}>{v.status}</span>
                    {v.published_at ? <span style={{ color: 'var(--text-secondary, #999)' }}>{' · '}{v.published_at.slice(0, 10)}</span> : null}
                  </span>
                  {v.status === 'draft' ? (
                    <button
                      type="button"
                      className="btn primary"
                      data-testid={`publish-btn-${v.version}`}
                      onClick={() => setPublishTarget(v.version)}
                    >
                      Publish
                    </button>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
          {editUrl ? (
            <a href={editUrl} data-testid="versions-edit-link" style={{ display: 'inline-block', marginTop: 10, fontSize: 13 }}>
              ← Back to editor
            </a>
          ) : null}
        </div>

        <div style={{ flex: '2 1 480px', minWidth: 360 }}>
          <h2 style={{ fontSize: 14, margin: '0 0 8px' }}>Compare versions</h2>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 10 }}>
            <select data-testid="diff-from-select" value={from} onChange={(e) => setFrom(e.target.value)} aria-label="From version">
              <option value="">from…</option>
              {state.versions.map((v) => <option key={v.version} value={v.version}>v{v.version}</option>)}
            </select>
            <span>→</span>
            <select data-testid="diff-to-select" value={to} onChange={(e) => setTo(e.target.value)} aria-label="To version">
              <option value="">to…</option>
              {state.versions.map((v) => <option key={v.version} value={v.version}>v{v.version}</option>)}
            </select>
            <button type="button" className="btn" data-testid="diff-compare-btn" onClick={compare} disabled={!from || !to}>
              Compare
            </button>
          </div>

          {diff?.status === 'error' ? (
            <div className="empty" data-testid="diff-error">{diff.message}</div>
          ) : null}

          {diff?.status === 'ready' ? (
            <>
              <div data-testid="diff-summary" style={{ display: 'flex', gap: 12, fontSize: 13, marginBottom: 8 }}>
                <span data-testid="diff-added-count" style={{ color: DIFF_COLORS.added }}>+{diff.summary.added} added</span>
                <span data-testid="diff-removed-count" style={{ color: DIFF_COLORS.removed }}>−{diff.summary.removed} removed</span>
                <span data-testid="diff-changed-count" style={{ color: DIFF_COLORS.changed }}>~{diff.summary.changed} changed</span>
              </div>
              <div data-testid="diff-canvas" style={{ width: '100%', height: 380, border: '1px solid var(--border, #333)', borderRadius: 8 }}>
                <ReactFlowProvider>
                  <ReactFlow nodes={diffElements.nodes} edges={diffElements.edges} fitView proOptions={{ hideAttribution: true }}>
                    <Background />
                    <Controls showInteractive={false} />
                  </ReactFlow>
                </ReactFlowProvider>
              </div>
            </>
          ) : null}
        </div>
      </div>

      {publishTarget !== null ? (
        <div
          data-testid="publish-modal"
          role="dialog"
          aria-modal="true"
          style={{
            position: 'fixed', inset: 0, background: 'var(--bg-overlay, rgba(0,0,0,0.6))',
            display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 50,
          }}
        >
          <div style={{ background: 'var(--bg-elevated, #1a1a1a)', color: 'var(--text, #eee)', borderRadius: 12, padding: 20, maxWidth: 420, border: '1px solid var(--border, #333)' }}>
            <h3 style={{ margin: '0 0 8px', fontSize: 16 }}>Publish v{publishTarget}?</h3>
            <p style={{ fontSize: 13, color: 'var(--text-secondary, #999)', margin: '0 0 16px' }}>
              Published versions are <b style={{ color: 'var(--text, #eee)' }}>immutable</b> and become runnable. Running instances keep their pinned version. Create a new draft to make further changes.
            </p>
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
              <button type="button" className="btn" data-testid="publish-cancel-btn" onClick={() => setPublishTarget(null)} disabled={publishing}>
                Cancel
              </button>
              <button type="button" className="btn primary" data-testid="publish-confirm-btn" onClick={() => doPublish(publishTarget)} disabled={publishing}>
                {publishing ? 'Publishing…' : `Publish v${publishTarget}`}
              </button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}

const mountPoint = document.getElementById('flow-studio-root');

if (mountPoint) {
  const {
    mode, graphUrl, flowName, editGraphUrl, catalogUrl, draftUrl, dryRunUrl, aiBuildUrl,
    versionListUrl, diffUrl, publishUrl, editUrl,
  } = mountPoint.dataset;

  createRoot(mountPoint).render(
    <StrictMode>
      {mode === 'edit' ? (
        <StudioEditor flowName={flowName} editGraphUrl={editGraphUrl} catalogUrl={catalogUrl} draftUrl={draftUrl} dryRunUrl={dryRunUrl} aiBuildUrl={aiBuildUrl} />
      ) : mode === 'versions' ? (
        <StudioVersions
          versionListUrl={versionListUrl}
          diffUrl={diffUrl}
          publishUrl={publishUrl}
          catalogUrl={catalogUrl}
          editUrl={editUrl}
        />
      ) : graphUrl ? (
        <StudioCanvas graphUrl={graphUrl} />
      ) : null}
    </StrictMode>,
  );
}
