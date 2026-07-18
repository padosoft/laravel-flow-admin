// ============== Icons (lucide-style, 16px stroke 1.75) ==============
const Icon = ({ d, size = 16, fill = 'none', children, ...rest }) => (
  <svg viewBox="0 0 24 24" width={size} height={size} fill={fill} stroke="currentColor"
       strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" {...rest}>
    {d ? <path d={d} /> : children}
  </svg>
);

const I = {
  // Brand mark for Flow
  Logo: (p) => <Icon {...p}><path d="M5 5h10l4 4v10H5z"/><path d="M9 13l2 2 4-4"/></Icon>,
  // Nav icons
  Home: (p) => <Icon {...p}><path d="M3 12l9-8 9 8"/><path d="M5 10v10h14V10"/></Icon>,
  Runs: (p) => <Icon {...p}><path d="M3 6h18M3 12h18M3 18h12"/></Icon>,
  Approvals: (p) => <Icon {...p}><path d="M5 12l4 4 10-10"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></Icon>,
  Outbox: (p) => <Icon {...p}><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></Icon>,
  Definitions: (p) => <Icon {...p}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></Icon>,
  Settings: (p) => <Icon {...p}><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.36.16.68.4.92.7"/></Icon>,
  // UI
  Search: (p) => <Icon {...p}><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></Icon>,
  Bell: (p) => <Icon {...p}><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/></Icon>,
  Sun: (p) => <Icon {...p}><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></Icon>,
  Moon: (p) => <Icon {...p}><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></Icon>,
  Refresh: (p) => <Icon {...p}><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></Icon>,
  Pause: (p) => <Icon {...p}><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></Icon>,
  Play: (p) => <Icon {...p} fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></Icon>,
  Filter: (p) => <Icon {...p}><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></Icon>,
  Plus: (p) => <Icon {...p}><path d="M12 5v14M5 12h14"/></Icon>,
  ChevronDown: (p) => <Icon {...p}><path d="m6 9 6 6 6-6"/></Icon>,
  ChevronRight: (p) => <Icon {...p}><path d="m9 18 6-6-6-6"/></Icon>,
  ChevronLeft: (p) => <Icon {...p}><path d="m15 18-6-6 6-6"/></Icon>,
  X: (p) => <Icon {...p}><path d="M18 6 6 18M6 6l12 12"/></Icon>,
  Copy: (p) => <Icon {...p}><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></Icon>,
  External: (p) => <Icon {...p}><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></Icon>,
  Check: (p) => <Icon {...p}><path d="M20 6 9 17l-5-5"/></Icon>,
  AlertTriangle: (p) => <Icon {...p}><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></Icon>,
  Clock: (p) => <Icon {...p}><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></Icon>,
  Activity: (p) => <Icon {...p}><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></Icon>,
  Send: (p) => <Icon {...p}><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></Icon>,
  ArrowUp: (p) => <Icon {...p}><path d="M12 19V5M5 12l7-7 7 7"/></Icon>,
  ArrowDown: (p) => <Icon {...p}><path d="M12 5v14M19 12l-7 7-7-7"/></Icon>,
  Sparkle: (p) => <Icon {...p}><path d="M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3z"/></Icon>,
  Replay: (p) => <Icon {...p}><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 4 3 10 9 10"/></Icon>,
  Cancel: (p) => <Icon {...p}><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></Icon>,
  Resume: (p) => <Icon {...p}><polygon points="5 3 19 12 5 21 5 3" fill="currentColor" stroke="none"/></Icon>,
  Code: (p) => <Icon {...p}><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></Icon>,
  User: (p) => <Icon {...p}><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></Icon>,
  Hash: (p) => <Icon {...p}><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></Icon>,
  ArrowRight: (p) => <Icon {...p}><path d="M5 12h14M12 5l7 7-7 7"/></Icon>,
  // Flow Studio node & canvas icons
  GitBranch: (p) => <Icon {...p}><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></Icon>,
  Repeat: (p) => <Icon {...p}><path d="m17 2 4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/></Icon>,
  Square: (p) => <Icon {...p}><rect x="4" y="4" width="16" height="16" rx="2"/></Icon>,
  Shuffle: (p) => <Icon {...p}><path d="M16 3h5v5"/><path d="M4 20 21 3"/><path d="M21 16v5h-5"/><path d="m15 15 6 6"/><path d="M4 4l5 5"/></Icon>,
  Merge: (p) => <Icon {...p}><path d="m8 6 4-4 4 4"/><path d="M12 2v10.3a4 4 0 0 1-1.17 2.83L8 18"/><path d="m16 18 2.83-2.87A4 4 0 0 0 20 12.3V2"/></Icon>,
  Globe: (p) => <Icon {...p}><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20"/></Icon>,
  Database: (p) => <Icon {...p}><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></Icon>,
  Tag: (p) => <Icon {...p}><path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.41 0l8.59-8.59a1 1 0 0 0 0-1.41z"/><circle cx="7" cy="7" r="1.5"/></Icon>,
  Eye: (p) => <Icon {...p}><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></Icon>,
  UserCheck: (p) => <Icon {...p}><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/></Icon>,
  Edit: (p) => <Icon {...p}><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/></Icon>,
  Boxes: (p) => <Icon {...p}><path d="M2.97 12.92A2 2 0 0 0 2 14.63v3.24a2 2 0 0 0 .97 1.71l3 1.8a2 2 0 0 0 2.06 0L12 19v-5.5l-5-3-4.03 2.42Z"/><path d="m7 16.5-4.74-2.85M7 16.5l5-3M7 16.5v5.17"/><path d="M12 13.5V19l3.97 2.38a2 2 0 0 0 2.06 0l3-1.8a2 2 0 0 0 .97-1.71v-3.24a2 2 0 0 0-.97-1.71L17 10.5l-5 3Z"/></Icon>,
  Zap: (p) => <Icon {...p} fill="currentColor" stroke="none"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></Icon>,
  Undo: (p) => <Icon {...p}><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></Icon>,
  Redo: (p) => <Icon {...p}><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3L21 13"/></Icon>,
  Trash: (p) => <Icon {...p}><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></Icon>,
  Maximize: (p) => <Icon {...p}><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></Icon>,
  ZoomIn: (p) => <Icon {...p}><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M11 8v6M8 11h6"/></Icon>,
  ZoomOut: (p) => <Icon {...p}><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3M8 11h6"/></Icon>,
  Grid: (p) => <Icon {...p}><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></Icon>,
  Diff: (p) => <Icon {...p}><path d="M12 3v14M5 10h14M5 21h14"/></Icon>,
  Save: (p) => <Icon {...p}><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></Icon>,
  Rocket: (p) => <Icon {...p}><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></Icon>,
  Wand: (p) => <Icon {...p}><path d="M15 4V2M15 10V8M12.5 6.5h-2M19.5 6.5h-2"/><path d="m3 21 12-12 2 2L5 23zM17 8l1.5 1.5"/></Icon>,
  Play2: (p) => <Icon {...p}><polygon points="6 3 20 12 6 21 6 3"/></Icon>,
  Beaker: (p) => <Icon {...p}><path d="M4.5 3h15M6 3v13a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V3M6 14h12"/></Icon>,
  Info: (p) => <Icon {...p}><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></Icon>,
  Link: (p) => <Icon {...p}><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></Icon>,
  Fit: (p) => <Icon {...p}><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/></Icon>,
  Dot: (p) => <Icon {...p} fill="currentColor" stroke="none"><circle cx="12" cy="12" r="4"/></Icon>,
  Layers: (p) => <Icon {...p}><path d="m12 2 9 5-9 5-9-5 9-5z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/></Icon>,
  Compass: (p) => <Icon {...p}><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></Icon>,
};

