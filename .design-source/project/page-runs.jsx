// ============== Runs List Page ==============

function PageRuns({ runs, onOpenRun, initialStatus }) {
  const [statusFilter, setStatusFilter] = React.useState(initialStatus || 'all');
  const [flowFilter, setFlowFilter] = React.useState('all');
  const [query, setQuery] = React.useState('');
  const [page, setPage] = React.useState(1);
  const pageSize = 25;

  React.useEffect(() => { setPage(1); }, [statusFilter, flowFilter, query]);

  const flowDefs = React.useMemo(() => {
    const set = new Map();
    runs.forEach(r => set.set(r.flow_def, r.flow_name));
    return Array.from(set.entries());
  }, [runs]);

  const filtered = React.useMemo(() => {
    return runs.filter(r => {
      if (statusFilter !== 'all' && r.status !== statusFilter) return false;
      if (flowFilter !== 'all' && r.flow_def !== flowFilter) return false;
      if (query) {
        const q = query.toLowerCase();
        if (!r.id.toLowerCase().includes(q)
          && !r.flow_name.toLowerCase().includes(q)
          && !r.actor.toLowerCase().includes(q)
          && !r.correlation_id.toLowerCase().includes(q)) return false;
      }
      return true;
    });
  }, [runs, statusFilter, flowFilter, query]);

  const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
  const visible = filtered.slice((page - 1) * pageSize, page * pageSize);

  const counts = React.useMemo(() => {
    const c = { all: runs.length };
    runs.forEach(r => { c[r.status] = (c[r.status] || 0) + 1; });
    return c;
  }, [runs]);

  const statusOpts = ['all', 'running', 'paused', 'failed', 'success', 'compensated', 'pending'];

  return (
    <div className="page" data-screen-label="Runs">
      <div className="page-head">
        <div>
          <h1 className="page-title">Runs</h1>
          <p className="page-sub">All flow executions · {filtered.length.toLocaleString()} matches</p>
        </div>
        <div className="page-actions">
          <button className="btn"><I.External size={13}/> Export</button>
          <button className="btn primary"><I.Plus size={13}/> Trigger flow</button>
        </div>
      </div>

      {/* Filter bar */}
      <div className="filter-bar">
        {statusOpts.map(s => (
          <button key={s}
                  className={`chip ${statusFilter === s ? 'active' : ''}`}
                  onClick={() => setStatusFilter(s)}>
            {s === 'all' ? 'All' : s.charAt(0).toUpperCase() + s.slice(1)}
            <span className="count">{counts[s] || 0}</span>
          </button>
        ))}
        <div style={{flex:1}}/>
        <select className="select" style={{width:200}} value={flowFilter} onChange={e => setFlowFilter(e.target.value)}>
          <option value="all">All flow definitions</option>
          {flowDefs.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
        </select>
        <div style={{position:'relative'}}>
          <I.Search size={13} style={{position:'absolute', left:8, top:'50%', transform:'translateY(-50%)', color:'var(--text-tertiary)'}}/>
          <input className="input" style={{width:240, paddingLeft:28}}
                 placeholder="Search id, actor, correlation…"
                 value={query} onChange={e => setQuery(e.target.value)} />
        </div>
      </div>

      <div className="card">
        <div className="card-body flush">
          <div className="table-wrap">
            <table className="tbl">
              <thead>
                <tr>
                  <th style={{width:120}}>Status</th>
                  <th>Flow</th>
                  <th>Run ID</th>
                  <th>Trigger</th>
                  <th>Actor</th>
                  <th>Started</th>
                  <th className="num">Steps</th>
                  <th className="num">Duration</th>
                  <th style={{width:24}}></th>
                </tr>
              </thead>
              <tbody>
                {visible.length === 0 && (
                  <tr><td colSpan={9}><div className="empty">No runs match your filters</div></td></tr>
                )}
                {visible.map(r => (
                  <tr key={r.id} onClick={() => onOpenRun(r.id)}>
                    <td><StatusBadge status={r.status}/></td>
                    <td>
                      <b style={{fontWeight:500}}>{r.flow_name}</b>
                      <span className="tertiary mono" style={{fontSize:11, marginLeft:6}}>{r.version}</span>
                    </td>
                    <td><span className="mono" style={{fontSize:11.5}}>{r.id}</span></td>
                    <td>
                      <span className="badge outline" style={{fontSize:10.5}}>{r.triggered_by}</span>
                    </td>
                    <td className="muted" style={{fontSize:12}}>{r.actor}</td>
                    <td className="muted" title={fmtDateTime(r.started_at)}>{fmtRelative(r.started_at)}</td>
                    <td className="num">
                      <span className="mono">{r.steps_done}/{r.steps_total}</span>
                    </td>
                    <td className="num muted">{fmtDuration(r.duration_ms)}</td>
                    <td><I.ChevronRight size={14} style={{color:'var(--text-tertiary)'}}/></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
        <div className="pagination">
          <span>
            Showing <b>{(page-1)*pageSize + 1}</b>–<b>{Math.min(page*pageSize, filtered.length)}</b> of <b>{filtered.length}</b>
          </span>
          <div className="pagination-controls">
            <button className="btn sm" disabled={page === 1} onClick={() => setPage(1)}>First</button>
            <button className="btn sm" disabled={page === 1} onClick={() => setPage(p => p-1)}><I.ChevronLeft size={12}/></button>
            <span className="muted" style={{padding:'0 8px', fontFamily:'var(--font-mono)'}}>{page} / {totalPages}</span>
            <button className="btn sm" disabled={page === totalPages} onClick={() => setPage(p => p+1)}><I.ChevronRight size={12}/></button>
            <button className="btn sm" disabled={page === totalPages} onClick={() => setPage(totalPages)}>Last</button>
          </div>
        </div>
      </div>
    </div>
  );
}

window.PageRuns = PageRuns;
