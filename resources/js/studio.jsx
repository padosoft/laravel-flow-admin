import { StrictMode, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ReactFlow, ReactFlowProvider, Background, MarkerType } from '@xyflow/react';
import '@xyflow/react/dist/style.css';

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

const mountPoint = document.getElementById('flow-studio-root');

if (mountPoint) {
  const graphUrl = mountPoint.dataset.graphUrl;

  createRoot(mountPoint).render(
    <StrictMode>{graphUrl ? <StudioCanvas graphUrl={graphUrl} /> : null}</StrictMode>,
  );
}
