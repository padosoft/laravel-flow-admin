@extends('flow-admin::layouts.app', [
    'route' => 'home',
    'pageTitle' => 'Overview',
    'breadcrumbs' => [
        ['label' => 'Overview'],
    ],
])

@section('content')
    <div class="page" data-testid="flow-admin-overview-page">
        <header class="page-head">
            <div>
                <h1 class="page-title">Flow Admin</h1>
                <p class="page-sub">Macro 3.2 layout shell — KPI tiles, throughput, recent runs land in Macro 5.</p>
            </div>
        </header>
    </div>
@endsection