// ============== Status Badge ==============
function StatusBadge({ status, size }) {
  const labels = {
    running: 'Running', success: 'Succeeded', failed: 'Failed',
    paused: 'Paused', pending: 'Pending', compensated: 'Compensated',
    delivered: 'Delivered', dead: 'Dead-letter',
  };
  return (
    <span className={`badge ${status}`}>
      <span className="dot" />
      {labels[status] || status}
    </span>
  );
}

// ============== Time helpers ==============
function fmtRelative(ts) {
  const ref = new Date('2026-05-05T14:32:00Z').getTime();
  const diff = (ref - ts) / 1000;
  if (diff < 60) return `${Math.floor(diff)}s ago`;
  if (diff < 3600) return `${Math.floor(diff/60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;
  return `${Math.floor(diff/86400)}d ago`;
}
function fmtTime(ts) {
  const d = new Date(ts);
  return d.toISOString().slice(11, 19) + 'Z';
}
function fmtDateTime(ts) {
  const d = new Date(ts);
  return d.toISOString().slice(0, 19).replace('T', ' ') + 'Z';
}
function fmtDuration(ms) {
  if (!ms) return '—';
  if (ms < 1000) return `${ms}ms`;
  if (ms < 60000) return `${(ms/1000).toFixed(2)}s`;
  if (ms < 3600000) return `${(ms/60000).toFixed(1)}m`;
  return `${(ms/3600000).toFixed(1)}h`;
}

// ============== JSON pretty-printer ==============
function jsonHighlight(obj) {
  const json = JSON.stringify(obj, null, 2);
  return json
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, (m) => {
      let cls = 'json-num';
      if (/^"/.test(m)) cls = /:$/.test(m) ? 'json-key' : 'json-string';
      else if (/true|false/.test(m)) cls = 'json-bool';
      else if (/null/.test(m)) cls = 'json-null';
      return `<span class="${cls}">${m}</span>`;
    });
}

// ============== Sparkline ==============
function Sparkline({ data, color, height = 32, width = 96 }) {
  if (!data || data.length === 0) return null;
  const max = Math.max(...data, 1);
  const min = Math.min(...data, 0);
  const range = max - min || 1;
  const stepX = width / (data.length - 1 || 1);
  const points = data.map((v, i) => `${i * stepX},${height - ((v - min) / range) * (height - 4) - 2}`).join(' ');
  const areaPoints = `0,${height} ${points} ${width},${height}`;
  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`}>
      <polygon points={areaPoints} fill={color || 'var(--accent)'} opacity="0.12" />
      <polyline points={points} fill="none" stroke={color || 'var(--accent)'} strokeWidth="1.5" />
    </svg>
  );
}

