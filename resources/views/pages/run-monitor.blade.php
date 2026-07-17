@extends('flow-admin::layouts.app')

@section('content')
    <div class="page page-run-monitor">
        <div class="page-head">
            <div>
                <h1 class="page-title">Live monitor</h1>
                <p class="page-sub">Node states update live over the run's broadcast channel, or by polling when broadcasting is off.</p>
            </div>
            <a href="{{ route('flow-admin.runs.show', ['id' => $runId]) }}" class="btn" data-testid="run-detail-link">
                Run detail
            </a>
        </div>

        <div
            id="flow-monitor-root"
            data-testid="flow-monitor-root"
            data-run-id="{{ $runId }}"
            data-monitor-state-url="{{ route('flow-admin.runs.monitor-state', ['id' => $runId]) }}"
            data-broadcasting="{{ $broadcasting ? 'on' : 'off' }}"
            data-channel="{{ $channel }}"
        ></div>
    </div>
@endsection

@push('scripts')
    <script type="module" src="{{ route('flow-admin.assets.monitor-js') }}"></script>
@endpush
