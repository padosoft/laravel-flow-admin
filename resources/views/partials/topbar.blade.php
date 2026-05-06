@php
    /**
     * Topbar partial — Macro 8 runtime controls.
     */
    $theme = $theme ?? config('flow-admin.theme_default', 'dark');
    $breadcrumbs = $breadcrumbs ?? [];
    $nextTheme = $theme === 'dark' ? 'light' : 'dark';
@endphp
<header class="topbar" data-testid="flow-admin-topbar">
    @include('flow-admin::partials.breadcrumbs', ['breadcrumbs' => $breadcrumbs])
    <div class="topbar-spacer"></div>

    <span class="live-pill" id="flow-live-pill" title="Auto-refresh status">
        <span class="pulse"></span>
        <span>Live</span>
    </span>

    <button type="button" class="iconbtn" id="flow-live-toggle" title="Pause/Resume auto refresh" aria-label="Pause or resume auto refresh">
        <x-flow-admin::icon name="pause" size="14" />
    </button>

    <button type="button" class="search-trigger" id="flow-cmdk-open" aria-label="Open command palette">
        <x-flow-admin::icon name="search" size="13" />
        <span>Search runs, flows, approvals...</span>
        <span class="kbd">Ctrl+K</span>
    </button>

    <button type="button" class="iconbtn" id="flow-notifications" title="Notifications" aria-label="Notifications">
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
