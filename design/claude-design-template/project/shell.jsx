// ============== Shell: Sidebar + Topbar + Cmd+K ==============

function Sidebar({ route, onNavigate, counts = {} }) {
  const primary = [
    { key: 'overview', label: 'Overview', icon: <I.Home size={15}/> },
    { key: 'flows', label: 'Flows', icon: <I.Layers size={15}/> },
    { key: 'runs', label: 'Runs', icon: <I.Runs size={15}/>, badge: counts.running || null },
    { key: 'approvals', label: 'Approvals', icon: <I.UserCheck size={15}/>, badge: counts.approvals || null },
    { key: 'advisor', label: 'Advisor', icon: <I.Wand size={15}/>, badge: counts.advisor || null },
    { key: 'webhooks', label: 'Webhooks', icon: <I.Outbox size={15}/>, badge: counts.webhooks || null },
  ];
  const secondary = [
    { key: 'settings', label: 'Settings', icon: <I.Settings size={15}/> },
  ];
  const isActive = (k) => route === k || (k === 'flows' && route === 'studio') || (k === 'runs' && route === 'monitor');
  return (
    <aside className="sidebar">
      <div className="sidebar-brand">
        <div className="brand-mark">F</div>
        <div className="brand-text">
          <span>Flow Studio</span>
          <small>padosoft · v2.0</small>
        </div>
      </div>
      <nav className="sidebar-nav">
        <div className="nav-section">
          <div className="nav-label">Orchestrate</div>
          {primary.map(it => (
            <div key={it.key} className={`nav-item ${isActive(it.key) ? 'active' : ''}`} onClick={() => onNavigate(it.key)} data-testid={`nav-${it.key}`}>
              {it.icon}<span>{it.label}</span>
              {it.badge != null && <span className="badge">{it.badge}</span>}
            </div>
          ))}
        </div>
        <div className="nav-section">
          <div className="nav-label">Configure</div>
          {secondary.map(it => (
            <div key={it.key} className={`nav-item ${route === it.key ? 'active' : ''}`} onClick={() => onNavigate(it.key)}>
              {it.icon}<span>{it.label}</span>
            </div>
          ))}
        </div>
      </nav>
      <div className="sidebar-footer">
        <div className="user-chip">
          <div className="avatar">MR</div>
          <div className="user-info"><b>Marco Rossi</b><small>admin · padosoft</small></div>
        </div>
        <button className="iconbtn" title="Account"><I.ChevronDown size={14}/></button>
      </div>
    </aside>
  );
}

function Topbar({ route, theme, onTheme, autoRefresh, onAutoRefresh, onOpenPalette, lastTick }) {
  const labels = { overview:'Overview', flows:'Flows', studio:'Flows › Studio', runs:'Runs', monitor:'Runs › Monitor', approvals:'Approvals', advisor:'Advisor', webhooks:'Webhooks', settings:'Settings' };
  return (
    <header className="topbar">
      <div className="crumbs">
        <span className="muted">Flow Studio</span>
        <span className="sep"><I.ChevronRight size={12}/></span>
        <b>{labels[route] || 'Overview'}</b>
      </div>
      <div className="topbar-spacer" />
      <span className="live-pill" title="Realtime status"><span className="pulse" /><span>Live</span><span style={{opacity:0.7,marginLeft:4}}>· {fmtTime(lastTick)}</span></span>
      <button className="search-trigger" onClick={onOpenPalette} data-testid="search-trigger">
        <I.Search size={13}/><span>Search flows, runs, nodes…</span><span className="kbd">⌘K</span>
      </button>
      <button className="iconbtn" onClick={() => onAutoRefresh(!autoRefresh)} title={autoRefresh ? 'Pause auto-refresh' : 'Resume'}>
        {autoRefresh ? <I.Pause size={14}/> : <I.Play size={14}/>}
      </button>
      <button className="iconbtn" title="Notifications"><I.Bell size={14}/></button>
      <button className="iconbtn" onClick={() => onTheme(theme === 'dark' ? 'light' : 'dark')} title="Toggle theme" data-testid="theme-toggle">
        {theme === 'dark' ? <I.Sun size={14}/> : <I.Moon size={14}/>}
      </button>
    </header>
  );
}

