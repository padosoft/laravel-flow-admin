@extends('flow-admin::layouts.app')

@section('content')
    <div class="page">
        <div class="page-head">
            <div>
                <h1 class="page-title">Webhook outbox</h1>
                <p class="page-sub">Delivery queue status and retry eligibility.</p>
            </div>
        </div>

        <div class="filter-bar">
            <a href="{{ route('flow-admin.outbox.index', ['status' => 'all']) }}" class="chip {{ ($filters['status'] ?? null) === null ? 'active' : '' }}">All</a>
            <a href="{{ route('flow-admin.outbox.index', ['status' => 'pending']) }}" class="chip {{ ($filters['status'] ?? null) === 'pending' ? 'active' : '' }}">Pending <span class="count">{{ $pendingCount }}</span></a>
            <a href="{{ route('flow-admin.outbox.index', ['status' => 'delivered']) }}" class="chip {{ ($filters['status'] ?? null) === 'delivered' ? 'active' : '' }}">Delivered <span class="count">{{ $deliveredCount }}</span></a>
            <a href="{{ route('flow-admin.outbox.index', ['status' => 'failed']) }}" class="chip {{ ($filters['status'] ?? null) === 'failed' ? 'active' : '' }}">Failed <span class="count">{{ $failedCount }}</span></a>
        </div>

        <div class="card">
            <div class="card-body flush">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>ID</th>
                                <th>Event</th>
                                <th>Destination</th>
                                <th class="num">Attempts</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $row)
                                <tr>
                                    <td><x-flow-admin::status-badge :status="$row->status" :label="$row->statusLabel" /></td>
                                    <td class="mono">{{ $row->id }}</td>
                                    <td class="mono">{{ $row->eventType }}</td>
                                    <td class="muted">{{ $row->destination }}</td>
                                    <td class="num mono">{{ $row->attempts }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="empty">No outbox items found.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
