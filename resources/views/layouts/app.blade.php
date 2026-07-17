@php
    /**
     * Layout shell with Macro 8 command palette + polling + toasts.
     */
    $themeDefault = config('flow-admin.theme_default', 'dark');
    $themeCookie = request()->cookie('flow_admin_theme');
    $theme = in_array($themeCookie, ['light', 'dark'], true) ? $themeCookie : $themeDefault;

    $route = $route ?? 'home';
    $counts = $counts ?? [];
    $breadcrumbs = $breadcrumbs ?? [];
    $pageTitle = $pageTitle ?? config('app.name', 'Flow Admin');
@endphp
<!doctype html>
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }} · Flow Admin</title>
    <link rel="stylesheet" href="{{ route('flow-admin.assets.css') }}">
    @stack('head')
</head>
<body data-testid="flow-admin-shell" data-theme="{{ $theme }}">
    <div class="app">
        @include('flow-admin::partials.sidebar', ['route' => $route, 'counts' => $counts])
        <main class="main">
            @include('flow-admin::partials.topbar', [
                'route' => $route,
                'theme' => $theme,
                'breadcrumbs' => $breadcrumbs,
            ])
            <section class="content">
                @yield('content')
            </section>
        </main>
    </div>

    <div class="overlay" id="flow-cmdk-overlay" style="display:none;"></div>
    <div class="palette" id="flow-cmdk" style="display:none;" role="dialog" aria-label="Command palette">
        <input class="palette-input" id="flow-cmdk-input" placeholder="Type to search runs and pages..." />
        <div class="palette-list" id="flow-cmdk-list"></div>
        <div class="palette-foot"><span><span class="kbd">Enter</span> open</span><span><span class="kbd">Esc</span> close</span></div>
    </div>

    <div class="toast-stack" id="flow-toast-stack"></div>

    <script>
    (function () {
      const pollingMs = {{ (int) config('flow-admin.polling_interval_ms', 4000) }};
      const liveUrl = @json(route('flow-admin.api.live'));
      const searchUrl = @json(route('flow-admin.api.search'));
      const pill = document.getElementById('flow-live-pill');
      const toggle = document.getElementById('flow-live-toggle');
      const openBtn = document.getElementById('flow-cmdk-open');
      const overlay = document.getElementById('flow-cmdk-overlay');
      const palette = document.getElementById('flow-cmdk');
      const input = document.getElementById('flow-cmdk-input');
      const list = document.getElementById('flow-cmdk-list');
      const toastStack = document.getElementById('flow-toast-stack');

      let polling = true;
      let timer = null;
      let results = [];
      let selected = 0;

      function toast(title, body, kind) {
        const node = document.createElement('div');
        node.className = 'toast' + (kind ? ' ' + kind : '');
        node.innerHTML = '<b>' + title + '</b>' + (body ? '<small>' + body + '</small>' : '');
        toastStack.appendChild(node);
        setTimeout(() => node.remove(), 3600);
      }

      // Exposed so per-page @stack('scripts') modules (e.g. the E-PR6 action
      // runner) reuse this single toast implementation instead of duplicating it.
      window.flowAdminToast = toast;

      async function tick() {
        if (!polling) return;
        try {
          const res = await fetch(liveUrl, { headers: { 'Accept': 'application/json' } });
          if (!res.ok) return;
          const data = await res.json();
          pill.title = 'Live: runs=' + data.totalRuns + ' failed=' + data.failedRuns + ' @ ' + data.ts;
        } catch (_) {}
      }

      function startPolling() {
        clearInterval(timer);
        timer = setInterval(tick, pollingMs);
      }

      function setPolling(state) {
        polling = state;
        toggle.innerHTML = state
          ? '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>'
          : '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>';
        if (state) {
          pill.classList.remove('outline');
          toast('Auto-refresh resumed', '', '');
        } else {
          toast('Auto-refresh paused', '', 'warn');
        }
      }

      function openPalette() {
        overlay.style.display = 'block';
        palette.style.display = 'block';
        input.focus();
        input.select();
      }

      function closePalette() {
        overlay.style.display = 'none';
        palette.style.display = 'none';
      }

      function renderResults() {
        list.innerHTML = '';
        if (results.length === 0) {
          list.innerHTML = '<div class="empty">No results</div>';
          return;
        }
        results.forEach((item, idx) => {
          const div = document.createElement('div');
          div.className = 'palette-item' + (idx === selected ? ' active' : '');
          div.innerHTML = '<span>' + item.label + '</span><span class="meta">' + item.meta + '</span>';
          div.onclick = () => { window.location = item.url; };
          list.appendChild(div);
        });
      }

      async function runSearch(q) {
        const url = new URL(searchUrl, window.location.origin);
        url.searchParams.set('q', q || '');
        const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        const data = await res.json();
        results = Array.isArray(data.items) ? data.items : [];
        selected = 0;
        renderResults();
      }

      toggle?.addEventListener('click', () => setPolling(!polling));
      openBtn?.addEventListener('click', openPalette);
      overlay?.addEventListener('click', closePalette);
      input?.addEventListener('input', (e) => { runSearch(e.target.value).catch(() => {}); });
      input?.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { closePalette(); return; }
        if (e.key === 'ArrowDown') { e.preventDefault(); selected = Math.min(selected + 1, Math.max(results.length - 1, 0)); renderResults(); }
        if (e.key === 'ArrowUp') { e.preventDefault(); selected = Math.max(selected - 1, 0); renderResults(); }
        if (e.key === 'Enter' && results[selected]) { window.location = results[selected].url; }
      });
      document.addEventListener('keydown', (e) => {
        const isCmdk = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k';
        if (isCmdk) {
          e.preventDefault();
          if (palette.style.display === 'none') { openPalette(); runSearch('').catch(() => {}); }
          else { closePalette(); }
        } else if (e.key === 'Escape' && palette.style.display !== 'none') {
          closePalette();
        }
      });

      startPolling();
      tick();
      toast('Flow Admin ready', 'Live refresh active', '');
    })();
    </script>
    @stack('scripts')
</body>
</html>
