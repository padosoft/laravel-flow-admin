@extends('flow-admin::layouts.app')

@section('content')
    <div class="page">
        <div class="page-head">
            <div>
                <h1 class="page-title">Definitions</h1>
                <p class="page-sub">Registered flow definitions and success rate.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body flush">
                <div class="table-wrap">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Version</th>
                                <th class="num">Steps</th>
                                <th class="num">Runs</th>
                                <th class="num">Success rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($definitions as $definition)
                                <tr>
                                    <td><b>{{ $definition->name }}</b></td>
                                    <td class="mono">{{ $definition->version }}</td>
                                    <td class="num mono">{{ $definition->stepCount }}</td>
                                    <td class="num mono">{{ $definition->totalRuns }}</td>
                                    <td class="num mono">{{ $definition->successRateLabel }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="empty">No definitions available.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
