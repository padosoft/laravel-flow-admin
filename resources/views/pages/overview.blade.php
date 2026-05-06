@extends('flow-admin::layouts.app')

@section('content')
    <div class="page" data-testid="flow-admin-overview-page">
        <div class="page-head">
            <div>
                <h1 class="page-title">Overview</h1>
                <p class="page-sub">All flow activity across your application in the recent window.</p>
            </div>
        </div>

        <div class="kpi-grid">
            @foreach ($kpis as $tile)
                <article class="kpi">
                    <div class="kpi-label">{{ $tile->label }}</div>
                    <div class="kpi-value">{{ $tile->valueLabel }}</div>
                    <div class="kpi-delta {{ str_starts_with($tile->deltaLabel, '+') ? 'up' : (str_starts_with($tile->deltaLabel, '0') ? 'flat' : 'down') }}">{{ $tile->deltaLabel }}</div>
                </article>
            @endforeach
        </div>

        <div class="card" style="margin-bottom:16px;">
            <div class="card-head">
                <div>
                    <h3 class="card-title">Throughput</h3>
                    <p class="card-sub">Started runs by hour, stacked by success and failed.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="thru-chart">
                    @forelse ($throughput as $bar)
                        @php
                            $successHeight = (int) round(max(0, min(100, $bar->successHeightRatio * 100)));
                            $failedHeight = (int) round(max(0, min(100, $bar->failedHeightRatio * 100)));
                            $fullHeight = max(4, $successHeight + $failedHeight);
                        @endphp
                        <div class="thru-col">
                            <div class="thru-bar-wrap">
                                <div class="thru-bar" style="height: {{ $fullHeight }}%;">
                                    @if ($failedHeight > 0)
                                        <div class="thru-seg fail" style="height: {{ $failedHeight }}%;"></div>
                                    @endif
                                    @if ($successHeight > 0)
                                        <div class="thru-seg ok" style="height: {{ $successHeight }}%;"></div>
                                    @endif
                                </div>
                            </div>
                            <div class="thru-lbl">{{ $bar->at->format('H:i') }}</div>
                        </div>
                    @empty
                        <div class="empty" style="width:100%;">No throughput data available.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:16px;">
            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Recent runs</h3>
                    <a class="btn ghost sm" href="{{ route('flow-admin.runs.index') }}">View all</a>
                </div>
                <div class="card-body flush">
                    <div class="table-wrap">
                        <table class="tbl">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Flow</th>
                                    <th>Run ID</th>
                                    <th class="num">Duration</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentRuns as $run)
                                    <tr onclick="window.location='{{ route('flow-admin.runs.show', ['id' => '__ID__']) }}'.replace('__ID__', '{{ $run->id }}')">
                                        <td><x-flow-admin::status-badge :status="$run->status" :label="$run->statusLabel" /></td>
                                        <td><b>{{ $run->flowName }}</b> <span class="tertiary mono">{{ $run->flowVersion }}</span></td>
                                        <td><span class="mono">{{ $run->id }}</span></td>
                                        <td class="num muted">{{ $run->durationLabel }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4"><div class="empty">No runs found.</div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Pending approvals</h3>
                    <a class="btn ghost sm" href="{{ route('flow-admin.approvals.index') }}">View all</a>
                </div>
                <div class="card-body flush">
                    @forelse ($pendingApprovals as $approval)
                        <div class="approval-card">
                            <div class="approval-info">
                                <b>{{ $approval->description }}</b>
                                <small><span class="mono">{{ $approval->runId }}</span> · {{ $approval->stepName }}</small>
                            </div>
                            <x-flow-admin::status-badge :status="$approval->status" :label="$approval->statusLabel" />
                        </div>
                    @empty
                        <div class="empty">No pending approvals.</div>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Recent failures</h3>
                    <a class="btn ghost sm" href="{{ route('flow-admin.runs.index', ['status' => 'failed']) }}">View all</a>
                </div>
                <div class="card-body flush">
                    @forelse ($recentFailed as $run)
                        <div class="approval-card">
                            <div class="approval-info">
                                <b>{{ $run->flowName }}</b>
                                <small><span class="mono">{{ $run->id }}</span> · {{ $run->durationLabel }}</small>
                            </div>
                            <x-flow-admin::status-badge status="failed" label="Failed" />
                        </div>
                    @empty
                        <div class="empty">No recent failures.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