// ============== Command palette ==============
function CommandPalette({ open, onClose, onNavigate, onOpenRun, onAction }) {
  const [q, setQ] = React.useState('');
  const [active, setActive] = React.useState(0);
  const inputRef = React.useRef(null);
  React.useEffect(() => { if (open) { setQ(''); setActive(0); setTimeout(() => inputRef.current?.focus(), 30); } }, [open]);

  const navItems = [
    { label: 'Overview', icon: <I.Home size={14}/>, action: () => onNavigate('overview') },
    { label: 'Flows', icon: <I.Layers size={14}/>, action: () => onNavigate('flows') },
    { label: 'Open Studio (canvas)', icon: <I.Grid size={14}/>, action: () => onNavigate('studio') },
    { label: 'Runs', icon: <I.Runs size={14}/>, action: () => onNavigate('runs') },
    { label: 'Approvals', icon: <I.UserCheck size={14}/>, action: () => onNavigate('approvals') },
    { label: 'Advisor', icon: <I.Wand size={14}/>, action: () => onNavigate('advisor') },
    { label: 'Webhooks', icon: <I.Outbox size={14}/>, action: () => onNavigate('webhooks') },
  ];
  const actionItems = [
    { label: 'New flow (blank canvas)', icon: <I.Plus size={14}/>, action: () => onNavigate('studio') },
    { label: 'Generate flow with AI', icon: <I.Wand size={14}/>, action: () => onAction('builder') },
    { label: 'Toggle theme', icon: <I.Moon size={14}/>, action: () => onAction('theme') },
  ];

  const results = React.useMemo(() => {
    const ql = q.toLowerCase();
    if (!ql) return [
      { section: 'Navigate', items: navItems },
      { section: 'Actions', items: actionItems },
      { section: 'Recent runs', items: window.FLOW.RUNS_LIST.slice(0,4).map(r => ({ label: r.flow, meta: r.id, icon: <I.Hash size={14}/>, action: () => onOpenRun(r.id) })) },
    ];
    const nav = navItems.filter(i => i.label.toLowerCase().includes(ql));
    const act = actionItems.filter(i => i.label.toLowerCase().includes(ql));
    const runs = window.FLOW.RUNS_LIST.filter(r => r.id.includes(ql) || r.flow.toLowerCase().includes(ql)).slice(0,6)
      .map(r => ({ label: r.flow, meta: r.id, icon: <I.Hash size={14}/>, action: () => onOpenRun(r.id) }));
    const flows = window.FLOW.FLOWS_LIBRARY.filter(f => f.name.toLowerCase().includes(ql)).slice(0,4)
      .map(f => ({ label: f.name, meta: `v${f.version}`, icon: <I.Layers size={14}/>, action: () => onNavigate('studio') }));
    const s = [];
    if (nav.length) s.push({ section:'Navigate', items:nav });
    if (act.length) s.push({ section:'Actions', items:act });
    if (flows.length) s.push({ section:'Flows', items:flows });
    if (runs.length) s.push({ section:'Runs', items:runs });
    return s;
  }, [q]);

  const flat = results.flatMap(s => s.items);
  React.useEffect(() => {
    if (!open) return;
    const onKey = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); onClose(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); setActive(a => Math.min(flat.length-1, a+1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(a => Math.max(0, a-1)); }
      else if (e.key === 'Enter') { e.preventDefault(); const it = flat[active]; if (it) { it.action(); onClose(); } }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, flat, active, onClose]);

  if (!open) return null;
  let idx = 0;
  return (
    <>
      <div className="overlay" onClick={onClose}/>
      <div className="palette">
        <input ref={inputRef} className="palette-input" placeholder="Search flows, runs, actions…" value={q} onChange={e => { setQ(e.target.value); setActive(0); }} data-testid="palette-input"/>
        <div className="palette-list">
          {results.length === 0 && <div className="empty" style={{ padding:'32px 16px' }}>No results</div>}
          {results.map((sec, si) => (
            <div key={si}>
              <div className="palette-section">{sec.section}</div>
              {sec.items.map((it, ii) => {
                const cur = idx++;
                return (
                  <div key={ii} className={`palette-item ${cur === active ? 'active' : ''}`} onMouseEnter={() => setActive(cur)} onClick={() => { it.action(); onClose(); }}>
                    <span className="icon">{it.icon}</span><span>{it.label}</span>{it.meta && <span className="meta">{it.meta}</span>}
                  </div>
                );
              })}
            </div>
          ))}
        </div>
        <div className="palette-foot"><span><span className="kbd">↑↓</span> Navigate</span><span><span className="kbd">↵</span> Open</span><span><span className="kbd">esc</span> Close</span></div>
      </div>
    </>
  );
}

Object.assign(window, { Sidebar, Topbar, CommandPalette });
