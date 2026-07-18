// ============================================================
// Flow Studio — editor page (palette + canvas + inspector)
// ============================================================

// ---- Validation ----
function validateGraph(nodes, connections) {
  const errors = [];
  const byId = Object.fromEntries(nodes.map(n => [n.id, n]));
  // unwired required inputs
  nodes.forEach(n => {
    const cat = window.FLOW.catalogByType(n.type); if (!cat) return;
    cat.inputs.forEach(inp => {
      if (inp.required) {
        const wired = connections.some(c => c.targetNodeId === n.id && c.targetPortKey === inp.key);
        if (!wired) errors.push({ severity: 'error', nodeId: n.id, message: `${cat.name}: required input “${inp.key}” is not wired` });
      }
    });
  });
  // type mismatches
  connections.forEach(c => {
    const s = window.FLOW.findPort(nodes, c.sourceNodeId, c.sourcePortKey, 'out');
    const t = window.FLOW.findPort(nodes, c.targetNodeId, c.targetPortKey, 'in');
    if (s && t && !window.FLOW.portTypesCompatible(s.type, t.type)) {
      errors.push({ severity: 'error', nodeId: c.targetNodeId, message: `Type mismatch: ${s.type} → ${t.type}` });
    }
  });
  // cycle detection (DFS)
  const adj = {}; connections.forEach(c => (adj[c.sourceNodeId] ||= []).push(c.targetNodeId));
  const WHITE = 0, GRAY = 1, BLACK = 2; const color = {};
  nodes.forEach(n => color[n.id] = WHITE);
  let cyclic = false;
  function dfs(u) { color[u] = GRAY; (adj[u]||[]).forEach(v => { if (color[v] === GRAY) cyclic = true; else if (color[v] === WHITE) dfs(v); }); color[u] = BLACK; }
  nodes.forEach(n => { if (color[n.id] === WHITE) dfs(n.id); });
  if (cyclic) errors.push({ severity: 'error', nodeId: null, message: 'Cycle detected in graph — flows must be acyclic' });
  // orphan warning (non-start node with no inputs wired)
  nodes.forEach(n => {
    const cat = window.FLOW.catalogByType(n.type); if (!cat || cat.type === 'control.start') return;
    if (cat.inputs.length && !connections.some(c => c.targetNodeId === n.id)) {
      errors.push({ severity: 'warn', nodeId: n.id, message: `${cat.name} has no incoming connections` });
    }
  });
  return errors;
}

// ---- Dry-run wave assignment (topological levels) ----
function computeWaves(nodes, connections) {
  const indeg = {}; nodes.forEach(n => indeg[n.id] = 0);
  connections.forEach(c => { indeg[c.targetNodeId] = (indeg[c.targetNodeId]||0) + 1; });
  const adj = {}; connections.forEach(c => (adj[c.sourceNodeId] ||= []).push(c.targetNodeId));
  const level = {}; let queue = nodes.filter(n => indeg[n.id] === 0).map(n => n.id);
  queue.forEach(id => level[id] = 1);
  const q = [...queue];
  while (q.length) {
    const u = q.shift();
    (adj[u]||[]).forEach(v => { indeg[v]--; level[v] = Math.max(level[v]||0, (level[u]||0) + 1); if (indeg[v] === 0) q.push(v); });
  }
  return level;
}

