@extends('flow-admin::layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ route('flow-admin.assets.studio-css') }}">
@endpush

@section('content')
    <div class="page page-studio">
        <div class="page-head">
            <div>
                <h1 class="page-title">Versions · {{ $flowName }}</h1>
                <p class="page-sub">Publish a draft as an immutable runnable version, and compare any two versions on the canvas.</p>
            </div>
        </div>

        <div
            id="flow-studio-root"
            data-testid="flow-studio-root"
            data-mode="versions"
            data-flow-name="{{ $flowName }}"
            data-version-list-url="{{ route('flow-admin.studio.version-list', ['name' => $flowName]) }}"
            data-diff-url="{{ route('flow-admin.studio.diff', ['name' => $flowName]) }}"
            data-publish-url="{{ route('flow-admin.studio.publish', ['name' => $flowName]) }}"
            data-catalog-url="{{ route('flow-admin.studio.catalog') }}"
            data-edit-url="{{ route('flow-admin.studio.edit', ['name' => $flowName]) }}"
            style="width: 100%; height: 72vh;"
        ></div>
    </div>
@endsection

@push('scripts')
    <script type="module" src="{{ route('flow-admin.assets.studio-js') }}"></script>
@endpush