// ============== Toast manager (simple bus) ==============
const ToastContext = React.createContext({ push: () => {} });
function useToast() { return React.useContext(ToastContext); }

function ToastProvider({ children }) {
  const [toasts, setToasts] = React.useState([]);
  const push = React.useCallback((t) => {
    const id = Math.random().toString(36).slice(2);
    setToasts(prev => [...prev, { id, ...t }]);
    setTimeout(() => setToasts(prev => prev.filter(x => x.id !== id)), t.duration || 3600);
  }, []);
  return (
    <ToastContext.Provider value={{ push }}>
      {children}
      <div className="toast-stack">
        {toasts.map(t => (
          <div key={t.id} className={`toast ${t.kind || ''}`}>
            <b>{t.title}</b>
            {t.body && <small>{t.body}</small>}
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

// ============== Modal ==============
function Modal({ open, onClose, title, sub, children, footer, width }) {
  React.useEffect(() => {
    if (!open) return;
    const onKey = (e) => e.key === 'Escape' && onClose?.();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);
  if (!open) return null;
  return (
    <>
      <div className="overlay" onClick={onClose} />
      <div className="modal" style={width ? { width } : null}>
        {title && (
          <div className="modal-head">
            <div className="modal-title">{title}</div>
            {sub && <div className="modal-sub">{sub}</div>}
          </div>
        )}
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-foot">{footer}</div>}
      </div>
    </>
  );
}

// ============== Drawer ==============
function Drawer({ open, onClose, title, children, actions }) {
  React.useEffect(() => {
    if (!open) return;
    const onKey = (e) => e.key === 'Escape' && onClose?.();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);
  if (!open) return null;
  return (
    <>
      <div className="overlay" onClick={onClose} />
      <div className="drawer">
        <div className="drawer-head">
          <div style={{display:'flex',alignItems:'center',gap:10}}>
            <strong style={{fontSize:13}}>{title}</strong>
          </div>
          <div style={{display:'flex',gap:6,alignItems:'center'}}>
            {actions}
            <button className="iconbtn" onClick={onClose}><I.X size={16}/></button>
          </div>
        </div>
        <div className="drawer-body">{children}</div>
      </div>
    </>
  );
}

// ============== Tooltip kbd ==============
function Kbd({ children }) {
  return <span style={{
    fontFamily: 'var(--font-mono)', fontSize: 10, padding: '1px 5px',
    border: '1px solid var(--border)', borderRadius: 3, background: 'var(--bg-elevated)',
    color: 'var(--text-secondary)'
  }}>{children}</span>;
}

Object.assign(window, {
  Icon, I, StatusBadge, Sparkline,
  fmtRelative, fmtTime, fmtDateTime, fmtDuration,
  jsonHighlight, ToastProvider, useToast, Modal, Drawer, Kbd,
});
