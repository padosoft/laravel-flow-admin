// ============== App root ==============

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "theme": "dark",
  "stepViz": "timeline"
}/*EDITMODE-END*/;

function App() {
  // useTweaks returns [values, setTweak]; fallback to local state if not loaded.
  const fallback = React.useState(TWEAK_DEFAULTS);
  const fallbackSetter = React.useCallback((keyOrEdits, val) => {
    const edits = typeof keyOrEdits === 'object' ? keyOrEdits : { [keyOrEdits]: val };
    fallback[1](prev => ({ ...prev, ...edits }));
  }, []);
  const [tweaks, setTweak] = window.useTweaks
    ? window.useTweaks(TWEAK_DEFAULTS)
    : [fallback[0], fallbackSetter];

  // Apply theme to document
  React.useEffect(() => {
    document.documentElement.dataset.theme = tweaks.theme;
  }, [tweaks.theme]);

  React.useEffect(() => {
    window.__tweaks = tweaks;
    window.dispatchEvent(new CustomEvent('flow:stepViz', { detail: tweaks.stepViz }));
  }, [tweaks.stepViz]);

  // ============ Routing (in-memory) ============
  const [route, setRoute] = React.useState('home');
  const [runId, setRunId] = React.useState(null);
  const [paletteOpen, setPaletteOpen] = React.useState(false);

  const openRun = React.useCallback((id) => { setRunId(id); setRoute('run-detail'); }, []);
  const navigate = React.useCallback((r) => { setRoute(r); setRunId(null); }, []);

  // ============ Live data simulation ============
  const [runs, setRuns] = React.useState(() => window.FLOW_DATA.RUNS);
  const [hourly, setHourly] = React.useState(() => window.FLOW_DATA.HOURLY);
  const [kpis, setKpis] = React.useState(() => window.FLOW_DATA.KPIS);
  const [autoRefresh, setAutoRefresh] = React.useState(true);
  const [lastTick, setLastTick] = React.useState(Date.now());

  React.useEffect(() => {
    if (!autoRefresh) return;
    const id = setInterval(() => {
      setLastTick(Date.now());
      setRuns(prev => {
        // Slight reshuffle: progress some running flows, change last_seen
        const next = prev.map(r => {
          if (r.status === 'running' && Math.random() < 0.18) {
            const ns = r.steps_done + 1;
            if (ns >= r.steps_total) return { ...r, status: 'success', steps_done: r.steps_total, finished_at: Date.now(), duration_ms: Date.now() - r.started_at };
            return { ...r, steps_done: ns };
          }
          return r;
        });
        return next;
      });
    }, 4000);
    return () => clearInterval(id);
  }, [autoRefresh]);

  // ============ Cmd+K ============
  React.useEffect(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setPaletteOpen(o => !o);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const counts = React.useMemo(() => ({
    running: runs.filter(r => r.status === 'running').length,
    approvals: runs.filter(r => r.status === 'paused').length,
    outbox: runs.filter(r => r.status === 'failed').length,
  }), [runs]);

  const navKey = route === 'run-detail' ? 'runs' : route;

  return (
    <ToastProvider>
      <div className="app">
        <Sidebar route={navKey} onNavigate={navigate} counts={counts}/>
        <div className="main">
          <Topbar route={route} runId={runId} theme={tweaks.theme}
                  onTheme={(th) => setTweak('theme', th)}
                  autoRefresh={autoRefresh} onAutoRefresh={setAutoRefresh}
                  onOpenPalette={() => setPaletteOpen(true)}
                  lastTick={lastTick}/>
          <div className="content">
            {route === 'home' && (
              <PageOverview runs={runs} hourly={hourly} kpis={kpis}
                            onOpenRun={openRun} onNavigate={navigate} lastTick={lastTick}/>
            )}
            {route === 'runs' && (
              <PageRuns runs={runs} onOpenRun={openRun}/>
            )}
            {route === 'run-detail' && runId && (
              <PageRunDetail runId={runId} runs={runs}
                             stepViz={tweaks.stepViz}
                             onBack={() => navigate('runs')}
                             onTriggerReplay={() => navigate('runs')}/>
            )}
            {route === 'approvals' && (
              <PageApprovals runs={runs} onOpenRun={openRun}/>
            )}
            {route === 'outbox' && (
              <PageOutbox runs={runs}/>
            )}
            {route === 'definitions' && (
              <PageDefinitions runs={runs}/>
            )}
            {route === 'settings' && (
              <PageSettings/>
            )}
          </div>
        </div>

        <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)}
                        runs={runs} onNavigate={navigate} onOpenRun={openRun}/>
      </div>

      {/* Tweaks panel */}
      <FlowTweaks tweaks={tweaks} setTweak={setTweak}/>
    </ToastProvider>
  );
}

