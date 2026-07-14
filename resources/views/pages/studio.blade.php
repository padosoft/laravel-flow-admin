@extends('flow-admin::layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ route('flow-admin.assets.studio-css') }}">
@endpush

@section('content')
    <div class="page page-studio">
        <div class="page-head">
            <div>
                <h1 class="page-title">Studio</h1>
                <p class="page-sub">Compose, publish, and monitor flow graphs.</p>
            </div>
        </div>

        <div id="flow-studio-root" data-testid="flow-studio-root" style="width: 100%; height: 70vh;"></div>
    </div>
@endsection

@push('scripts')
    <script type="module" src="{{ route('flow-admin.assets.studio-js') }}"></script>
@endpush
