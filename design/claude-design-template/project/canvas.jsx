// ============================================================
// Flow Studio — Canvas engine
// Pan / zoom / minimap, node drag, typed-port wiring + validation.
// Used by both Studio (editable) and Run Monitor (read-only).
// ============================================================

const NODE_W = 240;
const HEADER_H = 47;
const PORT_PAD = 8;
const PORT_ROW_H = 26;

function nodeHeight(cat) {
  const rows = Math.max((cat.inputs||[]).length, (cat.outputs||[]).length, 1);
  return HEADER_H + PORT_PAD * 2 + rows * PORT_ROW_H;
}
function inPortPos(node, cat, i) {
  return { x: node.position.x, y: node.position.y + HEADER_H + PORT_PAD + i * PORT_ROW_H + PORT_ROW_H / 2 };
}
function outPortPos(node, cat, i) {
  return { x: node.position.x + NODE_W, y: node.position.y + HEADER_H + PORT_PAD + i * PORT_ROW_H + PORT_ROW_H / 2 };
}
function findPort(nodes, nodeId, portKey, dir) {
  const node = nodes.find(n => n.id === nodeId);
  if (!node) return null;
  const cat = window.FLOW.catalogByType(node.type);
  if (!cat) return null;
  const list = dir === 'in' ? cat.inputs : cat.outputs;
  const idx = list.findIndex(p => p.key === portKey);
  if (idx < 0) return null;
  const pos = dir === 'in' ? inPortPos(node, cat, idx) : outPortPos(node, cat, idx);
  return { pos, type: list[idx].type };
}
function wirePath(x1, y1, x2, y2) {
  const dx = Math.max(40, Math.abs(x2 - x1) * 0.5);
  return `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`;
}
function portColor(type) { return (window.FLOW.PORT_TYPES[type] || window.FLOW.PORT_TYPES.any).color; }

