{{--
    Macro 3 subtask 3.1 placeholder. The full overview page (KPI tiles,
    throughput chart, recent runs, pending approvals, recent failures,
    sparkline) lands in Macro 5. This stub exists only to verify that:
      1. the design-token stylesheet (`.design-source/project/styles.css`
         port) is wired through `/_flow-admin/assets/admin.css`,
      2. the dark/light theme attribute on <html> resolves correctly,
      3. Blade view discovery is healthy (`flow-admin::pages.overview`).
--}}
<!doctype html>
<html lang="en" data-theme="{{ config('flow-admin.theme_default', 'dark') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flow Admin — Overview</title>
    <link rel="stylesheet" href="{{ route('flow-admin.assets.css') }}">
</head>
<body>
    <main class="app-shell" data-testid="flow-admin-overview-shell">
        <header class="app-shell__header">
            <h1 class="app-shell__title">Flow Admin</h1>
            <p class="app-shell__subtitle">Macro 3.1 skeleton — design tokens wired, full overview lands in Macro 5.</p>
        </header>
    </main>
</body>
</html>
