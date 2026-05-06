{{--
    Macro 3 subtask 3.1 placeholder. The full overview page (KPI tiles,
    throughput chart, recent runs, pending approvals, recent failures,
    sparkline) lands in Macro 5. This stub exists only to verify that:
      1. the design-token stylesheet (`.design-source/project/styles.css`
         port) is wired through `/_flow-admin/assets/admin.css`,
      2. the dark/light theme attribute on <html> resolves correctly,
      3. Blade view discovery is healthy (`flow-admin::pages.overview`).

    Class names below come from the ported `resources/css/admin.css`
    (`.page`, `.page-head`, `.page-title`, `.page-sub`). The Macro 3.2
    layout shell will wrap this content in `.app` + `.sidebar` + `.topbar`
    + `.content`; until then we render the page in isolation and the
    visual baseline test pins the no-shell skeleton.
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
    <main class="page" data-testid="flow-admin-overview-shell">
        <header class="page-head">
            <div>
                <h1 class="page-title">Flow Admin</h1>
                <p class="page-sub">Macro 3.1 skeleton — design tokens wired, full overview lands in Macro 5.</p>
            </div>
        </header>
    </main>
</body>
</html>