function PageSettings() {
  return (
    <div className="page" data-screen-label="Settings">
      <div className="page-head">
        <div>
          <h1 className="page-title">Settings</h1>
          <p className="page-sub">Authorizer policies, retention, and webhook signing</p>
        </div>
      </div>
      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr', gap:16}}>
        <div className="card">
          <div className="card-head"><h3 className="card-title">Authorizer</h3></div>
          <div className="card-body">
            <dl className="kv">
              <dt>Policy class</dt><dd>App\Flows\Policies\AdminAuthorizer</dd>
              <dt>Token TTL</dt><dd>15 minutes</dd>
              <dt>Required role</dt><dd>flow.operator</dd>
              <dt>2FA</dt><dd>required for replay & cancel</dd>
            </dl>
          </div>
        </div>
        <div className="card">
          <div className="card-head"><h3 className="card-title">Retention</h3></div>
          <div className="card-body">
            <dl className="kv">
              <dt>Successful runs</dt><dd>30 days</dd>
              <dt>Failed runs</dt><dd>180 days</dd>
              <dt>Audit events</dt><dd>365 days</dd>
              <dt>Outbox</dt><dd>14 days after delivery</dd>
            </dl>
          </div>
        </div>
        <div className="card">
          <div className="card-head"><h3 className="card-title">Webhook signing</h3></div>
          <div className="card-body">
            <dl className="kv">
              <dt>Algorithm</dt><dd>HMAC-SHA256</dd>
              <dt>Header</dt><dd>X-Flow-Signature</dd>
              <dt>Secret rotation</dt><dd>every 90 days</dd>
            </dl>
          </div>
        </div>
        <div className="card">
          <div className="card-head"><h3 className="card-title">Queue</h3></div>
          <div className="card-body">
            <dl className="kv">
              <dt>Driver</dt><dd>redis</dd>
              <dt>Connection</dt><dd>flow_default</dd>
              <dt>Workers</dt><dd>8 active · 2 idle</dd>
              <dt>Backpressure</dt><dd>none</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  );
}

// ============== Custom Tweaks panel ==============
function FlowTweaks({ tweaks, setTweak }) {
  if (!window.TweaksPanel) return null;
  const TweaksPanel = window.TweaksPanel;
  const TweakSection = window.TweakSection;
  const TweakRadio = window.TweakRadio;
  return (
    <TweaksPanel title="Tweaks">
      <TweakSection title="Theme">
        <TweakRadio label="Mode" value={tweaks.theme}
                    options={[{ value: 'light', label: 'Light' }, { value: 'dark', label: 'Dark' }]}
                    onChange={v => setTweak('theme', v)}/>
      </TweakSection>
      <TweakSection title="Steps visualization">
        <TweakRadio label="Layout" value={tweaks.stepViz}
                    options={[
                      { value: 'timeline', label: 'Timeline' },
                      { value: 'gantt', label: 'Gantt' },
                      { value: 'dag', label: 'DAG' },
                    ]}
                    onChange={v => setTweak('stepViz', v)}/>
      </TweakSection>
    </TweaksPanel>
  );
}

window.App = App;

// Mount
ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
