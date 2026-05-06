@extends('flow-admin::layouts.app')

@section('content')
    <div class="page" data-step-viz="{{ config('flow-admin.step_viz_default', 'timeline') }}">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $viewModel->summary->flowName }}</h1>
                <p class="page-sub"><span class="mono">{{ $viewModel->summary->id }}</span> · {{ $viewModel->summary->flowVersion }}</p>
            </div>
            <x-flow-admin::status-badge :status="$viewModel->summary->status" :label="$viewModel->summary->statusLabel" />
        </div>

        <div class="run-grid">
            <div class="card">
                <div class="card-head">
                    <h3 class="card-title">Steps</h3>
                </div>
                <div class="card-body flush">
                    <div class="step-list">
                        @forelse ($viewModel->steps as $step)
                            <div class="step {{ $step->status }} {{ $loop->first ? 'selected' : '' }}">
                                <div class="step-rail">
                                    <span class="node"></span>
                                    <span class="line"></span>
                                </div>
                                <div class="step-body">
                                    <div class="step-row1">
                                        <span class="step-name">{{ $step->name }}</span>
                                        <x-flow-admin::status-badge :status="$step->status" :label="$step->statusLabel" />
                                    </div>
                                    <div class="step-row2">
                                        <span class="mono">{{ $step->durationLabel }}</span>
                                        <span>Attempts: {{ $step->attempts }}</span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="empty">No steps available.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="detail-pane">
                <div class="detail-head">
                    <div>
                        <div style="font-size:13px;font-weight:600;">Run details</div>
                    </div>
                </div>
                <div class="detail-content">
                    <dl class="kv">
                        <dt>Run ID</dt><dd>{{ $viewModel->summary->id }}</dd>
                        <dt>Correlation</dt><dd>{{ $viewModel->summary->correlationId !== '' ? $viewModel->summary->correlationId : '—' }}</dd>
                        <dt>Actor</dt><dd>{{ $viewModel->summary->actor }}</dd>
                        <dt>Duration</dt><dd>{{ $viewModel->summary->durationLabel }}</dd>
                    </dl>

                    <h4 style="margin-top:18px;">Input</h4>
                    <pre class="code-block">{{ $viewModel->inputJson }}</pre>

                    <h4 style="margin-top:18px;">Output</h4>
                    <pre class="code-block">{{ $viewModel->outputJson }}</pre>
                </div>
            </div>
        </div>
    </div>
@endsection
