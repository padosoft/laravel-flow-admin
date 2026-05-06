@php
    /**
     * Topbar partial — Macro 3.2.
     *
     * Theme toggle is the only interactive bit in 3.2: a POST form to
     * `flow-admin.theme.toggle` with the next theme. Live pill, search
     * trigger, notifications bell are placeholders here — the
     * auto-refresh + ⌘K palette + toast bus wiring lands in Macro 8.
     */
    $theme = $theme ?? config('flow-admin.theme_default', 'dark');
    $breadcrumbs = $breadcrumbs ?? [];
    $nextTheme = $theme === 'dark' ? 'light' : 'dark';
@endphp
<header class="topbar" data-testid="flow-admin-topbar">
    @include('flow-admin::partials.breadcrumbs', ['breadcrumbs' => $breadcrumbs])
    <div class="topbar-spacer"></div>

    <span class="live-pill" title="Auto-refresh status (live polling lands in Macro 8)">
        <span class="pulse"></span>
        <span>Live</span>
    </span>

    <button type="button" class="search-trigger" disabled aria-label="Open command palette (Macro 8)">
        <x-flow-admin::icon name="search" size="13" />
        <span>Search runs, flows, approvals…</span>
        <span class="kbd">⌘K</span>
    </button>

    <button type="button" class="iconbtn" title="Notifications" aria-label="Notifications">
        <x-flow-admin::icon name="bell" size="14" />
    </button>

    <form method="POST" action="{{ route('flow-admin.theme.toggle') }}" class="theme-toggle-form" data-testid="flow-admin-theme-toggle-form">
        @csrf
        <input type="hidden" name="theme" value="{{ $nextTheme }}">
        <button type="submit"
                class="iconbtn"
                data-testid="flow-admin-theme-toggle"
                data-current-theme="{{ $theme }}"
                title="Switch to {{ $nextTheme }} theme"
                aria-label="Switch to {{ $nextTheme }} theme">
            <x-flow-admin::icon :name="$theme === 'dark' ? 'sun' : 'moon'" size="14" />
        </button>
    </form>
</header>