// ---- Node card ----
function NodeCard({ node, cat, selected, state, wave, diff, editable,
                    onHeaderPointerDown, onSelect, onOutPointerDown,
                    onInPointerEnter, onInPointerLeave, connectedInputs, wiringType }) {
  const stClass = state ? `st-${state}` : '';
  const diffClass = diff ? `diff-${diff}` : '';
  const stateBadge = {
    running: { label: 'Running', cls: 'running' },
    completed: { label: 'Done', cls: 'completed' },
    failed: { label: 'Failed', cls: 'failed' },
    paused: { label: 'Awaiting', cls: 'paused' },
    'cache-hit': { label: 'Cache ⚡', cls: 'cache-hit' },
  }[state];
  const IconCmp = I[cat.icon] || I.Square;

  return (
    <div className={`node ${selected ? 'selected' : ''} ${cat.subflow ? 'subflow' : ''} ${stClass} ${diffClass}`}
         style={{ left: node.position.x, top: node.position.y, width: NODE_W, '--node-cat': cat.color }}
         onPointerDown={(e) => { e.stopPropagation(); onSelect && onSelect(node.id); }}
         data-testid={`node-${node.id}`}>
      {wave != null && <div className="node-wave">{wave}</div>}
      {stateBadge && <div className={`node-state-badge ${stateBadge.cls}`}>{stateBadge.label}</div>}
      <div className="node-header"
           onPointerDown={(e) => editable && onHeaderPointerDown && onHeaderPointerDown(e, node.id)}>
        <div className="node-icon"><IconCmp size={15}/></div>
        <div className="node-title">
          <b>{cat.name}</b>
          <small>{node.type}</small>
        </div>
        {cat.cost && <I.Zap size={13} style={{color:'var(--cat-ai)'}}/>}
      </div>
      <div className="node-ports">
        {Array.from({ length: Math.max(cat.inputs.length, cat.outputs.length) }).map((_, i) => {
          const inp = cat.inputs[i];
          const out = cat.outputs[i];
          return (
            <div className="port-row" key={i} style={{ position: 'relative', height: PORT_ROW_H }}>
              {inp && (
                <div className="port-row in" style={{ position:'absolute', inset:0, display:'flex', alignItems:'center' }}>
                  <div className={`port-dot ${connectedInputs?.has(inp.key) ? 'connected' : ''} ${
                        wiringType ? (window.FLOW.portTypesCompatible(wiringType, inp.type) ? 'compatible' : 'incompatible') : ''}`}
                       style={{ '--pc': portColor(inp.type) }}
                       onPointerEnter={() => onInPointerEnter && onInPointerEnter(node.id, inp.key, inp.type)}
                       onPointerLeave={() => onInPointerLeave && onInPointerLeave()}
                       data-testid={`port-in-${node.id}-${inp.key}`}/>
                  <span className="port-label">{inp.key}{inp.required && <span className="req">*</span>}</span>
                </div>
              )}
              {out && (
                <div className="port-row out" style={{ position:'absolute', inset:0, display:'flex', alignItems:'center', justifyContent:'flex-end' }}>
                  <span className="port-label">{out.key}</span>
                  <div className="port-dot" style={{ '--pc': portColor(out.type) }}
                       onPointerDown={(e) => { e.stopPropagation(); editable && onOutPointerDown && onOutPointerDown(e, node.id, out.key, out.type); }}
                       data-testid={`port-out-${node.id}-${out.key}`}/>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ---- Main canvas ----
function FlowCanvas({
  nodes, connections, editable = true,
  selectedNodeId, onSelectNode,
  selectedEdgeId, onSelectEdge,
  onNodesChange, onConnect, onDropNode,
  nodeStates = {}, waves = null, diffMap = null, wireFlowing = false,
  showLegend = true, showMinimap = true, focusNodeId = null,
  canvasRef,
}) {
  const wrapRef = React.useRef(null);
  const [vp, setVp] = React.useState({ x: 80, y: 40, zoom: 0.8 });
  const [drag, setDrag] = React.useState(null);      // node drag
  const [pan, setPan] = React.useState(null);        // pan
  const [wiring, setWiring] = React.useState(null);  // {fromNode, fromPort, type, cursor}
  const [hoverPort, setHoverPort] = React.useState(null);

  const screenToWorld = React.useCallback((cx, cy) => {
    const r = wrapRef.current.getBoundingClientRect();
    return { x: (cx - r.left - vp.x) / vp.zoom, y: (cy - r.top - vp.y) / vp.zoom };
  }, [vp]);

  // Fit view to nodes
  const fitView = React.useCallback(() => {
    if (!nodes.length || !wrapRef.current) return;
    const r = wrapRef.current.getBoundingClientRect();
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    nodes.forEach(n => {
      const cat = window.FLOW.catalogByType(n.type); if (!cat) return;
      minX = Math.min(minX, n.position.x); minY = Math.min(minY, n.position.y);
      maxX = Math.max(maxX, n.position.x + NODE_W); maxY = Math.max(maxY, n.position.y + nodeHeight(cat));
    });
    const pad = 80;
    const w = maxX - minX + pad * 2, h = maxY - minY + pad * 2;
    const zoom = Math.min(r.width / w, r.height / h, 1.1);
    setVp({ zoom, x: r.width / 2 - (minX + (maxX - minX) / 2) * zoom, y: r.height / 2 - (minY + (maxY - minY) / 2) * zoom });
  }, [nodes]);

  React.useEffect(() => { const t = setTimeout(fitView, 60); return () => clearTimeout(t); }, []); // eslint-disable-line

  // expose imperative api
  React.useEffect(() => {
    if (canvasRef) canvasRef.current = { fitView, zoomIn: () => zoomBy(1.2), zoomOut: () => zoomBy(0.83), reset: () => setVp({x:80,y:40,zoom:0.8}) };
  });

  // focus a node (validation click)
  React.useEffect(() => {
    if (!focusNodeId || !wrapRef.current) return;
    const n = nodes.find(x => x.id === focusNodeId); if (!n) return;
    const cat = window.FLOW.catalogByType(n.type); if (!cat) return;
    const r = wrapRef.current.getBoundingClientRect();
    const zoom = 1;
    setVp({ zoom, x: r.width/2 - (n.position.x + NODE_W/2)*zoom, y: r.height/2 - (n.position.y + nodeHeight(cat)/2)*zoom });
  }, [focusNodeId]); // eslint-disable-line

  function zoomBy(factor) {
    const r = wrapRef.current.getBoundingClientRect();
    const cx = r.width / 2, cy = r.height / 2;
    setVp(v => {
      const nz = Math.min(2.2, Math.max(0.25, v.zoom * factor));
      const wx = (cx - v.x) / v.zoom, wy = (cy - v.y) / v.zoom;
      return { zoom: nz, x: cx - wx * nz, y: cy - wy * nz };
    });
  }

  const onWheel = (e) => {
    e.preventDefault();
    const r = wrapRef.current.getBoundingClientRect();
    const cx = e.clientX - r.left, cy = e.clientY - r.top;
    setVp(v => {
      const factor = e.deltaY < 0 ? 1.08 : 0.925;
      const nz = Math.min(2.2, Math.max(0.25, v.zoom * factor));
      const wx = (cx - v.x) / v.zoom, wy = (cy - v.y) / v.zoom;
      return { zoom: nz, x: cx - wx * nz, y: cy - wy * nz };
    });
  };

  // Pan start
  const onCanvasPointerDown = (e) => {
    if (e.button !== 0) return;
    onSelectNode && onSelectNode(null);
    onSelectEdge && onSelectEdge(null);
    setPan({ sx: e.clientX, sy: e.clientY, ox: vp.x, oy: vp.y });
  };

  // Node header drag
  const onHeaderPointerDown = (e, nodeId) => {
    e.stopPropagation();
    const n = nodes.find(x => x.id === nodeId);
    onSelectNode && onSelectNode(nodeId);
    setDrag({ nodeId, sx: e.clientX, sy: e.clientY, ox: n.position.x, oy: n.position.y });
  };

  // Wiring start (from output)
  const onOutPointerDown = (e, nodeId, portKey, type) => {
    const w = screenToWorld(e.clientX, e.clientY);
    setWiring({ fromNode: nodeId, fromPort: portKey, type, cursor: w });
  };

  // global move/up
  React.useEffect(() => {
    if (!drag && !pan && !wiring) return;
    const move = (e) => {
      if (pan) setVp(v => ({ ...v, x: pan.ox + (e.clientX - pan.sx), y: pan.oy + (e.clientY - pan.sy) }));
      else if (drag) {
        const dx = (e.clientX - drag.sx) / vp.zoom, dy = (e.clientY - drag.sy) / vp.zoom;
        onNodesChange && onNodesChange(drag.nodeId, { x: Math.round(drag.ox + dx), y: Math.round(drag.oy + dy) });
      } else if (wiring) {
        setWiring(w => ({ ...w, cursor: screenToWorld(e.clientX, e.clientY) }));
      }
    };
    const up = () => {
      if (wiring && hoverPort && window.FLOW.portTypesCompatible(wiring.type, hoverPort.type)
          && hoverPort.nodeId !== wiring.fromNode) {
        onConnect && onConnect({
          sourceNodeId: wiring.fromNode, sourcePortKey: wiring.fromPort,
          targetNodeId: hoverPort.nodeId, targetPortKey: hoverPort.portKey,
        });
      }
      setDrag(null); setPan(null); setWiring(null);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    return () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
  }, [drag, pan, wiring, hoverPort, vp.zoom]); // eslint-disable-line

  // Drop from palette
  const onDrop = (e) => {
    e.preventDefault();
    const type = e.dataTransfer.getData('application/flow-node');
    if (!type || !onDropNode) return;
    const w = screenToWorld(e.clientX, e.clientY);
    onDropNode(type, { x: Math.round(w.x - NODE_W / 2), y: Math.round(w.y - 40) });
  };

  // connected inputs per node
  const connectedInputsByNode = React.useMemo(() => {
    const m = {};
    connections.forEach(c => { (m[c.targetNodeId] ||= new Set()).add(c.targetPortKey); });
    return m;
  }, [connections]);

  // wire geometry
  const wires = React.useMemo(() => connections.map(c => {
    const s = findPort(nodes, c.sourceNodeId, c.sourcePortKey, 'out');
    const t = findPort(nodes, c.targetNodeId, c.targetPortKey, 'in');
    if (!s || !t) return null;
    return { id: c.id, d: wirePath(s.pos.x, s.pos.y, t.pos.x, t.pos.y), color: portColor(s.type),
             active: wireFlowing && (nodeStates[c.sourceNodeId] === 'completed' || nodeStates[c.sourceNodeId] === 'cache-hit') };
  }).filter(Boolean), [connections, nodes, wireFlowing, nodeStates]);

  // temp wire
  const tempWire = React.useMemo(() => {
    if (!wiring) return null;
    const s = findPort(nodes, wiring.fromNode, wiring.fromPort, 'out');
    if (!s) return null;
    const compat = hoverPort ? window.FLOW.portTypesCompatible(wiring.type, hoverPort.type) : true;
    return { d: wirePath(s.pos.x, s.pos.y, wiring.cursor.x, wiring.cursor.y), color: portColor(wiring.type), compat };
  }, [wiring, hoverPort, nodes]);

  return (
    <div ref={wrapRef}
         className={`canvas-wrap ${pan ? 'panning' : ''} ${wiring ? 'wiring' : ''}`}
         onPointerDown={onCanvasPointerDown}
         onWheel={onWheel}
         onDragOver={(e) => e.preventDefault()}
         onDrop={onDrop}
         data-testid="flow-canvas">
      <div className="canvas-world" style={{ transform: `translate(${vp.x}px, ${vp.y}px) scale(${vp.zoom})` }}>
        <svg className="canvas-svg">
          {wires.map(w => (
            <g key={w.id}>
              <path className="wire-hit" d={w.d} onPointerDown={(e) => { e.stopPropagation(); onSelectEdge && onSelectEdge(w.id); }} />
              <path className={`wire ${selectedEdgeId === w.id ? 'selected' : ''} ${w.active ? 'flowing' : ''}`}
                    d={w.d} stroke={selectedEdgeId === w.id ? 'var(--accent)' : w.color} />
            </g>
          ))}
          {tempWire && (
            <path className={`wire-temp ${tempWire.compat ? '' : 'invalid'}`} d={tempWire.d}
                  stroke={tempWire.compat ? tempWire.color : 'var(--status-failed)'} />
          )}
        </svg>

        {nodes.map(n => {
          const cat = window.FLOW.catalogByType(n.type);
          if (!cat) return null;
          return (
            <NodeCard key={n.id} node={n} cat={cat}
                      selected={selectedNodeId === n.id}
                      state={nodeStates[n.id]}
                      wave={waves ? waves[n.id] : null}
                      diff={diffMap ? diffMap[n.id] : null}
                      editable={editable}
                      connectedInputs={connectedInputsByNode[n.id]}
                      wiringType={wiring?.type}
                      onSelect={onSelectNode}
                      onHeaderPointerDown={onHeaderPointerDown}
                      onOutPointerDown={onOutPointerDown}
                      onInPointerEnter={(nodeId, portKey, type) => setHoverPort({ nodeId, portKey, type })}
                      onInPointerLeave={() => setHoverPort(null)} />
          );
        })}
      </div>

      {/* invalid connection tooltip */}
      {wiring && hoverPort && !window.FLOW.portTypesCompatible(wiring.type, hoverPort.type) && (
        <div style={{ position:'fixed', left:0, top:0, transform:'translate(-9999px,0)' }} />
      )}

      {showLegend && (
        <div className="port-legend">
          <b>Port types</b>
          {Object.entries(window.FLOW.PORT_TYPES).map(([k, v]) => (
            <div className="port-legend-row" key={k} style={{ color: v.color }}>
              <span className="port-legend-swatch"/>
              <span style={{ color: 'var(--text-secondary)' }}>{v.label}</span>
            </div>
          ))}
        </div>
      )}

      {/* zoom controls */}
      <div className="canvas-controls">
        <div className="canvas-ctrl-group">
          <button className="canvas-ctrl" onClick={() => zoomBy(1.2)} title="Zoom in" data-testid="zoom-in"><I.ZoomIn size={15}/></button>
          <div className="zoom-label">{Math.round(vp.zoom * 100)}%</div>
          <button className="canvas-ctrl" onClick={() => zoomBy(0.83)} title="Zoom out" data-testid="zoom-out"><I.ZoomOut size={15}/></button>
        </div>
        <div className="canvas-ctrl-group">
          <button className="canvas-ctrl" onClick={fitView} title="Fit view" data-testid="fit-view"><I.Fit size={15}/></button>
        </div>
      </div>

      {showMinimap && (
        <Minimap nodes={nodes} vp={vp} wrapRef={wrapRef} nodeStates={nodeStates}/>
      )}
    </div>
  );
}

function Minimap({ nodes, vp, wrapRef, nodeStates }) {
  const bounds = React.useMemo(() => {
    if (!nodes.length) return { minX: 0, minY: 0, w: 100, h: 100 };
    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
    nodes.forEach(n => {
      const cat = window.FLOW.catalogByType(n.type); if (!cat) return;
      minX = Math.min(minX, n.position.x); minY = Math.min(minY, n.position.y);
      maxX = Math.max(maxX, n.position.x + NODE_W); maxY = Math.max(maxY, n.position.y + nodeHeight(cat));
    });
    const pad = 60;
    return { minX: minX - pad, minY: minY - pad, w: (maxX - minX) + pad * 2, h: (maxY - minY) + pad * 2 };
  }, [nodes]);

  const r = wrapRef.current?.getBoundingClientRect();
  const viewBox = `${bounds.minX} ${bounds.minY} ${bounds.w} ${bounds.h}`;
  // viewport rect in world coords
  const vpRect = r ? { x: -vp.x / vp.zoom, y: -vp.y / vp.zoom, w: r.width / vp.zoom, h: r.height / vp.zoom } : null;
  const stColor = { running: 'var(--status-running)', completed: 'var(--status-success)', failed: 'var(--status-failed)', paused: 'var(--status-paused)', 'cache-hit': 'var(--st-cache)' };

  return (
    <div className="minimap">
      <svg viewBox={viewBox} preserveAspectRatio="xMidYMid meet">
        {nodes.map(n => {
          const cat = window.FLOW.catalogByType(n.type); if (!cat) return null;
          return <rect key={n.id} x={n.position.x} y={n.position.y} width={NODE_W} height={nodeHeight(cat)}
                       rx="8" fill={stColor[nodeStates[n.id]] || 'var(--text-tertiary)'} opacity="0.7"/>;
        })}
        {vpRect && <rect className="minimap-viewport" x={vpRect.x} y={vpRect.y} width={vpRect.w} height={vpRect.h} rx="4"/>}
      </svg>
    </div>
  );
}

Object.assign(window, { FlowCanvas, NodeCard, Minimap, NODE_W, nodeHeight, inPortPos, outPortPos, findPort, wirePath, portColor });
Object.assign(window.FLOW, { NODE_W, nodeHeight, inPortPos, outPortPos, findPort, wirePath, portColor });
