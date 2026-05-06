@extends('flow-admin::layouts.app')

@section('content')
    <div class="page">
        <div class="page-head">
            <div>
                <h1 class="page-title">Settings</h1>
                <p class="page-sub">Read-only configuration snapshot.</p>
            </div>
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-head"><h3 class="card-title">flow-admin.php</h3></div>
            <div class="card-body">
                <pre class="code-block">{{ json_encode($flowAdmin, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3 class="card-title">laravel-flow.php</h3></div>
            <div class="card-body">
                <pre class="code-block">{{ json_encode($laravelFlow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
@endsection
