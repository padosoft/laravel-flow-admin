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
                            @if ($approval->canDecide())
                                <button class="btn sm danger" type="button"
                                    data-flow-action
                                    data-testid="approval-reject"
                                    data-action-url="{{ route('flow-admin.approvals.reject', ['tokenHash' => $approval->tokenHash]) }}"
                                    data-confirm="Reject this approval? The run will be failed."
                                    data-busy-label="Rejecting…">Reject</button>
                                <button class="btn sm primary" type="button"
                                    data-flow-action
                                    data-testid="approval-approve"
                                    data-action-url="{{ route('flow-admin.approvals.approve', ['tokenHash' => $approval->tokenHash]) }}"
                                    data-busy-label="Approving…">Approve</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty">No approvals found.</div>
                @endforelse
            </div>
        </div>
    </div>

    @include('flow-admin::partials.action-runner')
@endsection
