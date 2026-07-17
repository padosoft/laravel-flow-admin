@extends('flow-admin::layouts.app')

@section('content')
    <div class="page" data-testid="flow-advisor-page">
        <div class="page-head">
            <div>
                <h1 class="page-title">Advisor</h1>
                <p class="page-sub">Deterministic suggestions from your flows' run history — no AI model, no guessing.</p>
            </div>
            @if ($advisorAvailable)
                <div class="page-actions">
                    <button
                        type="button"
                        class="btn primary"
                        data-testid="advisor-scan-button"
                        data-scan-url="{{ route('flow-admin.advisor.scan') }}"
                    >
                        Scan flows for suggestions
                    </button>
                </div>
            @endif
        </div>

        @unless ($advisorAvailable)
            <div class="card">
                <div class="card-body">
                    <div class="empty" data-testid="advisor-unavailable">
                        The Flow Advisor requires the optional <span class="mono">padosoft/laravel-flow-ai</span> package.
                        Install it to scan your flows for reliability and performance suggestions.
                    </div>
                </div>
            </div>
        @else
            {{-- Scanning writes draft versions the operator reviews — this is an
                 explicit action, not an auto-load, so the initial state invites it. --}}
            <div class="card">
                <div class="card-body flush">
                    <div class="advisor-status" data-testid="advisor-status" role="status" aria-live="polite" hidden></div>

                    <div class="empty" data-testid="advisor-initial">
                        Run a scan to surface suggestions. Each suggestion is saved as a <b>draft version</b>
                        of its flow (never published) that you can review and publish from the flow's Versions page.
                    </div>

                    <div class="advisor-loading empty" data-testid="advisor-loading" hidden>Scanning run history…</div>

                    <div class="empty" data-testid="advisor-empty" hidden>
                        No suggestions — the advisor found nothing notable in recent run history.
                    </div>

                    <div class="field-error" data-testid="advisor-error" style="padding: 10px 14px;" hidden></div>

                    <ul class="advisor-list" data-testid="advisor-list" style="list-style: none; margin: 0; padding: 0;" hidden></ul>
                </div>
            </div>
        @endunless
    </div>
@endsection

@push('scripts')
    <script type="module">
        const button = document.querySelector('[data-testid="advisor-scan-button"]');
        if (button) {
            const root = document.querySelector('[data-testid="flow-advisor-page"]');
            const show = (sel, on) => {
                const el = root.querySelector(`[data-testid="${sel}"]`);
                if (el) el.hidden = !on;
            };
            const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            // Split the versions-route template around the name segment in PHP
            // (build time) so the URL is assembled by concatenation, never by a
            // runtime String.replace that could collide with a flow literally
            // named after the placeholder token.
            @php
                [$versionsPrefix, $versionsSuffix] = explode(
                    'FLOW_NAME_PLACEHOLDER',
                    route('flow-admin.studio.versions', ['name' => 'FLOW_NAME_PLACEHOLDER']),
                    2,
                );
            @endphp
            const versionsUrlFor = (flow) => @json($versionsPrefix) + encodeURIComponent(flow) + @json($versionsSuffix);

            // Pure DOM construction (no innerHTML): every value is
            // user/analyzer-derived, so building nodes with textContent keeps
            // the render XSS-safe by construction.
            const el = (tag, className, text) => {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            };

            const renderRationale = (rationale) => {
                const entries = Object.entries(rationale ?? {});
                if (entries.length === 0) return null;
                const wrap = el('div', 'advisor-rationale');
                wrap.dataset.testid = 'advisor-rationale';
                for (const [key, value] of entries) {
                    const row = el('div', 'advisor-rationale-row');
                    row.append(el('span', 'mono', key));
                    row.append(el('span', null, typeof value === 'object' ? JSON.stringify(value) : String(value)));
                    wrap.append(row);
                }
                return wrap;
            };

            const renderCard = (s, index) => {
                const li = el('li', 'advisor-card');
                // Include the index: FlowAdvisor can return several suggestions
                // for the SAME flow (one per analyzer finding), so keying the
                // testid on flow name alone would collide.
                li.dataset.testid = `advisor-card-${s.flow}-${index}`;

                const head = el('div', 'advisor-card-head');
                head.append(el('b', null, s.flow));
                head.append(el('span', 'badge', s.finding.type));
                head.append(el('span', 'advisor-draft mono', `draft v${s.draft_version}`));
                li.append(head);

                li.append(el('p', 'advisor-summary', s.finding.summary));

                const rationale = renderRationale(s.finding.rationale);
                if (rationale) li.append(rationale);

                const actions = el('div', 'advisor-card-actions');
                const link = el('a', 'btn sm', `Review draft v${s.draft_version}`);
                link.href = versionsUrlFor(s.flow);
                link.dataset.testid = `advisor-view-draft-${s.flow}-${index}`;
                actions.append(link);
                li.append(actions);

                return li;
            };

            const setStatus = (message) => {
                const el = root.querySelector('[data-testid="advisor-status"]');
                if (!el) return;
                el.textContent = message ?? '';
                el.hidden = !message;
            };

            button.addEventListener('click', async () => {
                // Explicit confirmation (rule-admin-ajax-pattern): a scan runs
                // across every flow and can WRITE a new draft version per flow
                // with a finding, so it isn't a free read — make the operator
                // opt in rather than firing on a single click.
                if (!window.confirm('Scan all flows for suggestions? Each flow with a suggestion gets a new draft version (never published) you can review and publish.')) {
                    return;
                }

                button.disabled = true;
                setStatus('');
                show('advisor-initial', false);
                show('advisor-empty', false);
                show('advisor-error', false);
                show('advisor-list', false);
                show('advisor-loading', true);

                try {
                    const response = await fetch(button.dataset.scanUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    });
                    const body = await response.json().catch(() => ({}));
                    show('advisor-loading', false);

                    if (!response.ok || !body.success) {
                        const error = root.querySelector('[data-testid="advisor-error"]');
                        if (error) error.textContent = body.message ?? 'The advisor scan could not complete. Try again.';
                        show('advisor-error', true);
                        return;
                    }

                    setStatus(body.message ?? '');
                    const suggestions = Array.isArray(body.suggestions) ? body.suggestions : [];
                    if (suggestions.length === 0) {
                        show('advisor-empty', true);
                        return;
                    }

                    const list = root.querySelector('[data-testid="advisor-list"]');
                    list.replaceChildren(...suggestions.map(renderCard));
                    show('advisor-list', true);
                } catch {
                    show('advisor-loading', false);
                    const error = root.querySelector('[data-testid="advisor-error"]');
                    if (error) error.textContent = 'Network error while scanning. Try again.';
                    show('advisor-error', true);
                } finally {
                    button.disabled = false;
                }
            });
        }
    </script>
@endpush
