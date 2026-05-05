// ============== Landing / Overview Page ==============

function PageOverview({ runs, hourly, kpis, onOpenRun, onNavigate, lastTick }) {
  const recent = runs.slice(0, 8);
  const pendingApprovals = runs.filter(r => r.status === 'paused').slice(0, 5);
  const failedRecent = runs.filter(r => r.status === 'failed').slice(0, 5);

  // Sparkline data (last 24h totals)
  const successSpark = hourly.map(h => h.success);
  const failSpark = hourly.map(h => h.failed);
  const runningSpark = hourly.map(h => h.success + h.failed + h.running);
  const durSpark = Array.from({length: 24}, (_, i) => 200 + Math.sin(i * 0.4) * 80 + (i * 5));

  const maxBar = Math.max(...hourly.map(h => h.success + h.failed + h.running), 1);

  return (
    <div className="page" data-screen-label="Overview">
      <div className="page-head">
        <div>
          <h1 className="page-title">Overview</h1>
          <p className="page-sub">All flow activity across your application · last 24 hours</p>
        </div>
        <div className="page-actions">
          <button className="btn"><I.Refresh size={13}/> Refresh</button>
          <button className="btn primary"><I.Plus size={13}/> Trigger flow</button>
        </div>
      </div>

      {/* KPIs */}
      <div className="kpi-grid">
        <div className="kpi">
          <div className="kpi-label"><I.Activity size={11}/> Runs (24h)</div>
          <div className="kpi-value">{kpis.runs_24h.toLocaleString()}</div>
          <div className="kpi-delta up"><I.ArrowUp size={11}/> +12.4% vs prev</div>
          <div className="kpi-spark"><Sparkline data={runningSpark} color="var(--text)" /></div>
        </div>
        <div className="kpi">
          <div className="kpi-label"><I.Check size={11}/> Success rate</div>
          <div className="kpi-value">{kpis.success_rate.toFixed(1)}<span style={{fontSize:18,color:'var(--text-tertiary)',marginLeft:2}}>%</span></div>
          <div className="kpi-delta up"><I.ArrowUp size={11}/> +0.6 pts</div>
          <div className="kpi-spark"><Sparkline data={successSpark} color="var(--status-success)" /></div>
        </div>
        <div className="kpi">
          <div className="kpi-label"><I.AlertTriangle size={11}/> Failures (24h)</div>
          <div className="kpi-value">{kpis.failed_24h}</div>
          <div className="kpi-delta down"><I.ArrowDown size={11}/> -3 vs prev</div>
          <div className="kpi-spark"><Sparkline data={failSpark} color="var(--status-failed)" /></div>
        </div>
        <div className="kpi">
          <div className="kpi-label"><I.Clock size={11}/> p95 duration</div>
          <div className="kpi-value">{fmtDuration(kpis.p95_duration_ms)}</div>
          <div className="kpi-delta flat">~ stable</div>
          <div className="kpi-spark"><Sparkline data={durSpark} color="var(--status-running)" /></div>
        </div>
      </div>

      {/* Hourly chart */}
      <div className="card" style={{marginBottom: 16}}>
        <div className="card-head">
          <div>
            <h3 className="card-title">Throughput · last 24h (UTC)</h3>
            <p className="card-sub">Runs started, by hour · stacked by terminal status</p>
          </div>
          <div style={{display:'flex',gap:14,fontSize:11,color:'var(--text-secondary)'}}>
            <span style={{display:'flex',alignItems:'center',gap:6}}><span style={{width:8,height:8,borderRadius:2,background:'var(--status-success)'}}/>Success</span>
            <span style={{display:'flex',alignItems:'center',gap:6}}><span style={{width:8,height:8,borderRadius:2,background:'var(--status-failed)'}}/>Failed</span>
            <span style={{display:'flex',alignItems:'center',gap:6}}><span style={{width:8,height:8,borderRadius:2,background:'var(--status-running)'}}/>Running</span>
          </div>
        </div>
        <div className="card-body">
          <div className="thru-chart">
            {hourly.map((h, i) => {
              const total = h.success + h.failed + h.running;
              const heightPct = Math.max((total / maxBar) * 100, total > 0 ? 4 : 0);
              return (
                <div key={i} className="thru-col">
                  <div className="thru-bar-wrap">
                    <div className="thru-bar" title={`${h.label} — ${total} runs (${h.success}✓ ${h.failed}✗ ${h.running}●)`}
                         style={{ height: `${heightPct}%` }}>
                      {h.running > 0 && <div className="thru-seg run" style={{flex: h.running}}/>}
                      {h.failed > 0 && <div className="thru-seg fail" style={{flex: h.failed}}/>}
                      {h.success > 0 && <div className="thru-seg ok" style={{flex: h.success}}/>}
                    </div>
                  </div>
                  <div className="thru-lbl">{i % 4 === 0 ? h.label : ''}</div>
                </div>
              );
            })}
          </div>
        </div>
      </div>

      {/* Three columns */}
      <div style={{display:'grid', gridTemplateColumns:'1.4fr 1fr 1fr', gap: 16}}>
        {/* Recent runs */}
        <div className="card">
          <div className="card-head">
            <h3 className="card-title">Recent runs</h3>
            <button className="btn ghost sm" onClick={() => onNavigate('runs')}>
              View all <I.ArrowRight size={11}/>
            </button>
          </div>
          <div className="card-body flush">
            <div className="table-wrap">
              <table className="tbl">
                <thead>
                  <tr>
                    <th style={{width: 28}}></th>
                    <th>Flow</th>
                    <th>Run ID</th>
                    <th>Started</th>
                    <th className="num">Duration</th>
                  </tr>
                </thead>
                <tbody>
                  {recent.map(r => (
                    <tr key={r.id} onClick={() => onOpenRun(r.id)}>
                      <td><StatusBadge status={r.status} /></td>
                      <td><b style={{fontWeight:500}}>{r.flow_name}</b> <span className="tertiary mono" style={{fontSize:11}}>{r.version}</span></td>
                      <td><span className="mono" style={{fontSize:11.5}}>{r.id}</span></td>
                      <td className="muted">{fmtRelative(r.started_at)}</td>
                      <td className="num muted">{fmtDuration(r.duration_ms)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {/* Pending approvals */}
        <div className="card">
          <div className="card-head">
            <h3 className="card-title">Pending approvals</h3>
            <button className="btn ghost sm" onClick={() => onNavigate('approvals')}>
              View all <I.ArrowRight size={11}/>
            </button>
          </div>
          <div className="card-body flush">
            {pendingApprovals.length === 0 ? (
              <div className="empty">No pending approvals</div>
            ) : pendingApprovals.map(r => (
              <div key={r.id} className="approval-card" onClick={() => onOpenRun(r.id)} style={{cursor:'pointer'}}>
                <div className="approval-info">
                  <b>{r.flow_name}</b>
                  <small><span className="mono">{r.id}</span> · paused {fmtRelative(r.started_at)}</small>
                </div>
                <StatusBadge status="paused" />
              </div>
            ))}
          </div>
        </div>

        {/* Recent failures */}
        <div className="card">
          <div className="card-head">
            <h3 className="card-title">Recent failures</h3>
            <button className="btn ghost sm" onClick={() => onNavigate('runs')}>
              View all <I.ArrowRight size={11}/>
            </button>
          </div>
          <div className="card-body flush">
            {failedRecent.length === 0 ? (
              <div className="empty">No recent failures · nice</div>
            ) : failedRecent.map(r => (
              <div key={r.id} className="approval-card" onClick={() => onOpenRun(r.id)} style={{cursor:'pointer'}}>
                <div className="approval-info">
                  <b>{r.flow_name}</b>
                  <small><span className="mono">{r.id}</span> · {fmtRelative(r.started_at)}</small>
                </div>
                <StatusBadge status="failed" />
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

window.PageOverview = PageOverview;
