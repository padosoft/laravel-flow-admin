@extends('flow-admin::layouts.app')

@section('content')
    <div class="page">
        <div class="page-head">
            <div>
                <h1 class="page-title">Approvals</h1>
                <p class="page-sub">Manual decisions waiting for an operator.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body flush">
                @forelse ($items as $approval)
                    <div class="approval-card">
                        <div class="approval-info">
                            <b>{{ $approval->description }}</b>
                            <small><span class="mono">{{ $approval->runId }}</span> · {{ $approval->stepName }}</small>
                        </div>
                        <div class="approval-actions">
                            <a class="btn sm" href="{{ route('flow-admin.runs.show', ['id' => $approval->runId]) }}">Inspect</a>
                            <button class="btn sm danger" type="button">Reject</button>
                            <button class="btn sm primary" type="button">Approve</button>
                        </div>
                    </div>
                @empty
                    <div class="empty">No approvals found.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
