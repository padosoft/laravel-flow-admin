@php
    /**
     * Sidebar partial — Macro 3.2.
     *
     * The badge counts (running runs, pending approvals, outbox depth)
     * land in Macro 5; for now `$counts` is an empty array and the
     * indicators stay hidden. The active-route highlighting reads from
     * `$route` passed by the layout (default `home`).
     *
     * Route URLs are stubbed to `route('flow-admin.overview')` for items
     * that don't exist yet; later macros wire them to real routes (Runs in
     * Macro 5, Approvals/Outbox/Definitions/Settings in Macro 7).
     */
    $route = $route ?? 'home';
    $counts = $counts ?? [];

    $primary = [
        ['key' => 'home',        'label' => 'Overview',    'icon' => 'home',        'badge' => null],
        ['key' => 'runs',        'label' => 'Runs',        'icon' => 'runs',        'badge' => $counts['running']   ?? null],
        ['key' => 'approvals',   'label' => 'Approvals',   'icon' => 'approvals',   'badge' => $counts['approvals'] ?? null],
        ['key' => 'outbox',      'label' => 'Outbox',      'icon' => 'outbox',      'badge' => $counts['outbox']    ?? null],
    ];

    $secondary = [
        ['key' => 'definitions', 'label' => 'Definitions', 'icon' => 'definitions'],
        ['key' => 'settings',    'label' => 'Settings',    'icon' => 'settings'],
    ];
@endphp
<aside class="sidebar" data-testid="flow-admin-sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">F</div>
        <div class="brand-text">
            <span>Flow</span>
            <small>laravel · v0.1</small>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-label">Operate</div>
            @foreach ($primary as $item)
                <a @class(['nav-item', 'active' => $route === $item['key']])
                   href="{{ route('flow-admin.overview') }}"
                   data-route-key="{{ $item['key'] }}">
                    <x-flow-admin::icon :name="$item['icon']" size="15" />
                    <span>{{ $item['label'] }}</span>
                    @if (! is_null($item['badge']) && $item['badge'] !== 0)
                        <span class="badge">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
        <div class="nav-section">
            <div class="nav-label">Configure</div>
            @foreach ($secondary as $item)
                <a @class(['nav-item', 'active' => $route === $item['key']])
                   href="{{ route('flow-admin.overview') }}"
                   data-route-key="{{ $item['key'] }}">
                    <x-flow-admin::icon :name="$item['icon']" size="15" />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="avatar">FA</div>
            <div class="user-info">
                <b>Flow Admin</b>
                <small>operator · example.test</small>
            </div>
        </div>
        <button type="button" class="iconbtn" title="Account" aria-label="Account menu">
            <x-flow-admin::icon name="chevron-down" size="14" />
        </button>
    </div>
</aside>
