@extends('flow-admin::layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ route('flow-admin.assets.studio-css') }}">
@endpush

@section('content')
    <div class="page page-studio">
        <div class="page-head">
            <div>
                <h1 class="page-title">Edit {{ $flowName }}</h1>
                <p class="page-sub">Drag nodes from the palette, connect ports, edit config, then save as a new draft version.</p>
            </div>
        </div>

        <div
            id="flow-studio-root"
            data-testid="flow-studio-root"
            data-mode="edit"
            data-flow-name="{{ $flowName }}"
            data-edit-graph-url="{{ route('flow-admin.studio.edit-graph', ['name' => $flowName]) }}"
            data-catalog-url="{{ route('flow-admin.studio.catalog') }}"
            data-draft-url="{{ route('flow-admin.studio.draft', ['name' => $flowName]) }}"
            data-dry-run-url="{{ route('flow-admin.studio.dry-run', ['name' => $flowName]) }}"
            style="width: 100%; height: 70vh;"
        ></div>
    </div>
@endsection

@push('scripts')
    <script type="module" src="{{ route('flow-admin.assets.studio-js') }}"></script>
@endpush
