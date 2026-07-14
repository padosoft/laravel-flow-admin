// ============================================================
// Flow Studio — app root & routing
// ============================================================

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "theme": "dark"
}/*EDITMODE-END*/;

function App() {
  const fallback = React.useState(TWEAK_DEFAULTS);
  const fallbackSetter = React.useCallback((keyOrEdits, val) => {
    const edits = typeof keyOrEdits === 'object' ? keyOrEdits : { [keyOrEdits]: val };
    fallback[1](prev => ({ ...prev, ...edits }));
  }, []);
  const [tweaks, setTweak] = window.useTweaks ? window.useTweaks(TWEAK_DEFAULTS) : [fallback[0], fallbackSetter];

  React.useEffect(() => { document.documentElement.dataset.theme = tweaks.theme; }, [tweaks.theme]);

  const [route, setRoute] = React.useState('overview');
  const [paletteOpen, setPaletteOpen] = React.useState(false);
  const [builderOpen, setBuilderOpen] = React.useState(false);
  const [autoRefresh, setAutoRefresh] = React.useState(true);
  const [lastTick, setLastTick] = React.useState(Date.now());

  React.useEffect(() => {
    if (!autoRefresh) return;
    const id = setInterval(() => setLastTick(Date.now()), 5000);
    return () => clearInterval(id);
  }, [autoRefresh]);

  React.useEffect(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); setPaletteOpen(o => !o); }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const navigate = (r) => setRoute(r);
  const openRun = () => setRoute('monitor');
  const openStudio = () => setRoute('studio');
  const toggleTheme = () => setTweak('theme', tweaks.theme === 'dark' ? 'light' : 'dark');
  const paletteAction = (a) => { if (a === 'builder') setBuilderOpen(true); else if (a === 'theme') toggleTheme(); };

  const counts = { running: 2, approvals: window.FLOW.APPROVALS.length, advisor: window.FLOW.ADVISOR_SUGGESTIONS.length, webhooks: window.FLOW.WEBHOOKS_OUTBOX.filter(w=>w.status!=='delivered').length };

  // Studio & Monitor are full-bleed (own their layout inside content)
  const fullBleed = route === 'studio' || route === 'monitor';

  return (
    <ToastProvider>
      <AppInner tweaks={tweaks} setTweak={setTweak} route={route} navigate={navigate}
                openRun={openRun} openStudio={openStudio} toggleTheme={toggleTheme}
                paletteOpen={paletteOpen} setPaletteOpen={setPaletteOpen}
                builderOpen={builderOpen} setBuilderOpen={setBuilderOpen}
                autoRefresh={autoRefresh} setAutoRefresh={setAutoRefresh}
                lastTick={lastTick} counts={counts} fullBleed={fullBleed}
                paletteAction={paletteAction}/>
    </ToastProvider>
  );
}

function AppInner(props) {
  const { tweaks, setTweak, route, navigate, openRun, openStudio, toggleTheme,
          paletteOpen, setPaletteOpen, builderOpen, setBuilderOpen,
          autoRefresh, setAutoRefresh, lastTick, counts, fullBleed, paletteAction } = props;
  const toast = useToast();

  return (
    <>
      <div className="app">
        <Sidebar route={route} onNavigate={navigate} counts={counts}/>
        <div className="main">
          <Topbar route={route} theme={tweaks.theme} onTheme={(t)=>setTweak('theme', t)}
                  autoRefresh={autoRefresh} onAutoRefresh={setAutoRefresh}
                  onOpenPalette={() => setPaletteOpen(true)} lastTick={lastTick}/>
          <div className="content" style={fullBleed ? { overflow:'hidden', padding:0 } : null}>
            {route === 'overview' && <OverviewPage onNavigate={navigate} onOpenRun={openRun}/>}
            {route === 'flows' && <FlowsPage onOpenStudio={openStudio} onOpenBuilder={() => setBuilderOpen(true)}/>}
            {route === 'studio' && <StudioPage onOpenBuilder={() => setBuilderOpen(true)} toast={toast}/>}
            {route === 'runs' && <RunsPage onOpenRun={openRun}/>}
            {route === 'monitor' && <RunMonitor onBack={() => navigate('runs')} toast={toast} autoRefresh={autoRefresh}/>}
            {route === 'approvals' && <ApprovalsPage onOpenRun={openRun} toast={toast}/>}
            {route === 'advisor' && <AdvisorPage onOpenStudio={openStudio} toast={toast}/>}
            {route === 'webhooks' && <WebhooksPage toast={toast}/>}
            {route === 'settings' && <SettingsPage/>}
          </div>
        </div>

        <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)}
                        onNavigate={navigate} onOpenRun={openRun} onAction={paletteAction}/>
        <AIBuilder open={builderOpen} onClose={() => setBuilderOpen(false)} onInsert={() => { toast.push({ title:'Inserted', body:'Graph added to canvas' }); navigate('studio'); }}/>
      </div>

      <FlowTweaks tweaks={tweaks} setTweak={setTweak}/>
    </>
  );
}

function FlowTweaks({ tweaks, setTweak }) {
  if (!window.TweaksPanel) return null;
  const { TweaksPanel, TweakSection, TweakRadio } = window;
  return (
    <TweaksPanel title="Tweaks">
      <TweakSection title="Theme">
        <TweakRadio label="Mode" value={tweaks.theme}
                    options={[{ value:'light', label:'Light' }, { value:'dark', label:'Dark' }]}
                    onChange={v => setTweak('theme', v)}/>
      </TweakSection>
    </TweaksPanel>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App/>);
