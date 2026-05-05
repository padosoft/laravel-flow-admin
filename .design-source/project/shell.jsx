// ============== Shell: Sidebar + Topbar + Cmd+K ==============

function Sidebar({ route, onNavigate, counts }) {
  const items = [
    { key: 'home', label: 'Overview', icon: <I.Home size={15}/>, path: '/flow' },
    { key: 'runs', label: 'Runs', icon: <I.Runs size={15}/>, path: '/flow/runs', badge: counts.running ? counts.running : null },
    { key: 'approvals', label: 'Approvals', icon: <I.Approvals size={15}/>, path: '/flow/approvals', badge: counts.approvals ? counts.approvals : null },
    { key: 'outbox', label: 'Outbox', icon: <I.Outbox size={15}/>, path: '/flow/outbox', badge: counts.outbox ? counts.outbox : null },
  ];
  const secondary = [
    { key: 'definitions', label: 'Definitions', icon: <I.Definitions size={15}/>, path: '/flow/definitions' },
    { key: 'settings', label: 'Settings', icon: <I.Settings size={15}/>, path: '/flow/settings' },
  ];
  return (
    <aside className="sidebar">
      <div className="sidebar-brand">
        <div className="brand-mark">F</div>
        <div className="brand-text">
          <span>Flow</span>
          <small>laravel · v3.4</small>
        </div>
      </div>
      <nav className="sidebar-nav">
        <div className="nav-section">
          <div className="nav-label">Operate</div>
          {items.map(it => (
            <div key={it.key}
                 className={`nav-item ${route === it.key ? 'active' : ''}`}
                 onClick={() => onNavigate(it.key)}>
              {it.icon}
              <span>{it.label}</span>
              {it.badge != null && <span className="badge">{it.badge}</span>}
            </div>
          ))}
        </div>
        <div className="nav-section">
          <div className="nav-label">Configure</div>
          {secondary.map(it => (
            <div key={it.key}
                 className={`nav-item ${route === it.key ? 'active' : ''}`}
                 onClick={() => onNavigate(it.key)}>
              {it.icon}
              <span>{it.label}</span>
            </div>
          ))}
        </div>
      </nav>
      <div className="sidebar-footer">
        <div className="user-chip">
          <div className="avatar">AO</div>
          <div className="user-info">
            <b>Alex Operator</b>
            <small>admin · example.test</small>
          </div>
        </div>
        <button className="iconbtn" title="Account"><I.ChevronDown size={14}/></button>
      </div>
    </aside>
  );
}

function Topbar({ route, runId, theme, onTheme, autoRefresh, onAutoRefresh, onOpenPalette, lastTick }) {
  const crumbs = (() => {
    if (route === 'home') return [{ label: 'Overview', current: true }];
    if (route === 'runs') return [{ label: 'Runs', current: true }];
    if (route === 'run-detail') return [
      { label: 'Runs', clickable: true, to: 'runs' },
      { label: runId, current: true, mono: true }
    ];
    if (route === 'approvals') return [{ label: 'Approvals', current: true }];
    if (route === 'outbox') return [{ label: 'Outbox', current: true }];
    if (route === 'definitions') return [{ label: 'Definitions', current: true }];
    if (route === 'settings') return [{ label: 'Settings', current: true }];
    return [];
  })();
  return (
    <header className="topbar">
      <div className="crumbs">
        <span className="muted">Flow</span>
        {crumbs.map((c, i) => (
          <React.Fragment key={i}>
            <span className="sep"><I.ChevronRight size={12}/></span>
            {c.current
              ? <b className={c.mono ? 'mono' : ''} style={c.mono ? {fontSize:12} : null}>{c.label}</b>
              : <span style={{cursor:'pointer'}} className="muted">{c.label}</span>}
          </React.Fragment>
        ))}
      </div>
      <div className="topbar-spacer" />

      <span className="live-pill" title="Auto-refresh status">
        <span className="pulse" />
        <span>Live</span>
        <span style={{opacity:0.7,marginLeft:4}}>· {fmtTime(lastTick)}</span>
      </span>

      <button className="search-trigger" onClick={onOpenPalette}>
        <I.Search size={13}/>
        <span>Search runs, flows, approvals…</span>
        <span className="kbd">⌘K</span>
      </button>

      <button className="iconbtn"
              onClick={() => onAutoRefresh(!autoRefresh)}
              title={autoRefresh ? 'Pause auto-refresh' : 'Resume auto-refresh'}>
        {autoRefresh ? <I.Pause size={14}/> : <I.Play size={14}/>}
      </button>
      <button className="iconbtn" title="Notifications"><I.Bell size={14}/></button>
      <button className="iconbtn" onClick={() => onTheme(theme === 'dark' ? 'light' : 'dark')}
              title="Toggle theme">
        {theme === 'dark' ? <I.Sun size={14}/> : <I.Moon size={14}/>}
      </button>
    </header>
  );
}

