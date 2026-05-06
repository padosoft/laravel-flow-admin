@extends('flow-admin::layouts.app')

@section('content')
    <div class="page">
        <div class="page-head">
            <div>
                <h1 class="page-title">Runs</h1>
                <p class="page-sub">{{ $pagination['total'] }} matching runs.</p>
            </div>
        </div>

        <form method="GET" class="filter-bar">
            @php
                $statuses = ['all', 'running', 'paused', 'failed', 'success', 'compensated', 'pending'];
                $activeStatus = $filters['status'] ?? 'all';
                if ($activeStatus === null) { $activeStatus = 'all'; }
            @endphp

            @foreach ($statuses as $status)
                <button type="submit" name="status" value="{{ $status }}" class="chip {{ $activeStatus === $status ? 'active' : '' }}">
                    {{ ucfirst($status) }}
                    <span class="count">{{ $statusCounts[$status] ?? 0 }}</span>
                </button>
            @endforeach

            <div style="flex:1;"></div>

            <select class="select" name="flow" style="width:220px;">
                <option value="all">All flow definitions</option>
                @foreach ($definitions as $definition)
                    <option value="{{ $definition->name }}" @selected(($filters['flow'] ?? null) === $definition->name)>{{ $definition->name }}</option>
                @endforeach
            </select>

            <input class="input" name="q" placeholder="Search id, actor, correlation" style="width:240px;" value="{{ $filters['q'] ?? '' }}" />
            <button type="submit" class="btn">Apply</button>
        </form>

        <div class="card">
            <div class="card-body flush">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Flow</th>
                                <th>Run ID</th>
                                <th>Actor</th>
                                <th class="num">Steps</th>
                                <th class="num">Attempts</th>
                                <th class="num">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $run)
                                <tr onclick="window.location='{{ route('flow-admin.runs.show', ['id' => '__ID__']) }}'.replace('__ID__','{{ $run->id }}')">
                                    <td><x-flow-admin::status-badge :status="$run->status" :label="$run->statusLabel" /></td>
                                    <td><b>{{ $run->flowName }}</b> <span class="tertiary mono">{{ $run->flowVersion }}</span></td>
                                    <td><span class="mono">{{ $run->id }}</span></td>
                                    <td class="muted">{{ $run->actor }}</td>
                                    <td class="num mono">{{ $run->stepCount }}</td>
                                    <td class="num mono">{{ $run->attemptsTotal }}</td>
                                    <td class="num muted">{{ $run->durationLabel }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="empty">No runs match your filters.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="pagination">
                <span>Page <b>{{ $pagination['page'] }}</b> / <b>{{ max(1, $pagination['pages']) }}</b></span>
                <div class="pagination-controls">
                    @php
                        $prev = max(1, $pagination['page'] - 1);
                        $next = min(max(1, $pagination['pages']), $pagination['page'] + 1);
                        $query = request()->query();
                    @endphp
                    <a class="btn sm" href="{{ route('flow-admin.runs.index', array_merge($query, ['page' => $prev])) }}">Prev</a>
                    <a class="btn sm" href="{{ route('flow-admin.runs.index', array_merge($query, ['page' => $next])) }}">Next</a>
                </div>
            </div>
        </div>
    </div>
@endsection
