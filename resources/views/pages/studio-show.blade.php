@extends('flow-admin::layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ route('flow-admin.assets.studio-css') }}">
@endpush

@section('content')
    <div class="page page-studio">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $flowName }}</h1>
                <p class="page-sub">Read-only canvas — the published graph.</p>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="{{ route('flow-admin.studio.versions', ['name' => $flowName]) }}" class="btn" data-testid="studio-versions-link">
                    Versions
                </a>
                <a href="{{ route('flow-admin.studio.edit', ['name' => $flowName]) }}" class="btn" data-testid="studio-edit-link">
                    Edit
                </a>
            </div>
        </div>

        <div
            id="flow-studio-root"
            data-testid="flow-studio-root"
            data-flow-name="{{ $flowName }}"
            data-graph-url="{{ route('flow-admin.studio.graph', ['name' => $flowName]) }}"
            style="width: 100%; height: 70vh;"
        ></div>
    </div>
@endsection

@push('scripts')
    <script type="module" src="{{ route('flow-admin.assets.studio-js') }}"></script>
@endpush