// ---- Palette ----
function NodePalette({ onDragStartNode }) {
  const [q, setQ] = React.useState('');
  const groups = React.useMemo(() => {
    const filtered = window.FLOW.NODE_CATALOG.filter(n =>
      !q || n.name.toLowerCase().includes(q.toLowerCase()) || n.type.includes(q.toLowerCase()));
    const g = {};
    filtered.forEach(n => (g[n.category] ||= []).push(n));
    return g;
  }, [q]);
  return (
    <div className="palette-panel">
      <div className="palette-search">
        <div style={{ position:'relative' }}>
          <I.Search size={13} style={{ position:'absolute', left:9, top:'50%', transform:'translateY(-50%)', color:'var(--text-tertiary)' }}/>
          <input className="input" style={{ paddingLeft:28 }} placeholder="Search nodes…"
                 value={q} onChange={e => setQ(e.target.value)} data-testid="palette-search"/>
        </div>
      </div>
      <div className="palette-scroll">
        {Object.keys(groups).length === 0 && <div className="empty">No nodes match</div>}
        {Object.entries(groups).map(([cat, items]) => (
          <div className="palette-group" key={cat}>
            <div className="palette-group-label">{cat}</div>
            {items.map(n => {
              const IconCmp = I[n.icon] || I.Square;
              return (
                <div key={n.type} className="palette-node" draggable
                     style={{ '--node-cat': n.color }}
                     onDragStart={(e) => { e.dataTransfer.setData('application/flow-node', n.type); e.dataTransfer.effectAllowed = 'copy'; }}
                     data-testid={`palette-${n.type}`}>
                  <div className="palette-node-icon"><IconCmp size={15}/></div>
                  <div className="palette-node-body">
                    <div className="palette-node-name">{n.name}</div>
                    <div className="palette-node-ports">{n.inputs.length} in · {n.outputs.length} out</div>
                  </div>
                </div>
              );
            })}
          </div>
        ))}
      </div>
    </div>
  );
}

// ---- Inspector ----
function Inspector({ node, cat, connections, onClose, onChangeConfig, onDelete }) {
  if (!node || !cat) {
    return (
      <div className="inspector">
        <div className="inspector-body">
          <div className="empty" style={{ padding: '48px 16px' }}>
            <I.Compass size={26} style={{ color:'var(--text-tertiary)', marginBottom:10 }}/>
            <div>Select a node to edit its properties, retry policy, and cache settings.</div>
          </div>
        </div>
      </div>
    );
  }
  const IconCmp = I[cat.icon] || I.Square;
  const errs = validateGraph([node], connections).filter(e => e.nodeId === node.id);
  return (
    <div className="inspector" data-testid="inspector">
      <div className="inspector-head">
        <div className="node-icon" style={{ '--node-cat': cat.color }}><IconCmp size={16}/></div>
        <div style={{ flex:1, minWidth:0 }}>
          <div style={{ fontWeight:600, fontSize:13 }}>{cat.name}</div>
          <div className="tertiary mono" style={{ fontSize:10.5 }}>{node.id} · {node.type}</div>
        </div>
        <button className="iconbtn" onClick={onClose}><I.X size={15}/></button>
      </div>
      <div className="inspector-body">
        <div className="insp-section">
          <div className="insp-section-title">Inputs</div>
          {cat.inputs.length === 0 && <div className="tertiary" style={{ fontSize:12 }}>No inputs</div>}
          {cat.inputs.map(inp => {
            const wired = connections.some(c => c.targetNodeId === node.id && c.targetPortKey === inp.key);
            return (
              <div className="insp-field" key={inp.key} style={{ '--pc': window.FLOW.portColor(inp.type) }}>
                <label>
                  {inp.key}{inp.required && <span className="req">*</span>}
                  <span className="port-tag">{inp.type}</span>
                </label>
                {wired ? (
                  <div className="input mono" style={{ display:'flex', alignItems:'center', gap:6, color:'var(--text-tertiary)' }}>
                    <I.Link size={12}/> wired from upstream
                  </div>
                ) : (
                  <input className="input" placeholder={inp.required ? 'Required — set a value or wire it' : 'Optional'}
                         defaultValue={node.config[inp.key] || ''}
                         onChange={e => onChangeConfig(inp.key, e.target.value)}/>
                )}
                {inp.required && !wired && !node.config[inp.key] && (
                  <div className="insp-error"><I.AlertTriangle size={11}/> Required input is empty</div>
                )}
              </div>
            );
          })}
        </div>

        <div className="insp-section">
          <div className="insp-section-title">Outputs</div>
          {cat.outputs.map(out => (
            <div className="insp-field" key={out.key} style={{ '--pc': window.FLOW.portColor(out.type), marginBottom:6 }}>
              <label style={{ marginBottom:0 }}>{out.key}<span className="port-tag">{out.type}</span></label>
            </div>
          ))}
        </div>

        <div className="insp-section">
          <div className="insp-section-title">Retry &amp; timeout</div>
          <div className="insp-field">
            <label>Max attempts</label>
            <input className="input" type="number" defaultValue={node.config.retries ?? 1} min="0" max="10"
                   onChange={e => onChangeConfig('retries', e.target.value)}/>
          </div>
          <div className="insp-field">
            <label>Timeout (s)</label>
            <input className="input" type="number" defaultValue={node.config.timeout ?? 30}
                   onChange={e => onChangeConfig('timeout', e.target.value)}/>
          </div>
        </div>

        <div className="insp-section">
          <div className="insp-section-title">Execution</div>
          <div className="insp-toggle-row">
            <div>
              <div style={{ fontSize:12.5, fontWeight:500 }}>Cache result</div>
              <div className="tertiary" style={{ fontSize:11 }}>Reuse output for identical inputs</div>
            </div>
            <div className={`toggle ${node.config.cache ? 'on' : ''}`} onClick={() => onChangeConfig('cache', !node.config.cache)} data-testid="cache-toggle"/>
          </div>
          {cat.cost && (
            <div style={{ marginTop:10, padding:'8px 10px', background:'var(--bg-subtle)', borderRadius:6, fontSize:11.5, color:'var(--text-secondary)' }}>
              <I.Zap size={11} style={{ color:'var(--cat-ai)', verticalAlign:'-1px' }}/> This node incurs LLM token cost. Est. €0.04–0.08 / run.
            </div>
          )}
        </div>

        <button className="btn danger" style={{ width:'100%', justifyContent:'center' }} onClick={onDelete} data-testid="delete-node">
          <I.Trash size={13}/> Delete node
        </button>
      </div>
    </div>
  );
}

