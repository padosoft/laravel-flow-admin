// ============== Approvals & Outbox & Definitions Pages ==============

function PageApprovals({ runs, onOpenRun }) {
  const [approveTarget, setApproveTarget] = React.useState(null);
  const [rejectTarget, setRejectTarget] = React.useState(null);
  const [token, setToken] = React.useState('');
  const [reason, setReason] = React.useState('');
  const toast = useToast();

  const pending = runs.filter(r => r.status === 'paused');

  return (
    <div className="page" data-screen-label="Approvals">
      <div className="page-head">
        <div>
          <h1 className="page-title">Approvals</h1>
          <p className="page-sub">{pending.length} flow{pending.length === 1 ? '' : 's'} awaiting human decision</p>
        </div>
        <div className="page-actions">
          <button className="btn"><I.Filter size={13}/> Filter</button>
        </div>
      </div>

      <div className="card">
        <div className="card-head">
          <h3 className="card-title">Awaiting approval</h3>
          <span className="badge outline">{pending.length}</span>
        </div>
        <div className="card-body flush">
          {pending.length === 0 ? (
            <div className="empty">All clear · no pending approvals</div>
          ) : pending.map(r => (
            <div key={r.id} className="approval-card">
              <div className="approval-info" style={{display:'flex',alignItems:'center',gap:14}}>
                <div style={{flex:1, minWidth:0}}>
                  <div style={{display:'flex',alignItems:'center',gap:8,marginBottom:4}}>
                    <b style={{fontSize:13.5}}>{r.flow_name}</b>
                    <span className="tertiary mono" style={{fontSize:11}}>{r.version}</span>
                    <StatusBadge status="paused"/>
                  </div>
                  <div className="muted" style={{fontSize:11.5,display:'flex',gap:14,flexWrap:'wrap'}}>
                    <span><I.Hash size={11} style={{verticalAlign:'-2px',marginRight:3}}/> <span className="mono">{r.id}</span></span>
                    <span><I.Clock size={11} style={{verticalAlign:'-2px',marginRight:3}}/> paused {fmtRelative(r.started_at)}</span>
                    <span><I.User size={11} style={{verticalAlign:'-2px',marginRight:3}}/> {r.actor}</span>
                  </div>
                </div>
              </div>
              <div className="approval-actions">
                <button className="btn sm" onClick={() => onOpenRun(r.id)}>Inspect</button>
                <button className="btn sm danger" onClick={() => { setRejectTarget(r); setReason(''); }}>
                  Reject
                </button>
                <button className="btn sm primary" onClick={() => { setApproveTarget(r); setToken('apv_tok_' + Math.random().toString(36).slice(2,10)); }}>
                  <I.Check size={12}/> Approve
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Approve modal */}
      <Modal open={!!approveTarget} onClose={() => setApproveTarget(null)}
             title="Approve & resume"
             sub="Provide an approval token. The flow will resume immediately."
             footer={<>
               <button className="btn" onClick={() => setApproveTarget(null)}>Cancel</button>
               <button className="btn primary" disabled={!token.trim()}
                       onClick={() => {
                         toast.push({title:'Approved', body:`${approveTarget.flow_name} resumed`});
                         setApproveTarget(null);
                       }}>
                 <I.Resume size={11}/> Approve & resume
               </button>
             </>}>
        {approveTarget && (
          <>
            <dl className="kv" style={{marginBottom:14}}>
              <dt>Run</dt><dd>{approveTarget.id}</dd>
              <dt>Flow</dt><dd>{approveTarget.flow_name}</dd>
              <dt>Paused at</dt><dd>{fmtDateTime(approveTarget.started_at)}</dd>
            </dl>
            <label style={{fontSize:11,fontWeight:600,textTransform:'uppercase',letterSpacing:'0.05em',color:'var(--text-tertiary)',display:'block',marginBottom:6}}>
              Approval token
            </label>
            <input className="input mono" value={token} onChange={e => setToken(e.target.value)}
                   placeholder="apv_tok_…"/>
            <small className="tertiary" style={{display:'block',marginTop:6,fontSize:11}}>
              Tokens are tied to the authorizer policy. They expire after the configured TTL.
            </small>
          </>
        )}
      </Modal>

      {/* Reject modal */}
      <Modal open={!!rejectTarget} onClose={() => setRejectTarget(null)}
             title="Reject & terminate"
             sub="The flow will receive a rejection signal. Compensation may run."
             footer={<>
               <button className="btn" onClick={() => setRejectTarget(null)}>Cancel</button>
               <button className="btn danger" disabled={!reason.trim()}
                       onClick={() => {
                         toast.push({title:'Rejected', body:`${rejectTarget.flow_name} terminated`, kind:'warn'});
                         setRejectTarget(null);
                       }}>
                 <I.Cancel size={11}/> Reject
               </button>
             </>}>
        {rejectTarget && (
          <>
            <dl className="kv" style={{marginBottom:14}}>
              <dt>Run</dt><dd>{rejectTarget.id}</dd>
              <dt>Flow</dt><dd>{rejectTarget.flow_name}</dd>
            </dl>
            <label style={{fontSize:11,fontWeight:600,textTransform:'uppercase',letterSpacing:'0.05em',color:'var(--text-tertiary)',display:'block',marginBottom:6}}>
              Reason
            </label>
            <textarea className="input" rows="3" value={reason} onChange={e => setReason(e.target.value)}
                      placeholder="Document the rejection rationale (audit trail)…"
                      style={{resize:'vertical', minHeight:80, fontFamily:'var(--font-sans)'}}/>
          </>
        )}
      </Modal>
    </div>
  );
}

// ============== Outbox ==============

function PageOutbox({ runs }) {
  // Aggregate outbox events from runs (simulated)
  const events = React.useMemo(() => {
    const all = [];
    const rng = (() => { let s = 7; return () => { s = (s * 9301 + 49297) % 233280; return s / 233280; }; })();
    runs.slice(0, 60).forEach(r => {
      const det = window.FLOW_DATA.genRunDetail(r);
      det.outbox.forEach(o => all.push({ ...o, run: r }));
    });
    return all.sort((a, b) => (b.run.started_at) - (a.run.started_at));
  }, [runs]);

  const [statusFilter, setStatusFilter] = React.useState('all');
  const filtered = events.filter(e => statusFilter === 'all' || e.status === statusFilter);

  const counts = events.reduce((acc, e) => { acc[e.status] = (acc[e.status]||0)+1; return acc; }, { all: events.length });
  const filters = ['all', 'pending', 'delivered', 'dead'];

  return (
    <div className="page" data-screen-label="Outbox">
      <div className="page-head">
        <div>
          <h1 className="page-title">Webhook outbox</h1>
          <p className="page-sub">Outgoing event deliveries · transactional outbox pattern</p>
        </div>
        <div className="page-actions">
          <button className="btn"><I.Refresh size={13}/> Retry failed</button>
        </div>
      </div>

      <div className="filter-bar">
        {filters.map(f => (
          <button key={f} className={`chip ${statusFilter === f ? 'active' : ''}`}
                  onClick={() => setStatusFilter(f)}>
            {f === 'all' ? 'All' : f.charAt(0).toUpperCase() + f.slice(1)}
            <span className="count">{counts[f] || 0}</span>
          </button>
        ))}
      </div>

      <div className="card">
        <div className="card-body flush">
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th style={{width:120}}>Status</th>
                  <th>Event ID</th>
                  <th>Topic</th>
                  <th>Target</th>
                  <th>Attempts</th>
                  <th>Last response</th>
                  <th>Next retry</th>
                  <th style={{width:60}}></th>
                </tr>
              </thead>
              <tbody>
                {filtered.slice(0, 60).map(e => (
                  <tr key={e.id}>
                    <td><StatusBadge status={e.status === 'pending' ? 'pending' : e.status === 'delivered' ? 'success' : 'failed'} /></td>
                    <td><span className="mono" style={{fontSize:11.5}}>{e.id}</span></td>
                    <td><span className="mono" style={{fontSize:11.5}}>{e.topic}</span></td>
                    <td className="muted" style={{fontSize:11.5,fontFamily:'var(--font-mono)',maxWidth:220,overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}}>{e.target}</td>
                    <td>
                      <span className="attempt-bar">
                        {Array.from({length: 3}).map((_, i) => (
                          <span key={i} className={`attempt-tick ${i < e.attempts ? (e.status === 'delivered' && i === e.attempts-1 ? 'success' : 'fail') : ''}`}/>
                        ))}
                        <span className="mono muted" style={{marginLeft:6,fontSize:11}}>{e.attempts}/3</span>
                      </span>
                    </td>
                    <td className={e.status === 'delivered' ? 'muted' : ''} style={{fontSize:12, color: e.status==='dead'? 'var(--status-failed)': null}}>{e.last_response}</td>
                    <td className="muted">{e.next_retry_at ? fmtRelative(e.next_retry_at - 60000) : '—'}</td>
                    <td>
                      <button className="btn sm ghost" title="Retry"><I.Refresh size={11}/></button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

// ============== Definitions ==============

function PageDefinitions({ runs }) {
  const defs = window.FLOW_DATA.FLOW_DEFS.map(d => {
    const matching = runs.filter(r => r.flow_def === d.id);
    const success = matching.filter(r => r.status === 'success').length;
    const failed = matching.filter(r => r.status === 'failed').length;
    const running = matching.filter(r => r.status === 'running').length;
    const total = matching.length;
    return { ...d, total, success, failed, running, success_rate: total > 0 ? (success / total) * 100 : 0 };
  });

  return (
    <div className="page" data-screen-label="Definitions">
      <div className="page-head">
        <div>
          <h1 className="page-title">Flow definitions</h1>
          <p className="page-sub">{defs.length} registered · auto-discovered from <span className="mono">App\Flows</span></p>
        </div>
      </div>
      <div className="card">
        <div className="card-body flush">
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th>Flow</th>
                  <th>Version</th>
                  <th className="num">Steps</th>
                  <th className="num">Runs</th>
                  <th className="num">Success rate</th>
                  <th>Activity</th>
                </tr>
              </thead>
              <tbody>
                {defs.map(d => (
                  <tr key={d.id}>
                    <td><b style={{fontWeight:500}}>{d.name}</b></td>
                    <td><span className="mono" style={{fontSize:11.5}}>{d.version}</span></td>
                    <td className="num">{d.steps}</td>
                    <td className="num">{d.total}</td>
                    <td className="num">
                      <span className={d.success_rate >= 95 ? 'mono' : 'mono'}
                            style={{color: d.success_rate >= 95 ? 'var(--status-success)' : d.success_rate >= 80 ? 'var(--status-paused)' : 'var(--status-failed)'}}>
                        {d.success_rate.toFixed(1)}%
                      </span>
                    </td>
                    <td>
                      <span style={{display:'inline-flex',alignItems:'center',gap:8}}>
                        <span style={{display:'inline-flex',height:6,borderRadius:3,overflow:'hidden',width:120,background:'var(--bg-subtle)'}}>
                          <span style={{width:`${(d.success/Math.max(d.total,1))*100}%`,background:'var(--status-success)'}}/>
                          <span style={{width:`${(d.failed/Math.max(d.total,1))*100}%`,background:'var(--status-failed)'}}/>
                          <span style={{width:`${(d.running/Math.max(d.total,1))*100}%`,background:'var(--status-running)'}}/>
                        </span>
                        <span className="muted mono" style={{fontSize:11}}>{d.success}·{d.failed}·{d.running}</span>
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

window.PageApprovals = PageApprovals;
window.PageOutbox = PageOutbox;
window.PageDefinitions = PageDefinitions;
