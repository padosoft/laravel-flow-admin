// ============================================================
// Flow Studio — Run detail live monitor (canvas + side tabs)
// ============================================================

function RunMonitor({ onBack, toast, autoRefresh }) {
  const [progress, setProgress] = React.useState(3); // 0..7, paused at approval (index 3)
  const [tab, setTab] = React.useState('timeline');
  const [tickerOpen, setTickerOpen] = React.useState(true);
  const { flow, states } = React.useMemo(() => window.FLOW.makeRefundRun(progress), [progress]);

  const total = flow.nodes.length;
  const done = Object.values(states).filter(s => s === 'completed' || s === 'cache-hit').length;
  const pct = Math.round((done / total) * 100);
  const paused = Object.values(states).includes('paused');
  const [elapsed, setElapsed] = React.useState(642);
  const [cost, setCost] = React.useState(0.00);

  React.useEffect(() => {
    if (!autoRefresh) return;
    const id = setInterval(() => setElapsed(e => e + 1000), 1000);
    return () => clearInterval(id);
  }, [autoRefresh]);

  const steps = [
    { name: 'Start', status: 'completed', dur: '2ms' },
    { name: 'DB Query', status: 'completed', dur: '128ms' },
    { name: 'LLM Prompt', status: 'cache-hit', dur: '0ms ⚡' },
    { name: 'Approval Gate', status: 'paused', dur: '—' },
    { name: 'HTTP Request', status: 'pending', dur: '—' },
    { name: 'Emit Webhook', status: 'pending', dur: '—' },
    { name: 'End', status: 'pending', dur: '—' },
  ];

  const tabs = [
    { k: 'timeline', label: 'Timeline' },
    { k: 'payloads', label: 'Payloads' },
    { k: 'audit', label: 'Audit' },
    { k: 'impact', label: 'Business impact' },
    { k: 'errors', label: 'Errors' },
  ];

  return (
    <div className="studio-shell" data-screen-label="Run Monitor">
      <div className="monitor-header">
        <button className="btn ghost sm" onClick={onBack}><I.ChevronLeft size={13}/> Runs</button>
        <div style={{ display:'flex', alignItems:'center', gap:10 }}>
          <b style={{ fontSize:14 }}>{flow.name}</b>
          <span className="version-chip">v{flow.version}</span>
          {paused ? <span className="badge paused"><span className="dot"/>Awaiting approval</span>
                  : <span className="badge running"><span className="dot"/>Running</span>}
        </div>
        <div className="monitor-progress-track" style={{ maxWidth:280 }}>
          <div className="monitor-progress-fill" style={{ width:`${pct}%` }}/>
        </div>
        <div className="monitor-stat"><small>Nodes</small><b>{done}/{total}</b></div>
        <div className="monitor-stat"><small>Elapsed</small><b>{(elapsed/1000).toFixed(1)}s</b></div>
        <div className="monitor-stat"><small>Cost</small><b>€{cost.toFixed(2)}</b></div>
        <div className="topbar-spacer" style={{ flex:1 }}/>
        {paused && <button className="btn primary" onClick={() => { setProgress(p => Math.min(6, p+1)); toast?.push({ title:'Approved', body:'Flow resumed' }); }}><I.Check size={13}/> Approve &amp; resume</button>}
        <button className="btn"><I.Replay size={13}/> Replay</button>
        <button className="btn danger"><I.Cancel size={13}/> Cancel</button>
      </div>

      <div className="monitor">
        <div className="monitor-canvas">
          <FlowCanvas nodes={flow.nodes} connections={flow.connections} editable={false}
                      nodeStates={states} wireFlowing={true} showLegend={false}
                      selectedNodeId={null} onSelectNode={()=>{}}/>
          {/* event ticker */}
          <div className="event-ticker" style={{ maxHeight: tickerOpen ? 160 : 33 }}>
            <div className="event-ticker-head" onClick={() => setTickerOpen(o => !o)}>
              <I.Activity size={13} style={{ color:'var(--accent)' }}/>
              <b style={{ fontFamily:'var(--font-sans)', fontSize:12 }}>Live events</b>
              <span className="live-pill" style={{ marginLeft:4 }}><span className="pulse"/>tailing</span>
              <div style={{ flex:1 }}/>
              <I.ChevronDown size={13} style={{ transform: tickerOpen ? 'none':'rotate(180deg)' }}/>
            </div>
            <div className="event-ticker-body">
              {window.FLOW.RUN_EVENTS.map((e, i) => (
                <div key={i} className={`event-line ${e.level === 'ok' ? 'ok' : e.level === 'warn' ? 'warn' : ''}`}>
                  <span className="time">{e.t}</span>
                  <span className="node">{e.node}</span>
                  <span className="msg">{e.msg}</span>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="monitor-side">
          <div className="monitor-side-tabs">
            {tabs.map(t => (
              <div key={t.k} className={`tab ${tab === t.k ? 'active' : ''}`} onClick={() => setTab(t.k)}>{t.label}</div>
            ))}
          </div>
          <div className="monitor-side-body">
            {tab === 'timeline' && (
              <div className="step-list">
                {steps.map((s, i) => (
                  <div key={i} className={`step ${s.status === 'cache-hit' ? 'success' : s.status}`} style={{ cursor:'default' }}>
                    <div className="step-rail"><div className="node"/><div className="line"/></div>
                    <div className="step-body">
                      <div className="step-row1">
                        <span className="step-name">{s.name}</span>
                        <span className="tertiary mono" style={{ fontSize:11 }}>#{i+1}</span>
                        <span style={{ flex:1 }}/>
                        <StatusBadge status={s.status === 'cache-hit' ? 'success' : s.status === 'pending' ? 'pending' : s.status === 'paused' ? 'paused' : 'success'}/>
                      </div>
                      <div className="step-row2"><span className="mono">{s.dur}</span></div>
                    </div>
                  </div>
                ))}
              </div>
            )}
            {tab === 'payloads' && (
              <div style={{ padding:14 }}>
                <div style={{ fontSize:11, textTransform:'uppercase', letterSpacing:'0.05em', color:'var(--text-tertiary)', fontWeight:600, marginBottom:8 }}>
                  DB Query · output <span className="redacted">•••</span> redacted fields
                </div>
                <pre className="code-block" dangerouslySetInnerHTML={{ __html: jsonHighlight({
                  order_id: 'ord_9f2a', amount_cents: 12450, currency: 'EUR',
                  customer: { id: 'cus_a1b2', email: '•••@•••.com' }, status: 'charged'
                }) }}/>
              </div>
            )}
            {tab === 'audit' && (
              <div className="audit-list">
                {window.FLOW.RUN_EVENTS.map((e, i) => (
                  <div key={i} className="audit-item">
                    <I.Activity size={14} className="audit-icon"/>
                    <div className="audit-event"><b className="mono" style={{ fontSize:12 }}>{e.node}</b><small>{e.msg}</small></div>
                    <time>{e.t}</time>
                  </div>
                ))}
              </div>
            )}
            {tab === 'impact' && (
              <div style={{ padding:14 }}>
                <div className="kpi" style={{ marginBottom:12 }}>
                  <div className="kpi-label"><I.Zap size={11}/> AI cost so far</div>
                  <div className="kpi-value">€0.00</div>
                  <div className="kpi-delta flat">cache hit saved €0.06</div>
                </div>
                <dl className="kv">
                  <dt>Refund amount</dt><dd>€124.50</dd>
                  <dt>Tokens used</dt><dd>0 (cached)</dd>
                  <dt>Writes pending</dt><dd>1 (Stripe refund)</dd>
                  <dt>Webhooks</dt><dd>1 queued</dd>
                </dl>
              </div>
            )}
            {tab === 'errors' && (
              <div className="empty" style={{ padding:'48px 16px' }}>
                <I.Check size={24} style={{ color:'var(--status-success)', marginBottom:8 }}/>
                <div>No errors in this run.</div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

window.RunMonitor = RunMonitor;