// ---- Studio page ----
function StudioPage({ onOpenBuilder, toast }) {
  const initial = React.useMemo(() => window.FLOW.makeRefundFlow(), []);
  const [nodes, setNodes] = React.useState(initial.nodes);
  const [connections, setConnections] = React.useState(initial.connections);
  const [selNode, setSelNode] = React.useState(null);
  const [selEdge, setSelEdge] = React.useState(null);
  const [showValidation, setShowValidation] = React.useState(false);
  const [dryRun, setDryRun] = React.useState(false);
  const [publishOpen, setPublishOpen] = React.useState(false);
  const [focusNode, setFocusNode] = React.useState(null);
  const canvasRef = React.useRef(null);

  // history
  const history = React.useRef({ past: [], future: [] });
  const snapshot = () => { history.current.past.push({ nodes: JSON.parse(JSON.stringify(nodes)), connections: JSON.parse(JSON.stringify(connections)) }); history.current.future = []; };
  const undo = () => {
    const h = history.current; if (!h.past.length) return;
    h.future.push({ nodes, connections });
    const prev = h.past.pop(); setNodes(prev.nodes); setConnections(prev.connections);
  };
  const redo = () => {
    const h = history.current; if (!h.future.length) return;
    h.past.push({ nodes, connections });
    const nxt = h.future.pop(); setNodes(nxt.nodes); setConnections(nxt.connections);
  };

  const errors = React.useMemo(() => validateGraph(nodes, connections), [nodes, connections]);
  const errorCount = errors.filter(e => e.severity === 'error').length;
  const waves = React.useMemo(() => dryRun ? computeWaves(nodes, connections) : null, [dryRun, nodes, connections]);

  // dry-run cost estimate
  const dryCost = React.useMemo(() => {
    let total = 0; const perNode = [];
    nodes.forEach(n => { const cat = window.FLOW.catalogByType(n.type); if (cat?.cost) { const c = 0.04 + Math.random()*0.04; total += c; perNode.push({ name: cat.name, cost: c, tokens: Math.round(800 + Math.random()*2400) }); } });
    return { total, perNode };
  }, [dryRun]); // eslint-disable-line

  const selectedNode = nodes.find(n => n.id === selNode);
  const selectedCat = selectedNode && window.FLOW.catalogByType(selectedNode.type);

  const moveNode = (id, pos) => setNodes(ns => ns.map(n => n.id === id ? { ...n, position: pos } : n));
  const addNode = (type, pos) => {
    snapshot();
    const id = 'n' + Math.random().toString(36).slice(2, 7);
    setNodes(ns => [...ns, { id, type, position: pos, config: {} }]);
    setSelNode(id);
  };
  const connect = (conn) => {
    // single connection per target input
    setConnections(cs => {
      snapshot();
      const filtered = cs.filter(c => !(c.targetNodeId === conn.targetNodeId && c.targetPortKey === conn.targetPortKey));
      return [...filtered, { id: 'c' + Math.random().toString(36).slice(2, 7), ...conn }];
    });
    toast?.push({ title: 'Connected', body: `${conn.sourcePortKey} → ${conn.targetPortKey}` });
  };
  const deleteNode = () => {
    if (!selNode) return; snapshot();
    setConnections(cs => cs.filter(c => c.sourceNodeId !== selNode && c.targetNodeId !== selNode));
    setNodes(ns => ns.filter(n => n.id !== selNode));
    setSelNode(null);
  };
  const deleteEdge = () => { if (!selEdge) return; snapshot(); setConnections(cs => cs.filter(c => c.id !== selEdge)); setSelEdge(null); };
  const changeConfig = (key, val) => setNodes(ns => ns.map(n => n.id === selNode ? { ...n, config: { ...n.config, [key]: val } } : n));

  // keyboard
  React.useEffect(() => {
    const onKey = (e) => {
      if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
      if ((e.key === 'Delete' || e.key === 'Backspace')) { if (selNode) deleteNode(); else if (selEdge) deleteEdge(); }
      else if ((e.metaKey || e.ctrlKey) && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
      else if ((e.metaKey || e.ctrlKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo(); }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }); // eslint-disable-line

  return (
    <div className="studio-shell" data-screen-label="Studio">
      {/* Topbar */}
      <div className="studio-topbar">
        <div className="flow-name">
          <I.ChevronLeft size={16} style={{ color:'var(--text-tertiary)', cursor:'pointer' }}/>
          {initial.name}
          <span className="version-chip published">v{initial.version} · published</span>
        </div>
        <div className="divider"/>
        <div className="tool-group">
          <button className="tool-btn" onClick={undo} title="Undo (⌘Z)"><I.Undo size={16}/></button>
          <button className="tool-btn" onClick={redo} title="Redo (⌘⇧Z)"><I.Redo size={16}/></button>
        </div>
        <div className="divider"/>
        <button className="btn" onClick={() => setShowValidation(v => !v)} data-testid="validate-btn">
          {errorCount > 0
            ? <><I.AlertTriangle size={13} style={{ color:'var(--status-failed)' }}/> {errorCount} issue{errorCount>1?'s':''}</>
            : <><I.Check size={13} style={{ color:'var(--status-success)' }}/> Valid</>}
        </button>
        <button className={`btn ${dryRun ? 'primary' : ''}`} onClick={() => setDryRun(d => !d)} data-testid="dryrun-btn">
          <I.Beaker size={13}/> {dryRun ? 'Exit dry-run' : 'Dry-run'}
        </button>
        <div className="topbar-spacer" style={{ flex:1 }}/>
        <button className="btn ghost" onClick={onOpenBuilder}><I.Wand size={13}/> AI Builder</button>
        <button className="btn"><I.Save size={13}/> Save draft</button>
        <button className="btn primary" onClick={() => setPublishOpen(true)} disabled={errorCount>0} data-testid="publish-btn">
          <I.Rocket size={13}/> Publish
        </button>
      </div>

      {/* 3-column body */}
      <div className="studio">
        <NodePalette/>
        <div style={{ position:'relative', minHeight:0, display:'flex', flexDirection:'column' }}>
          {dryRun && (
            <div style={{ position:'absolute', top:12, left:'50%', transform:'translateX(-50%)', zIndex:11,
                          background:'var(--status-paused-bg)', color:'var(--status-paused)', border:'1px solid var(--status-paused)',
                          padding:'5px 14px', borderRadius:999, fontSize:12, fontWeight:600, display:'flex', alignItems:'center', gap:6 }}>
              <I.Beaker size={13}/> Dry run — nothing was written
            </div>
          )}
          <FlowCanvas
            nodes={nodes} connections={connections} editable={!dryRun}
            selectedNodeId={selNode} onSelectNode={(id) => { setSelNode(id); setSelEdge(null); }}
            selectedEdgeId={selEdge} onSelectEdge={(id) => { setSelEdge(id); setSelNode(null); }}
            onNodesChange={moveNode} onConnect={connect} onDropNode={addNode}
            waves={waves} focusNodeId={focusNode} canvasRef={canvasRef}/>

          {dryRun && (
            <div style={{ position:'absolute', top:12, right:12, width:230, zIndex:11,
                          background:'var(--bg-elevated)', border:'1px solid var(--border)', borderRadius:8, padding:14, boxShadow:'var(--shadow-md)' }}>
              <div style={{ fontSize:11, textTransform:'uppercase', letterSpacing:'0.05em', color:'var(--text-tertiary)', fontWeight:600, marginBottom:10 }}>Estimated cost</div>
              <div style={{ fontSize:24, fontWeight:600, fontFamily:'var(--font-sans)', letterSpacing:'-0.02em' }}>€{dryCost.total.toFixed(3)}</div>
              <div className="tertiary" style={{ fontSize:11, marginBottom:12 }}>per run · {dryCost.perNode.length} AI node(s)</div>
              {dryCost.perNode.map((p, i) => (
                <div key={i} style={{ display:'flex', justifyContent:'space-between', fontSize:11.5, padding:'3px 0', borderTop:'1px solid var(--border)' }}>
                  <span className="muted">{p.name}</span>
                  <span className="mono">€{p.cost.toFixed(3)}</span>
                </div>
              ))}
              <div style={{ marginTop:10, fontSize:11, color:'var(--text-secondary)' }}>~2.4s est. duration · 3 writes skipped</div>
            </div>
          )}

          {showValidation && (
            <div className="validation-bar">
              <div className="validation-head" onClick={() => setShowValidation(false)}>
                <I.AlertTriangle size={14} style={{ color: errorCount ? 'var(--status-failed)' : 'var(--status-success)' }}/>
                <b style={{ fontSize:12.5 }}>Validation</b>
                <span className="badge outline">{errors.length}</span>
                <div style={{ flex:1 }}/>
                <I.ChevronDown size={14}/>
              </div>
              <div className="validation-list">
                {errors.length === 0 && <div className="empty" style={{ padding:'20px' }}>No problems — graph is valid ✓</div>}
                {errors.map((e, i) => (
                  <div key={i} className={`validation-item ${e.severity}`}
                       onClick={() => { if (e.nodeId) { setFocusNode(e.nodeId); setSelNode(e.nodeId); setTimeout(()=>setFocusNode(null), 100); } }}>
                    <span className="sev">{e.severity === 'error' ? <I.AlertTriangle size={14}/> : <I.Info size={14}/>}</span>
                    <span>{e.message}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
        <Inspector node={selectedNode} cat={selectedCat} connections={connections}
                   onClose={() => setSelNode(null)} onChangeConfig={changeConfig} onDelete={deleteNode}/>
      </div>

      {/* Publish modal */}
      <Modal open={publishOpen} onClose={() => setPublishOpen(false)}
             title="Publish flow" sub="Published versions are immutable and become runnable."
             footer={<>
               <button className="btn" onClick={() => setPublishOpen(false)}>Cancel</button>
               <button className="btn primary" onClick={() => { setPublishOpen(false); toast?.push({ title:'Published', body:`${initial.name} v${initial.version+1}` }); }}>
                 <I.Rocket size={13}/> Publish v{initial.version + 1}
               </button>
             </>}>
        <div style={{ display:'flex', gap:10, padding:'12px 14px', background:'var(--status-paused-bg)', borderRadius:8, marginBottom:14 }}>
          <I.Info size={16} style={{ color:'var(--status-paused)', flexShrink:0, marginTop:1 }}/>
          <div style={{ fontSize:12.5, color:'var(--text-secondary)' }}>
            Once published, <b style={{ color:'var(--text)' }}>v{initial.version + 1}</b> cannot be edited. Running instances keep their pinned version. Create a new draft to make further changes.
          </div>
        </div>
        <dl className="kv">
          <dt>Nodes</dt><dd>{nodes.length}</dd>
          <dt>Connections</dt><dd>{connections.length}</dd>
          <dt>Validation</dt><dd style={{ color: errorCount ? 'var(--status-failed)' : 'var(--status-success)' }}>{errorCount ? `${errorCount} errors` : 'Passed'}</dd>
        </dl>
      </Modal>
    </div>
  );
}

window.StudioPage = StudioPage;
window.validateGraph = validateGraph;
