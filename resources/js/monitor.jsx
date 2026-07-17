import { StrictMode, useCallback, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';

// The nine REAL core NodeState values (Padosoft\LaravelFlow\Executor\State\
// NodeState) — the single source of truth for a node's persisted status.
// "cache-hit" is deliberately NOT a state here: it's metadata ON a succeeded
// node, rendered as a separate ⚡ badge.
const STATE_COLORS = {
  pending: '#6b7280',
  running: '#3b82f6',
  paused: '#f59e0b',
  succeeded: '#10b981',
  failed: '#ef4444',
  skipped: '#8a8a93',
  blocked: '#8b5cf6',
  invalid_input: '#f97316',
  dead_letter: '#7f1d1d',
  // `compensated` is not a NodeState (it's a saga-rollback outcome the admin's
  // demo fixtures use); recognized here so it renders intentionally rather
  // than falling through to the gray "pending" default.
  compensated: '#a855f7',
};

const STATE_LABELS = {
  pending: 'Pending',
  running: 'Running',
  paused: 'Paused',
  succeeded: 'Succeeded',
  failed: 'Failed',
  skipped: 'Skipped',
  blocked: 'Blocked',
  invalid_input: 'Invalid input',
  dead_letter: 'Dead letter',
  compensated: 'Compensated',
};

function recomputeProgress(nodes, base = {}) {
  const total = nodes.length;
  const completed = nodes.filter((n) => n.state === 'succeeded').length;
  const failed = nodes.filter((n) => n.state === 'failed').length;

  // Settled = completed OR failed, matching core's GraphRunProgressUpdated.
  return { ...base, total, completed, failed, pct: total > 0 ? Math.round(((completed + failed) / total) * 100) : 0 };
}

// A `.node.transitioned` broadcast carries {run_id, node_id, node_type,
// state, sequence, occurred_at} — no cache_hit, so a live transition never
// changes the cache-hit badge (that only arrives via the polled state).
function applyNodeEvent(data, event) {
  const nodes = data.nodes.map((node) => (node.node_id === event.node_id ? { ...node, state: event.state } : node));

  return { ...data, nodes, progress: recomputeProgress(nodes, data.progress) };
}

// A `.run.progress` broadcast carries the authoritative aggregate.
function applyProgressEvent(data, event) {
  return {
    ...data,
    status: event.status ?? data.status,
    progress: {
      total: event.nodes_total ?? data.progress.total,
      completed: event.nodes_completed ?? data.progress.completed,
      failed: event.nodes_failed ?? data.progress.failed,
      pct: event.progress_pct ?? data.progress.pct,
    },
  };
}

function RunMonitor({ monitorStateUrl, broadcasting, channel }) {
  const [state, setState] = useState({ status: 'loading', data: null });

  const fetchState = useCallback(async () => {
    const response = await fetch(monitorStateUrl, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
      throw new Error('Unexpected response');
    }

    return response.json();
  }, [monitorStateUrl]);

  // Always fetch the current state once so the monitor renders immediately,
  // whichever update mode follows.
  useEffect(() => {
    let cancelled = false;

    fetchState()
      .then((data) => {
        if (!cancelled) setState({ status: 'ready', data });
      })
      .catch(() => {
        if (!cancelled) setState({ status: 'error', data: null });
      });

    return () => {
      cancelled = true;
    };
  }, [fetchState]);

  // Private-channel subscription can fail (the host app must authorize
  // `{prefix}.run.{id}` in routes/channels.php — core ships no auth callback).
  // On failure we drop to polling instead of showing stale state forever.
  const [liveFailed, setLiveFailed] = useState(false);
  const live = broadcasting === 'on' && typeof window !== 'undefined' && Boolean(window.Echo) && !liveFailed;

  // Live mode: subscribe to the run's private channel. Without an Echo client
  // (or with broadcasting disabled) this effect is inert and the polling
  // effect below drives updates instead.
  useEffect(() => {
    if (!live) {
      return undefined;
    }

    const subscription = window.Echo.private(channel);
    subscription.listen('.node.transitioned', (event) => {
      setState((current) => (current.data ? { ...current, data: applyNodeEvent(current.data, event) } : current));
    });
    subscription.listen('.run.progress', (event) => {
      setState((current) => (current.data ? { ...current, data: applyProgressEvent(current.data, event) } : current));
    });
    // Echo exposes channel auth/subscription errors via `.error()`; fall back
    // to polling when the host hasn't authorized this private channel.
    if (typeof subscription.error === 'function') {
      subscription.error(() => setLiveFailed(true));
    }

    return () => {
      try {
        window.Echo.leave(channel);
      } catch {
        // Echo may be a minimal shim (e.g. tests) without leave(); ignore.
      }
    };
  }, [live, channel]);

  // Polling fallback: re-fetch the state on an interval when not live.
  useEffect(() => {
    if (live) {
      return undefined;
    }

    let cancelled = false;
    const timer = setInterval(() => {
      fetchState()
        .then((data) => {
          if (!cancelled) setState({ status: 'ready', data });
        })
        .catch(() => {});
    }, 2500);

    return () => {
      cancelled = true;
      clearInterval(timer);
    };
  }, [live, fetchState]);

  if (state.status === 'loading') {
    return <div className="empty" data-testid="monitor-loading">Loading run state…</div>;
  }

  if (state.status === 'error') {
    return <div className="empty" data-testid="monitor-error">Could not load the run state. Try reloading the page.</div>;
  }

  const { data } = state;

  return (
    <div data-testid="flow-monitor" data-mode={live ? 'live' : 'polling'} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <div
        data-testid="monitor-progress"
        style={{ display: 'flex', alignItems: 'center', gap: 16, padding: '10px 14px', border: '1px solid var(--border, #333)', borderRadius: 8 }}
      >
        <span data-testid="monitor-status" style={{ fontWeight: 600 }}>{data.status}</span>
        <span data-testid="monitor-progress-count">{data.progress.completed}/{data.progress.total} nodes</span>
        <span data-testid="monitor-progress-pct" style={{ color: 'var(--text-secondary, #999)' }}>{data.progress.pct}%</span>
        {data.progress.failed > 0 ? (
          <span data-testid="monitor-failed-count" style={{ color: STATE_COLORS.failed }}>{data.progress.failed} failed</span>
        ) : null}
        <span data-testid="monitor-mode" style={{ marginLeft: 'auto', fontSize: 12, color: 'var(--text-tertiary, #888)' }}>
          {live ? 'live' : 'polling'}
        </span>
      </div>

      <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 6 }}>
        {data.nodes.map((node) => {
          const color = STATE_COLORS[node.state] ?? STATE_COLORS.pending;
          const pulsing = node.state === 'running';

          return (
            <li
              key={node.node_id}
              data-testid={`monitor-node-${node.node_id}`}
              style={{
                display: 'flex', alignItems: 'center', gap: 12,
                padding: '8px 12px', border: '1px solid var(--border, #333)', borderRadius: 8,
                borderLeft: `4px solid ${color}`,
              }}
            >
              <span style={{ fontWeight: 500 }}>{node.node_id}</span>
              <span
                data-testid={`monitor-node-state-${node.node_id}`}
                style={{
                  marginLeft: 'auto', fontSize: 12, padding: '2px 8px', borderRadius: 999,
                  color, border: `1px solid ${color}`, ...(pulsing ? { animation: 'pulse 1.2s ease-in-out infinite' } : {}),
                }}
              >
                {STATE_LABELS[node.state] ?? node.state}
              </span>
              {node.cache_hit && node.state === 'succeeded' ? (
                <span data-testid={`monitor-cache-badge-${node.node_id}`} title="Served from cache" style={{ fontSize: 12, color: STATE_COLORS.succeeded }}>
                  ⚡ cache
                </span>
              ) : null}
            </li>
          );
        })}
      </ul>
    </div>
  );
}

const mountPoint = document.getElementById('flow-monitor-root');

if (mountPoint) {
  const { monitorStateUrl, broadcasting, channel } = mountPoint.dataset;

  createRoot(mountPoint).render(
    <StrictMode>
      <RunMonitor monitorStateUrl={monitorStateUrl} broadcasting={broadcasting} channel={channel} />
    </StrictMode>,
  );
}