// ============== Command palette ==============
function CommandPalette({ open, onClose, runs, onNavigate, onOpenRun }) {
  const [q, setQ] = React.useState('');
  const [active, setActive] = React.useState(0);
  const inputRef = React.useRef(null);

  React.useEffect(() => {
    if (open) {
      setQ(''); setActive(0);
      setTimeout(() => inputRef.current?.focus(), 30);
    }
  }, [open]);

  const navItems = [
    { kind: 'nav', label: 'Overview', icon: <I.Home size={14}/>, action: () => onNavigate('home') },
    { kind: 'nav', label: 'Runs', icon: <I.Runs size={14}/>, action: () => onNavigate('runs') },
    { kind: 'nav', label: 'Approvals', icon: <I.Approvals size={14}/>, action: () => onNavigate('approvals') },
    { kind: 'nav', label: 'Outbox', icon: <I.Outbox size={14}/>, action: () => onNavigate('outbox') },
    { kind: 'nav', label: 'Definitions', icon: <I.Definitions size={14}/>, action: () => onNavigate('definitions') },
  ];

  const results = React.useMemo(() => {
    const ql = q.toLowerCase();
    if (!ql) {
      return [
        { section: 'Navigate', items: navItems },
        { section: 'Recent runs', items: runs.slice(0, 5).map(r => ({
          kind: 'run', label: r.flow_name, meta: r.id, icon: <I.Hash size={14}/>,
          action: () => onOpenRun(r.id),
        }))},
      ];
    }
    const navMatch = navItems.filter(i => i.label.toLowerCase().includes(ql));
    const runMatch = runs.filter(r =>
      r.id.toLowerCase().includes(ql) || r.flow_name.toLowerCase().includes(ql) ||
      r.actor.toLowerCase().includes(ql) || r.correlation_id.toLowerCase().includes(ql)
    ).slice(0, 8).map(r => ({
      kind: 'run', label: r.flow_name, meta: r.id, icon: <I.Hash size={14}/>,
      action: () => onOpenRun(r.id),
    }));
    const sections = [];
    if (navMatch.length) sections.push({ section: 'Navigate', items: navMatch });
    if (runMatch.length) sections.push({ section: 'Runs', items: runMatch });
    return sections;
  }, [q, runs]);

  const flat = results.flatMap(s => s.items);

  React.useEffect(() => {
    if (!open) return;
    const onKey = (e) => {
      if (e.key === 'Escape') { e.preventDefault(); onClose(); }
      else if (e.key === 'ArrowDown') { e.preventDefault(); setActive(a => Math.min(flat.length - 1, a + 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(a => Math.max(0, a - 1)); }
      else if (e.key === 'Enter') { e.preventDefault(); const it = flat[active]; if (it) { it.action(); onClose(); } }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, flat, active, onClose]);

  if (!open) return null;

  let runningIdx = 0;
  return (
    <>
      <div className="overlay" onClick={onClose} />
      <div className="palette">
        <input ref={inputRef} className="palette-input" placeholder="Search runs, navigate to a page…"
               value={q} onChange={e => { setQ(e.target.value); setActive(0); }} />
        <div className="palette-list">
          {results.length === 0 && <div className="empty" style={{padding: '32px 16px'}}>No results</div>}
          {results.map((sec, si) => (
            <div key={si}>
              <div className="palette-section">{sec.section}</div>
              {sec.items.map((it, ii) => {
                const idx = runningIdx++;
                return (
                  <div key={ii}
                       className={`palette-item ${idx === active ? 'active' : ''}`}
                       onMouseEnter={() => setActive(idx)}
                       onClick={() => { it.action(); onClose(); }}>
                    <span className="icon">{it.icon}</span>
                    <span>{it.label}</span>
                    {it.meta && <span className="meta">{it.meta}</span>}
                  </div>
                );
              })}
            </div>
          ))}
        </div>
        <div className="palette-foot">
          <span><span className="kbd">↑↓</span> Navigate</span>
          <span><span className="kbd">↵</span> Open</span>
          <span><span className="kbd">esc</span> Close</span>
        </div>
      </div>
    </>
  );
}

Object.assign(window, { Sidebar, Topbar, CommandPalette });
