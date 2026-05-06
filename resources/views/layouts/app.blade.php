@php
    /**
     * Layout shell — Macro 3.2.
     *
     * Resolves the active theme in this order:
     *   1. `flow_admin_theme` cookie (light | dark) — last user choice
     *   2. `config('flow-admin.theme_default', 'dark')`
     *
     * The cookie is set by POST /flow/theme (`ThemeController@toggle`).
     * `data-theme` on <html> is what the design tokens hook into; the body
     * never contains the theme attribute (the design source uses <html>
     * exclusively). The full ⌘K palette + auto-refresh + toast bus land in
     * Macro 8; this layout exposes the placeholder slots only.
     */
    $themeDefault = config('flow-admin.theme_default', 'dark');
    $themeCookie = request()->cookie('flow_admin_theme');
    $theme = in_array($themeCookie, ['light', 'dark'], true) ? $themeCookie : $themeDefault;

    $route = $route ?? 'home';
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
</head>
<body data-testid="flow-admin-shell" data-theme="{{ $theme }}">
    <div class="app">
        @include('flow-admin::partials.sidebar', ['route' => $route])
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
</body>
</html>
