import { StrictMode, useCallback, useEffect, useMemo, useState } from 'react';
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
        border: '1px solid var(--border-default, #333)',
        borderRadius: 8,
        padding: '8px 12px',
        background: 'var(--bg-elevated, #1a1a1a)',
        color: 'var(--text-primary, #eee)',
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
        border: `1px solid ${invalid ? '#ef4444' : 'var(--border-default, #333)'}`,
        borderRadius: 8,
        padding: '8px 14px',
        background: 'var(--bg-elevated, #1a1a1a)',
        color: 'var(--text-primary, #eee)',
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

  // Tracks non-`multiple` input ports already fed by an earlier wire in
  // this same list, so a loaded graph with a duplicate fan-in renders the
  // SECOND wire red exactly like drawing it interactively would (onConnect
  // applies the identical rule against the live edge list).
  const wiredInputs = new Set();

  const edges = graph.connections.map((wire) => {
    const sourceEntry = catalog[nodes.find((n) => n.id === wire.sourceNodeId)?.data.nodeType];
    const targetEntry = catalog[nodes.find((n) => n.id === wire.targetNodeId)?.data.nodeType];
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

function StudioEditorCanvas({ editGraphUrl, catalogUrl, draftUrl }) {
  const [state, setState] = useState({ status: 'loading', nodes: [], edges: [], catalog: {} });
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
        ? current.edges.filter((edge) => !removedIds.has(edge.source) && !removedIds.has(edge.target))
        : current.edges;

      return { ...current, nodes, edges };
    });
  }, []);

  const onEdgesChange = useCallback((changes) => {
    setState((current) => ({ ...current, edges: applyEdgeChanges(changes, current.edges) }));
  }, []);

  const onDrop = useCallback(
    (event) => {
      event.preventDefault();
      const type = event.dataTransfer.getData('application/flow-node-type');
      if (!type) return;

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

    // Defense-in-depth: canSave already gates the button on !hasConfigError
    // (computed via jsonFieldError, a SEPARATE try/catch'd JSON.parse over
    // the same fields), so this should never throw in the normal flow —
    // but the two checks are not literally the same call, so a future
    // drift between them must not surface as an uncaught SyntaxError.
    let nodes;
    try {
      nodes = state.nodes.map((node) => {
        // Start from the full current config (preserves any key not backed
        // by a declared input port — e.g. config set by another tool) and
        // only convert the JSON-typed DECLARED ports, which the inspector
        // stores as raw text while editing.
        const config = { ...node.data.config };
        node.data.inputs.forEach((port) => {
          if (port.type === 'json' && typeof config[port.key] === 'string') {
            config[port.key] = JSON.parse(config[port.key]);
          }
        });

        return { id: node.id, type: node.data.nodeType, config, position: node.position };
      });
    } catch {
      setSaveStatus({ kind: 'error', message: 'One or more config fields contain invalid JSON.' });

      return;
    }

    const connections = state.edges.map((edge) => ({
      sourceNodeId: edge.source,
      sourcePortKey: edge.sourceHandle,
      targetNodeId: edge.target,
      targetPortKey: edge.targetHandle,
    }));

    fetch(draftUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ schema_version: 1, kind: 'laravel-flow', metadata: {}, nodes, connections }),
    })
      .then(async (response) => {
        const payload = await response.json();

        if (!response.ok) {
          const violations = payload.data?.violations;
          setSaveStatus({ kind: 'error', message: violations ? `${payload.message} ${violations.join(' | ')}` : payload.message });

          return;
        }

        setSaveStatus({ kind: 'success', message: payload.message });
      })
      .catch(() => setSaveStatus({ kind: 'error', message: 'Network error while saving.' }));
  }, [state.nodes, state.edges, draftUrl]);

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
      </div>
      <InspectorPanel node={selectedNode} onChangeConfig={onChangeConfig} onDeleteNode={onDeleteNode} />
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

const mountPoint = document.getElementById('flow-studio-root');

if (mountPoint) {
  const { mode, graphUrl, flowName, editGraphUrl, catalogUrl, draftUrl } = mountPoint.dataset;

  createRoot(mountPoint).render(
    <StrictMode>
      {mode === 'edit' ? (
        <StudioEditor flowName={flowName} editGraphUrl={editGraphUrl} catalogUrl={catalogUrl} draftUrl={draftUrl} />
      ) : graphUrl ? (
        <StudioCanvas graphUrl={graphUrl} />
      ) : null}
    </StrictMode>,
  );
}
