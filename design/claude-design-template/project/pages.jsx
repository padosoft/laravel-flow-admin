// ============================================================
// Flow Studio — Overview, Flows, Advisor, Approvals, Runs, Webhooks, Settings
// ============================================================

function MiniSpark({ data, color, w = 72, h = 24 }) {
  if (!data || !data.length) return <span className="tertiary" style={{ fontSize:11 }}>no data</span>;
  const max = Math.max(...data, 1), min = Math.min(...data, 0), range = max - min || 1;
  const step = w / (data.length - 1 || 1);
  const pts = data.map((v, i) => `${i*step},${h - ((v-min)/range)*(h-3) - 1.5}`).join(' ');
  return <svg width={w} height={h}><polyline points={pts} fill="none" stroke={color || 'var(--accent)'} strokeWidth="1.5"/></svg>;
}

// ---- Overview ----
function OverviewPage({ onNavigate, onOpenRun }) {
  const hourly = React.useMemo(() => Array.from({ length: 24 }, (_, i) => ({
    label: `${i}:00`, success: Math.round(20 + Math.sin(i*0.5)*12 + Math.random()*8), failed: Math.round(Math.random()*4)
  })), []);
  const maxBar = Math.max(...hourly.map(h => h.success + h.failed), 1);
  const running = window.FLOW.RUNS_LIST.filter(r => r.status === 'running' || r.status === 'paused');

  return (
    <div className="page" data-screen-label="Overview">
      <div className="page-head">
        <div><h1 className="page-title">Overview</h1><p className="page-sub">Flow orchestration across your application · last 24h</p></div>
        <div className="page-actions">
          <button className="btn" onClick={() => onNavigate('flows')}><I.Layers size={13}/> Flows</button>
          <button className="btn primary" onClick={() => onNavigate('studio')}><I.Plus size={13}/> New flow</button>
        </div>
      </div>

      <div className="kpi-grid" style={{ gridTemplateColumns:'repeat(5, 1fr)' }}>
        {[
          { label:'Runs (24h)', value:'3,412', delta:'+12.4%', cls:'up', icon:<I.Activity size={11}/> },
          { label:'Success rate', value:'97.8%', delta:'+0.6pt', cls:'up', icon:<I.Check size={11}/> },
          { label:'Failed (24h)', value:'74', delta:'-3', cls:'up', icon:<I.AlertTriangle size={11}/> },
          { label:'p95 duration', value:'6.4s', delta:'stable', cls:'flat', icon:<I.Clock size={11}/> },
          { label:'AI cost (24h)', value:'€142', delta:'+8.1%', cls:'down', icon:<I.Zap size={11}/> },
        ].map((k, i) => (
          <div className="kpi" key={i}>
            <div className="kpi-label">{k.icon} {k.label}</div>
            <div className="kpi-value">{k.value}</div>
            <div className={`kpi-delta ${k.cls}`}>{k.cls==='up'&&<I.ArrowUp size={11}/>}{k.cls==='down'&&<I.ArrowDown size={11}/>} {k.delta}</div>
          </div>
        ))}
      </div>

      {/* Now running strip */}
      <div className="card" style={{ marginBottom:16 }}>
        <div className="card-head">
          <div><h3 className="card-title">Now running</h3><p className="card-sub">Live executions · mini progress</p></div>
          <span className="live-pill"><span className="pulse"/>Live</span>
        </div>
        <div className="card-body flush">
          {running.map(r => (
            <div key={r.id} className="approval-card" style={{ cursor:'pointer' }} onClick={() => onOpenRun(r.id)}>
              <div style={{ display:'flex', alignItems:'center', gap:14, flex:1 }}>
                <StatusBadge status={r.status}/>
                <div style={{ flex:1, minWidth:0 }}>
                  <div style={{ fontSize:13, fontWeight:500 }}>{r.flow}</div>
                  <div className="tertiary mono" style={{ fontSize:11 }}>{r.id} · {r.trigger}</div>
                </div>
                <div className="monitor-progress-track" style={{ maxWidth:200 }}>
                  <div className="monitor-progress-fill" style={{ width: r.status === 'paused' ? '45%' : '68%', background: r.status==='paused'?'var(--status-paused)':'var(--status-running)' }}/>
                </div>
                <span className="cost-chip"><I.Zap size={10}/>€{r.cost.toFixed(2)}</span>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ display:'grid', gridTemplateColumns:'1.5fr 1fr', gap:16, marginBottom:16 }}>
        <div className="card">
          <div className="card-head"><div><h3 className="card-title">Throughput · 24h</h3><p className="card-sub">Runs by hour</p></div>
            <div style={{ display:'flex', gap:14, fontSize:11, color:'var(--text-secondary)' }}>
              <span style={{ display:'flex', alignItems:'center', gap:6 }}><span style={{ width:8, height:8, borderRadius:2, background:'var(--status-success)' }}/>Success</span>
              <span style={{ display:'flex', alignItems:'center', gap:6 }}><span style={{ width:8, height:8, borderRadius:2, background:'var(--status-failed)' }}/>Failed</span>
            </div>
          </div>
          <div className="card-body">
            <div className="thru-chart">
              {hourly.map((h, i) => {
                const t = h.success + h.failed;
                return (
                  <div key={i} className="thru-col">
                    <div className="thru-bar-wrap"><div className="thru-bar" title={`${h.label} · ${t} runs`} style={{ height:`${Math.max((t/maxBar)*100,4)}%` }}>
                      {h.failed>0 && <div className="thru-seg fail" style={{ flex:h.failed }}/>}
                      {h.success>0 && <div className="thru-seg ok" style={{ flex:h.success }}/>}
                    </div></div>
                    <div className="thru-lbl">{i%4===0?h.label:''}</div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        {/* Advisor teaser */}
        <div className="card" style={{ borderLeft:'3px solid var(--cat-ai)' }}>
          <div className="card-head"><div style={{ display:'flex', alignItems:'center', gap:8 }}><I.Wand size={15} style={{ color:'var(--cat-ai)' }}/><h3 className="card-title">Advisor</h3></div>
            <button className="btn ghost sm" onClick={() => onNavigate('advisor')}>View all <I.ArrowRight size={11}/></button></div>
          <div className="card-body">
            <div style={{ fontSize:14, fontWeight:600, marginBottom:6 }}>3 suggestions for you</div>
            <p className="muted" style={{ fontSize:12.5, margin:'0 0 14px' }}>AI found optimizations that could cut cost and add reliability across your flows.</p>
            {window.FLOW.ADVISOR_SUGGESTIONS.slice(0,2).map(s => (
              <div key={s.id} style={{ display:'flex', gap:8, alignItems:'flex-start', padding:'8px 0', borderTop:'1px solid var(--border)' }}>
                <I.Sparkle size={13} style={{ color:'var(--cat-ai)', marginTop:2, flexShrink:0 }}/>
                <div style={{ fontSize:12.5 }}>{s.title}</div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:16 }}>
        <div className="card">
          <div className="card-head"><h3 className="card-title">Recent failures</h3><button className="btn ghost sm" onClick={() => onNavigate('runs')}>All runs <I.ArrowRight size={11}/></button></div>
          <div className="card-body flush">
            {window.FLOW.RUNS_LIST.filter(r=>r.status==='failed').map(r => (
              <div key={r.id} className="approval-card" style={{ cursor:'pointer' }} onClick={()=>onOpenRun(r.id)}>
                <div className="approval-info"><b>{r.flow}</b><small><span className="mono">{r.id}</span> · {r.started}</small></div>
                <StatusBadge status="failed"/>
              </div>
            ))}
          </div>
        </div>
        <div className="card">
          <div className="card-head"><h3 className="card-title">Pending approvals</h3><button className="btn ghost sm" onClick={() => onNavigate('approvals')}>Inbox <I.ArrowRight size={11}/></button></div>
          <div className="card-body flush">
            {window.FLOW.APPROVALS.map(a => (
              <div key={a.id} className="approval-card" style={{ cursor:'pointer' }} onClick={()=>onNavigate('approvals')}>
                <div className="approval-info"><b>{a.flow}</b><small>{a.step} · {a.requested}</small></div>
                <span className="countdown">{Math.floor(a.expiresIn/60)}m</span>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

// ---- Flows library ----
function FlowsPage({ onOpenStudio, onOpenBuilder }) {
  const [view, setView] = React.useState('cards');
  const [newOpen, setNewOpen] = React.useState(false);
  return (
    <div className="page" data-screen-label="Flows">
      <div className="page-head">
        <div><h1 className="page-title">Flows</h1><p className="page-sub">{window.FLOW.FLOWS_LIBRARY.length} definitions · library</p></div>
        <div className="page-actions">
          <div className="tool-group" style={{ background:'var(--bg-elevated)', border:'1px solid var(--border)', borderRadius:7, padding:2 }}>
            <button className={`tool-btn ${view==='cards'?'':''}`} style={{ width:28, height:26, background: view==='cards'?'var(--bg-active)':'transparent' }} onClick={()=>setView('cards')}><I.Grid size={14}/></button>
            <button className="tool-btn" style={{ width:28, height:26, background: view==='table'?'var(--bg-active)':'transparent' }} onClick={()=>setView('table')}><I.Runs size={14}/></button>
          </div>
          <button className="btn primary" onClick={()=>setNewOpen(true)}><I.Plus size={13}/> New Flow</button>
        </div>
      </div>

      {view === 'cards' ? (
        <div className="flow-cards">
          {window.FLOW.FLOWS_LIBRARY.map(f => (
            <div key={f.id} className="flow-card" onClick={onOpenStudio} data-testid={`flow-${f.id}`}>
              <div className="flow-card-head">
                <div className="flow-card-icon"><I.Layers size={17}/></div>
                <div style={{ flex:1, minWidth:0 }}>
                  <div style={{ fontWeight:600, fontSize:13.5 }}>{f.name}</div>
                  <div style={{ marginTop:3 }}><span className={`version-chip ${f.state}`}>v{f.version} · {f.state}</span></div>
                </div>
              </div>
              <div className="flow-card-stats">
                <div className="flow-card-stat"><small>Success</small><b style={{ color: f.successRate>=95?'var(--status-success)':f.successRate>0?'var(--status-paused)':'var(--text-tertiary)' }}>{f.successRate>0?f.successRate+'%':'—'}</b></div>
                <div className="flow-card-stat"><small>Runs</small><b>{f.runs.toLocaleString()}</b></div>
                <div className="flow-card-stat"><small>Cost/run</small><b>€{f.costPerRun.toFixed(2)}</b></div>
                <div className="flow-card-stat" style={{ marginLeft:'auto' }}><small>Trend</small><MiniSpark data={f.costTrend} color="var(--cat-ai)"/></div>
              </div>
              <div className="flow-card-foot">
                {f.tags.map(t => <span key={t} className="tag">{t}</span>)}
                <div style={{ flex:1 }}/>
                <span className="tertiary" style={{ fontSize:11 }}>{f.lastRun}</span>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="card"><div className="card-body flush"><div className="table-wrap">
          <table className="tbl"><thead><tr><th>Flow</th><th>Version</th><th className="num">Success</th><th className="num">Runs</th><th className="num">Cost/run</th><th>Tags</th><th>Last run</th></tr></thead>
            <tbody>{window.FLOW.FLOWS_LIBRARY.map(f => (
              <tr key={f.id} onClick={onOpenStudio}>
                <td><b style={{ fontWeight:500 }}>{f.name}</b></td>
                <td><span className={`version-chip ${f.state}`}>v{f.version} · {f.state}</span></td>
                <td className="num">{f.successRate>0?f.successRate+'%':'—'}</td>
                <td className="num">{f.runs.toLocaleString()}</td>
                <td className="num mono">€{f.costPerRun.toFixed(2)}</td>
                <td>{f.tags.map(t=><span key={t} className="tag" style={{ marginRight:4 }}>{t}</span>)}</td>
                <td className="muted">{f.lastRun}</td>
              </tr>
            ))}</tbody></table>
        </div></div></div>
      )}

      <Modal open={newOpen} onClose={()=>setNewOpen(false)} title="New flow" sub="Choose how to start">
        <div style={{ display:'flex', flexDirection:'column', gap:8 }}>
          {[
            { icon:<I.Grid size={16}/>, title:'Blank canvas', desc:'Start from an empty graph', action:()=>{ setNewOpen(false); onOpenStudio(); } },
            { icon:<I.Wand size={16}/>, title:'From AI prompt', desc:'Describe it, let Advisor draft the graph', action:()=>{ setNewOpen(false); onOpenBuilder(); } },
            { icon:<I.Code size={16}/>, title:'Import JSON', desc:'Paste a flow definition', action:()=>setNewOpen(false) },
          ].map((o,i) => (
            <button key={i} className="palette-node" style={{ textAlign:'left', border:'1px solid var(--border)' }} onClick={o.action}>
              <div className="palette-node-icon" style={{ color:'var(--accent)' }}>{o.icon}</div>
              <div className="palette-node-body"><div className="palette-node-name">{o.title}</div><div className="tertiary" style={{ fontSize:11 }}>{o.desc}</div></div>
              <I.ChevronRight size={15} style={{ color:'var(--text-tertiary)' }}/>
            </button>
          ))}
        </div>
      </Modal>
    </div>
  );
}

// ---- Advisor ----
function AdvisorPage({ onOpenStudio, toast }) {
  const [dismissed, setDismissed] = React.useState([]);
  const list = window.FLOW.ADVISOR_SUGGESTIONS.filter(s => !dismissed.includes(s.id));
  return (
    <div className="page" data-screen-label="Advisor">
      <div className="page-head">
        <div style={{ display:'flex', alignItems:'center', gap:10 }}>
          <div className="flow-card-icon" style={{ color:'var(--cat-ai)' }}><I.Wand size={18}/></div>
          <div><h1 className="page-title">Advisor</h1><p className="page-sub">AI-generated suggestions · {list.length} open</p></div>
        </div>
      </div>

      <div style={{ display:'flex', alignItems:'center', gap:8, padding:'10px 14px', background:'var(--bg-subtle)', borderRadius:8, marginBottom:16, fontSize:12, color:'var(--text-secondary)' }}>
        <I.Info size={14}/> Analysis scope: <b style={{ color:'var(--text)' }}>all published flows + 30d run history</b>. History is redacted before analysis.
      </div>

      {list.length === 0 && <div className="empty">No suggestions right now — you're all optimized ✓</div>}
      {list.map(s => (
        <div key={s.id} className={`advisor-card ${s.kind}`}>
          <div>
            <div style={{ display:'flex', alignItems:'center', gap:8, marginBottom:4 }}>
              <span className="badge outline" style={{ textTransform:'uppercase', fontSize:9.5 }}>{s.kind === 'new' ? 'New flow' : 'Improve'}</span>
              {s.flow && <span className="tertiary" style={{ fontSize:12 }}>{s.flow}</span>}
              {s.benefit && <span className="badge success" style={{ fontSize:10 }}>{typeof s.benefit==='string'?s.benefit:s.benefit+' benefit'}</span>}
              {s.severity && <span className={`badge ${s.severity==='medium'?'paused':'pending'}`} style={{ fontSize:10 }}>{s.severity}</span>}
            </div>
            <div style={{ fontSize:14, fontWeight:600, marginBottom:4 }}>{s.title}</div>
            {s.detail && <p className="muted" style={{ fontSize:12.5, margin:0 }}>{s.detail}</p>}
            {s.rationale && <div className="advisor-rationale">{s.rationale.map((r,i)=><span key={i} className="rationale-chip"><I.Dot size={9}/>{r}</span>)}</div>}
            {s.preview && <div className="mini-graph">{s.preview.split('→').map((p,i,arr)=>(<React.Fragment key={i}><span className="mg-node">{p.trim()}</span>{i<arr.length-1&&<I.ArrowRight size={11}/>}</React.Fragment>))}</div>}
          </div>
          <div className="advisor-actions">
            <button className="btn primary" onClick={() => { toast?.push({ title: s.kind==='new'?'Draft created':'Diff opened' }); onOpenStudio(); }}>
              {s.kind === 'new' ? <><I.Edit size={12}/> Open as draft</> : <><I.Diff size={12}/> View diff</>}
            </button>
            <button className="btn" onClick={() => setDismissed(d => [...d, s.id])}>Dismiss</button>
          </div>
        </div>
      ))}
    </div>
  );
}

// ---- Approvals ----
function ApprovalsPage({ onOpenRun, toast }) {
  const [items, setItems] = React.useState(window.FLOW.APPROVALS);
  const [approve, setApprove] = React.useState(null);
  const [reject, setReject] = React.useState(null);
  const [comment, setComment] = React.useState('');
  const [now, setNow] = React.useState(Date.now());
  React.useEffect(() => { const id = setInterval(() => setNow(Date.now()), 1000); return () => clearInterval(id); }, []);

  const fmtCountdown = (s) => `${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`;
  const resolve = (id, action) => { setItems(x => x.filter(i => i.id !== id)); toast?.push({ title: action==='approve'?'Approved':'Rejected', body: action==='approve'?'Flow resumed':'Flow terminated', kind: action==='reject'?'warn':undefined }); };

  return (
    <div className="page" data-screen-label="Approvals">
      <div className="page-head">
        <div><h1 className="page-title">Approvals</h1><p className="page-sub">{items.length} awaiting your decision</p></div>
      </div>
      {items.length === 0 ? <div className="empty">No approvals waiting — nice.</div> : items.map(a => {
        const secs = Math.max(0, a.expiresIn - Math.floor((now/1000) % 60));
        const urgent = a.expiresIn < 1500;
        return (
          <div key={a.id} className="card" style={{ marginBottom:12 }}>
            <div style={{ padding:16, display:'grid', gridTemplateColumns:'1fr auto', gap:16 }}>
              <div>
                <div style={{ display:'flex', alignItems:'center', gap:8, marginBottom:6 }}>
                  <I.UserCheck size={15} style={{ color:'var(--status-paused)' }}/>
                  <b style={{ fontSize:14 }}>{a.flow}</b>
                  <span className="tertiary" style={{ fontSize:12 }}>· {a.step}</span>
                </div>
                <div style={{ padding:'10px 12px', background:'var(--status-paused-bg)', borderRadius:8, fontSize:12.5, marginBottom:10, color:'var(--text)' }}>
                  <b style={{ display:'block', fontSize:10.5, textTransform:'uppercase', letterSpacing:'0.05em', color:'var(--status-paused)', marginBottom:3 }}>If approved</b>
                  {a.impact}
                </div>
                <div style={{ display:'flex', gap:16, flexWrap:'wrap', fontSize:11.5 }} className="muted">
                  {Object.entries(a.context).map(([k,v]) => <span key={k}><span className="tertiary">{k}:</span> <span className="mono">{v}</span></span>)}
                </div>
                <div style={{ marginTop:8, fontSize:11.5 }} className="tertiary">
                  Requested {a.requested} by <span className="mono">{a.requester}</span> · <a onClick={()=>onOpenRun(a.run)} style={{ color:'var(--accent)', cursor:'pointer' }}>open run monitor</a>
                </div>
              </div>
              <div style={{ display:'flex', flexDirection:'column', gap:8, alignItems:'flex-end', minWidth:150 }}>
                <span className={`countdown ${urgent?'urgent':''}`}>expires {fmtCountdown(secs)}</span>
                <button className="btn primary" style={{ width:'100%', justifyContent:'center' }} onClick={()=>{ setApprove(a); setComment(''); }} data-testid={`approve-${a.id}`}><I.Check size={13}/> Approve</button>
                <button className="btn danger" style={{ width:'100%', justifyContent:'center' }} onClick={()=>{ setReject(a); setComment(''); }} data-testid={`reject-${a.id}`}><I.Cancel size={13}/> Reject</button>
              </div>
            </div>
          </div>
        );
      })}

      <Modal open={!!approve} onClose={()=>setApprove(null)} title="Approve & resume" sub={approve?.impact}
             footer={<><button className="btn" onClick={()=>setApprove(null)}>Cancel</button>
               <button className="btn primary" onClick={()=>{ resolve(approve.id,'approve'); setApprove(null); }}><I.Check size={13}/> Confirm approve</button></>}>
        <label style={{ fontSize:11, fontWeight:600, textTransform:'uppercase', letterSpacing:'0.05em', color:'var(--text-tertiary)', display:'block', marginBottom:6 }}>Comment (optional)</label>
        <textarea className="input" rows="2" value={comment} onChange={e=>setComment(e.target.value)} placeholder="Recorded in the audit trail…" style={{ fontFamily:'var(--font-sans)', resize:'vertical' }}/>
      </Modal>
      <Modal open={!!reject} onClose={()=>setReject(null)} title="Reject & terminate" sub={reject ? `${reject.flow} · ${reject.step}` : ''}
             footer={<><button className="btn" onClick={()=>setReject(null)}>Cancel</button>
               <button className="btn danger" disabled={!comment.trim()} onClick={()=>{ resolve(reject.id,'reject'); setReject(null); }}><I.Cancel size={13}/> Reject</button></>}>
        <label style={{ fontSize:11, fontWeight:600, textTransform:'uppercase', letterSpacing:'0.05em', color:'var(--text-tertiary)', display:'block', marginBottom:6 }}>Reason (required)</label>
        <textarea className="input" rows="3" value={comment} onChange={e=>setComment(e.target.value)} placeholder="Why is this being rejected?" style={{ fontFamily:'var(--font-sans)', resize:'vertical' }}/>
      </Modal>
    </div>
  );
}

// ---- Runs list ----
function RunsPage({ onOpenRun }) {
  const [status, setStatus] = React.useState('all');
  const runs = window.FLOW.RUNS_LIST.filter(r => status === 'all' || r.status === status);
  const trigIcon = { manual:<I.User size={12}/>, schedule:<I.Clock size={12}/>, event:<I.Zap size={12}/>, webhook:<I.Globe size={12}/>, mcp:<I.Boxes size={12}/> };
  const counts = window.FLOW.RUNS_LIST.reduce((a,r)=>({ ...a, [r.status]:(a[r.status]||0)+1 }), { all: window.FLOW.RUNS_LIST.length });
  return (
    <div className="page" data-screen-label="Runs">
      <div className="page-head"><div><h1 className="page-title">Runs</h1><p className="page-sub">{runs.length} executions</p></div></div>
      <div className="filter-bar">
        {['all','running','paused','success','failed'].map(s => (
          <button key={s} className={`chip ${status===s?'active':''}`} onClick={()=>setStatus(s)}>{s==='all'?'All':s[0].toUpperCase()+s.slice(1)}<span className="count">{counts[s]||0}</span></button>
        ))}
      </div>
      <div className="card"><div className="card-body flush"><div className="table-wrap">
        <table className="tbl"><thead><tr><th style={{width:110}}>Status</th><th>Flow</th><th>Run ID</th><th>Trigger</th><th className="num">Duration</th><th className="num">Cost</th><th>Started</th><th style={{width:24}}></th></tr></thead>
          <tbody>{runs.map(r => (
            <tr key={r.id} onClick={()=>onOpenRun(r.id)}>
              <td><StatusBadge status={r.status}/></td>
              <td><b style={{ fontWeight:500 }}>{r.flow}</b> <span className="tertiary mono" style={{ fontSize:11 }}>v{r.version}</span></td>
              <td><span className="mono" style={{ fontSize:11.5 }}>{r.id}</span></td>
              <td><span className="badge outline" style={{ fontSize:10.5, gap:4 }}>{trigIcon[r.trigger]}{r.trigger}</span></td>
              <td className="num muted">{r.duration}</td>
              <td className="num"><span className="cost-chip"><I.Zap size={9}/>€{r.cost.toFixed(2)}</span></td>
              <td className="muted">{r.started}</td>
              <td><I.ChevronRight size={14} style={{ color:'var(--text-tertiary)' }}/></td>
            </tr>
          ))}</tbody></table>
      </div></div></div>
    </div>
  );
}

// ---- Webhooks ----
function WebhooksPage({ toast }) {
  const [status, setStatus] = React.useState('all');
  const rows = window.FLOW.WEBHOOKS_OUTBOX.filter(w => status==='all' || w.status===status);
  const counts = window.FLOW.WEBHOOKS_OUTBOX.reduce((a,w)=>({ ...a, [w.status]:(a[w.status]||0)+1 }), { all: window.FLOW.WEBHOOKS_OUTBOX.length });
  return (
    <div className="page" data-screen-label="Webhooks">
      <div className="page-head"><div><h1 className="page-title">Webhooks</h1><p className="page-sub">Transactional outbox · signed deliveries</p></div></div>
      <div className="filter-bar">
        {['all','delivered','pending','dead'].map(s => <button key={s} className={`chip ${status===s?'active':''}`} onClick={()=>setStatus(s)}>{s==='all'?'All':s[0].toUpperCase()+s.slice(1)}<span className="count">{counts[s]||0}</span></button>)}
      </div>
      <div className="card"><div className="card-body flush"><div className="table-wrap">
        <table className="tbl"><thead><tr><th style={{width:110}}>Status</th><th>Event</th><th>Topic</th><th>Target</th><th className="num">Attempts</th><th>Response</th><th>Next retry</th><th style={{width:70}}></th></tr></thead>
          <tbody>{rows.map(w => (
            <tr key={w.id}>
              <td><StatusBadge status={w.status==='delivered'?'success':w.status==='pending'?'pending':'failed'}/></td>
              <td><span className="mono" style={{ fontSize:11.5 }}>{w.id}</span></td>
              <td><span className="mono" style={{ fontSize:11.5 }}>{w.topic}</span></td>
              <td className="muted mono" style={{ fontSize:11.5, maxWidth:180, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap' }}>{w.target}</td>
              <td className="num mono">{w.attempts}/5</td>
              <td style={{ fontSize:12, color: w.status==='dead'?'var(--status-failed)':w.status==='pending'?'var(--status-paused)':'var(--text-secondary)' }}>{w.code}</td>
              <td className="muted">{w.next !== '—' ? <span className="countdown">{w.next}</span> : '—'}</td>
              <td>{w.status!=='delivered' && <button className="btn sm" onClick={()=>toast?.push({ title:'Redelivering', body:w.id })}><I.Refresh size={11}/> Redeliver</button>}</td>
            </tr>
          ))}</tbody></table>
      </div></div></div>
    </div>
  );
}

// ---- AI Builder dialog ----
function AIBuilder({ open, onClose, onInsert }) {
  const [phase, setPhase] = React.useState('prompt'); // prompt | streaming | preview
  const [prompt, setPrompt] = React.useState('');
  React.useEffect(() => { if (open) { setPhase('prompt'); setPrompt(''); } }, [open]);

  const generate = () => {
    setPhase('streaming');
    setTimeout(() => setPhase('preview'), 1800);
  };
  if (!open) return null;
  const preview = window.FLOW.makeRefundFlow();

  return (
    <>
      <div className="overlay" onClick={onClose}/>
      <div className="modal ai-builder">
        <div className="modal-head" style={{ display:'flex', alignItems:'center', gap:10 }}>
          <div className="flow-card-icon" style={{ color:'var(--cat-ai)', width:30, height:30 }}><I.Wand size={16}/></div>
          <div><div className="modal-title">AI Flow Builder</div><div className="modal-sub">Describe a workflow — get a graph</div></div>
        </div>
        <div className="modal-body">
          {phase === 'prompt' && (
            <textarea className="input" rows="4" autoFocus value={prompt} onChange={e=>setPrompt(e.target.value)}
                      placeholder="e.g. When an order is refunded, summarize fraud risk with AI, require approval above €100, then call Stripe and notify Slack…"
                      style={{ fontFamily:'var(--font-sans)', resize:'vertical', fontSize:13 }}/>
          )}
          {phase === 'streaming' && (
            <div style={{ padding:'8px 0' }}>
              <div style={{ fontSize:13, marginBottom:12 }}>Designing your flow<span className="streaming-cursor"/></div>
              <div style={{ display:'flex', flexDirection:'column', gap:8 }}>
                {['Identifying trigger: order.refunded','Adding fraud summary (LLM)','Inserting approval gate (>€100)','Wiring Stripe refund + Slack notify'].map((t,i)=>(
                  <div key={i} className="skel" style={{ height:14, width:`${90-i*8}%`, animationDelay:`${i*0.15}s` }}/>
                ))}
              </div>
            </div>
          )}
          {phase === 'preview' && (
            <>
              <div className="ai-builder-preview" style={{ marginBottom:14 }}>
                <div style={{ position:'absolute', inset:0, transform:'scale(0.42)', transformOrigin:'top left', pointerEvents:'none', width:'240%', height:'240%' }}>
                  <FlowCanvas nodes={preview.nodes} connections={preview.connections} editable={false} showLegend={false} showMinimap={false} selectedNodeId={null} onSelectNode={()=>{}}/>
                </div>
              </div>
              <div style={{ fontSize:11, textTransform:'uppercase', letterSpacing:'0.05em', color:'var(--text-tertiary)', fontWeight:600, marginBottom:6 }}>Assumptions</div>
              <ul style={{ margin:0, paddingLeft:18, fontSize:12.5, color:'var(--text-secondary)', display:'flex', flexDirection:'column', gap:4 }}>
                <li>Approval threshold set to €100 (editable)</li>
                <li>Stripe used as refund provider</li>
                <li>Slack webhook already configured</li>
              </ul>
            </>
          )}
        </div>
        <div className="modal-foot">
          {phase === 'prompt' && <>
            <button className="btn" onClick={onClose}>Cancel</button>
            <button className="btn primary" disabled={!prompt.trim()} onClick={generate}><I.Wand size={13}/> Generate</button>
          </>}
          {phase === 'streaming' && <button className="btn" onClick={onClose}>Cancel</button>}
          {phase === 'preview' && <>
            <button className="btn" onClick={()=>setPhase('prompt')}><I.Refresh size={13}/> Regenerate</button>
            <button className="btn primary" onClick={()=>{ onInsert?.(); onClose(); }}><I.Plus size={13}/> Insert into canvas</button>
          </>}
        </div>
      </div>
    </>
  );
}

// ---- Settings ----
function SettingsPage() {
  return (
    <div className="page" data-screen-label="Settings">
      <div className="page-head"><div><h1 className="page-title">Settings</h1><p className="page-sub">Authorizer, retention, signing, queue</p></div></div>
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:16 }}>
        {[
          { t:'Authorizer', rows:[['Policy','App\\Flows\\Policies\\AdminAuthorizer'],['Token TTL','15 minutes'],['Required role','flow.operator'],['2FA','required for publish & cancel']] },
          { t:'Retention', rows:[['Successful runs','30 days'],['Failed runs','180 days'],['Audit events','365 days'],['Outbox','14 days']] },
          { t:'Webhook signing', rows:[['Algorithm','HMAC-SHA256'],['Header','X-Flow-Signature'],['Rotation','every 90 days']] },
          { t:'Queue', rows:[['Driver','redis'],['Connection','flow_default'],['Workers','8 active · 2 idle'],['Backpressure','none']] },
        ].map((c,i) => (
          <div className="card" key={i}><div className="card-head"><h3 className="card-title">{c.t}</h3></div>
            <div className="card-body"><dl className="kv">{c.rows.map(([k,v])=><React.Fragment key={k}><dt>{k}</dt><dd>{v}</dd></React.Fragment>)}</dl></div></div>
        ))}
      </div>
    </div>
  );
}

Object.assign(window, { OverviewPage, FlowsPage, AdvisorPage, ApprovalsPage, RunsPage, WebhooksPage, AIBuilder, SettingsPage, MiniSpark });
