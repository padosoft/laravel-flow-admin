// ============== Run Detail Page ==============

function PageRunDetail({ runId, runs, onBack, onTriggerReplay, stepViz = 'timeline' }) {
  const baseRun = runs.find(r => r.id === runId);
  const run = React.useMemo(() => baseRun ? window.FLOW_DATA.genRunDetail(baseRun) : null, [baseRun]);
  const [selectedStep, setSelectedStep] = React.useState(0);
  const [tab, setTab] = React.useState('details');
  const [drawerOpen, setDrawerOpen] = React.useState(false);
  const toast = useToast();

  if (!run) return <div className="page"><div className="empty">Run not found</div></div>;

  const step = run.steps[selectedStep];
  const maxStepDur = Math.max(...run.steps.map(s => s.duration_ms || 0), 1);

  const [confirmReplay, setConfirmReplay] = React.useState(false);
  const [confirmCancel, setConfirmCancel] = React.useState(false);

  return (
    <div className="page" data-screen-label="Run Detail" data-step-viz={stepViz}>
      {/* Header */}
      <div className="page-head" style={{alignItems:'flex-start'}}>
        <div style={{minWidth:0,flex:1}}>
          <button className="btn ghost sm" onClick={onBack} style={{marginBottom:8}}>
            <I.ChevronLeft size={12}/> Back to runs
          </button>
          <div style={{display:'flex',alignItems:'center',gap:12,flexWrap:'wrap'}}>
            <h1 className="page-title" style={{margin:0}}>{run.flow_name}</h1>
            <span className="tertiary mono" style={{fontSize:13}}>{run.version}</span>
            <StatusBadge status={run.status} />
          </div>
          <div className="run-meta-bar">
            <div className="item">
              <small>Run ID</small>
              <b style={{display:'flex',alignItems:'center',gap:6}}>
                {run.id}
                <button className="iconbtn" style={{width:20,height:20}}
                        onClick={() => { navigator.clipboard?.writeText(run.id); toast.push({title:'Copied', body:run.id});}}>
                  <I.Copy size={11}/>
                </button>
              </b>
            </div>
            <div className="item">
              <small>Correlation</small>
              <b>{run.correlation_id}</b>
            </div>
            <div className="item">
              <small>Started</small>
              <b>{fmtDateTime(run.started_at)}</b>
            </div>
            <div className="item">
              <small>Duration</small>
              <b>{fmtDuration(run.duration_ms)}</b>
            </div>
            <div className="item">
              <small>Triggered by</small>
              <b>{run.triggered_by}</b>
            </div>
            <div className="item">
              <small>Actor</small>
              <b style={{fontFamily:'var(--font-sans)'}}>{run.actor}</b>
            </div>
          </div>
        </div>
        <div className="page-actions">
          <button className="btn" onClick={() => setDrawerOpen(true)}>
            <I.Code size={13}/> JSON
          </button>
          {run.status === 'failed' && (
            <button className="btn" onClick={() => setConfirmReplay(true)}>
              <I.Replay size={13}/> Replay
            </button>
          )}
          {(run.status === 'running' || run.status === 'paused') && (
            <button className="btn danger" onClick={() => setConfirmCancel(true)}>
              <I.Cancel size={13}/> Cancel
            </button>
          )}
        </div>
      </div>

      {/* Steps + Detail panel */}
      <div className="run-grid">
        {/* Steps panel */}
        <div className="card">
          <div className="card-head">
            <div>
              <h3 className="card-title">Steps</h3>
              <p className="card-sub">{run.steps_done} of {run.steps_total} completed</p>
            </div>
            <span className="badge outline">{stepViz}</span>
          </div>
          <div className="card-body flush">
            <div className="step-list">
              {run.steps.map((s, i) => (
                <div key={s.id} className={`step ${s.status} ${i === selectedStep ? 'selected' : ''}`}
                     onClick={() => { setSelectedStep(i); setTab('details'); }}>
                  <div className="step-rail">
                    <div className="node"/>
                    <div className="line"/>
                  </div>
                  <div className="step-body">
                    <div className="step-row1">
                      <span className="step-name">{s.name}</span>
                      <span className="tertiary mono" style={{fontSize:11}}>#{i+1}</span>
                      <span style={{flex:1}}/>
                      <StatusBadge status={s.status}/>
                    </div>
                    <div className="step-row2">
                      <span className="mono">{fmtDuration(s.duration_ms)}</span>
                      {s.attempts > 1 && <span>· {s.attempts} attempts</span>}
                      {s.status !== 'pending' && <span>· {fmtTime(s.started_at)}</span>}
                    </div>
                    <div className="step-gantt" data-dur={fmtDuration(s.duration_ms)}>
                      <div className="step-gantt-fill" style={{
                        width: s.status === 'pending' ? 0 :
                               `${Math.max(4, (s.duration_ms / maxStepDur) * 100)}%`,
                      }}/>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Detail pane */}
        <div className="detail-pane">
          <div className="detail-head">
            <div>
              <div style={{fontSize:13,fontWeight:600}}>{step.name}</div>
              <div className="tertiary" style={{fontSize:11,fontFamily:'var(--font-mono)',marginTop:2}}>
                {step.handler}
              </div>
            </div>
            <StatusBadge status={step.status}/>
          </div>
          <div className="detail-tabs">
            {[
              { k:'details', label:'Details' },
              { k:'input', label:'Input' },
              { k:'output', label:'Output' },
              { k:'audit', label:'Audit', count: run.audit.length },
            ].map(t => (
              <div key={t.k} className={`tab ${tab === t.k ? 'active' : ''}`} onClick={() => setTab(t.k)}>
                {t.label} {t.count != null && <span className="badge outline" style={{fontSize:10}}>{t.count}</span>}
              </div>
            ))}
          </div>
          <div className="detail-content">
            {tab === 'details' && (
              <>
                <dl className="kv">
                  <dt>Step ID</dt><dd>{step.id}</dd>
                  <dt>Handler</dt><dd>{step.handler}</dd>
                  <dt>Status</dt><dd><StatusBadge status={step.status}/></dd>
                  <dt>Started</dt><dd>{step.status === 'pending' ? '—' : fmtDateTime(step.started_at)}</dd>
                  <dt>Duration</dt><dd>{fmtDuration(step.duration_ms)}</dd>
                  <dt>Attempts</dt><dd>{step.attempts}</dd>
                </dl>

                {step.error && (
                  <div style={{marginTop:18}}>
                    <div style={{fontSize:11,textTransform:'uppercase',letterSpacing:'0.05em',color:'var(--text-tertiary)',fontWeight:600,marginBottom:6}}>
                      Error
                    </div>
                    <pre className="code-block" style={{borderColor:'var(--status-failed)', background:'var(--status-failed-bg)'}}>
{step.error.class}
{'\n'}{step.error.message}
                    </pre>
                  </div>
                )}
              </>
            )}
            {tab === 'input' && (
              step.input
                ? <pre className="code-block" dangerouslySetInnerHTML={{__html: jsonHighlight(step.input)}}/>
                : <div className="empty">No input recorded</div>
            )}
            {tab === 'output' && (
              step.output
                ? <pre className="code-block" dangerouslySetInnerHTML={{__html: jsonHighlight(step.output)}}/>
                : <div className="empty">No output yet</div>
            )}
            {tab === 'audit' && (
              <div className="audit-list" style={{margin:'-16px'}}>
                {run.audit.map((a, i) => (
                  <div key={i} className="audit-item">
                    <I.Activity size={14} className="audit-icon"/>
                    <div className="audit-event">
                      <b className="mono" style={{fontSize:12}}>{a.event}</b>
                      <small>{a.detail} · <span className="mono">{a.actor}</span></small>
                    </div>
                    <time>{fmtTime(a.ts)}</time>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* JSON Drawer */}
      <Drawer open={drawerOpen} onClose={() => setDrawerOpen(false)}
              title={<>Payload · <span className="mono" style={{fontSize:12,color:'var(--text-secondary)'}}>{run.id}</span></>}
              actions={<button className="btn sm"
                onClick={() => { navigator.clipboard?.writeText(JSON.stringify(run.payload, null, 2)); toast.push({title:'Copied JSON'}); }}>
                <I.Copy size={12}/> Copy
              </button>}>
        <pre className="code-block" style={{margin:0, border:0, borderRadius:0, height:'100%'}}
             dangerouslySetInnerHTML={{__html: jsonHighlight(run.payload)}}/>
      </Drawer>

      {/* Replay confirmation */}
      <Modal open={confirmReplay} onClose={() => setConfirmReplay(false)}
             title="Replay run" sub="A new run will be created with the same input payload."
             footer={<>
               <button className="btn" onClick={() => setConfirmReplay(false)}>Cancel</button>
               <button className="btn primary" onClick={() => {
                 setConfirmReplay(false);
                 toast.push({title:'Replay queued', body:`New run will inherit input from ${run.id}`});
                 onTriggerReplay?.(run);
               }}>
                 <I.Replay size={13}/> Confirm replay
               </button>
             </>}>
        <dl className="kv">
          <dt>From run</dt><dd>{run.id}</dd>
          <dt>Flow</dt><dd>{run.flow_name} {run.version}</dd>
          <dt>Idempotency</dt><dd>new key will be generated</dd>
        </dl>
      </Modal>

      <Modal open={confirmCancel} onClose={() => setConfirmCancel(false)}
             title="Cancel run" sub="The current step will be interrupted; compensation may run."
             footer={<>
               <button className="btn" onClick={() => setConfirmCancel(false)}>Keep running</button>
               <button className="btn danger" onClick={() => {
                 setConfirmCancel(false);
                 toast.push({title:'Cancellation requested', body:run.id, kind:'warn'});
               }}><I.Cancel size={13}/> Cancel run</button>
             </>}>
        <p style={{margin:0}}>Are you sure you want to cancel <span className="mono">{run.id}</span>? This action cannot be undone.</p>
      </Modal>
    </div>
  );
}

window.PageRunDetail = PageRunDetail;
