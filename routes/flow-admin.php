<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padosoft\LaravelFlowAdmin\Http\Controllers\AdvisorController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\ApiController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\ApprovalsController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\DefinitionsController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\OutboxController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\OverviewController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\RunDetailController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\RunMonitorController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\RunsController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\SettingsController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\StudioController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\ThemeController;

Route::prefix(config('flow-admin.prefix', 'flow'))
    ->middleware(config('flow-admin.middleware', ['web', 'auth']))
    ->name('flow-admin.')
    ->group(function () {
        Route::get('/', [OverviewController::class, 'index'])->name('overview');
        Route::get('/studio', [StudioController::class, 'index'])->name('studio');
        // Literal /studio/catalog and every /studio/{name}/... suffix route
        // must be registered before the catch-all /studio/{name} below, or
        // that route swallows them (e.g. "catalog" resolved as a flow name).
        Route::get('/studio/catalog', [StudioController::class, 'catalog'])->name('studio.catalog');
        Route::get('/studio/{name}/graph', [StudioController::class, 'graph'])->name('studio.graph');
        Route::get('/studio/{name}/edit', [StudioController::class, 'edit'])->name('studio.edit');
        Route::get('/studio/{name}/edit-graph', [StudioController::class, 'editGraph'])->name('studio.edit-graph');
        Route::post('/studio/{name}/draft', [StudioController::class, 'storeDraft'])->name('studio.draft');
        Route::get('/studio/{name}/versions', [StudioController::class, 'versions'])->name('studio.versions');
        Route::get('/studio/{name}/version-list', [StudioController::class, 'versionList'])->name('studio.version-list');
        Route::get('/studio/{name}/diff', [StudioController::class, 'diff'])->name('studio.diff');
        Route::post('/studio/{name}/publish', [StudioController::class, 'publish'])->name('studio.publish');
        Route::post('/studio/{name}/dry-run', [StudioController::class, 'dryRun'])->name('studio.dry-run');
        // Registered UNCONDITIONALLY even though padosoft/laravel-flow-ai is
        // optional: gating the route on class_exists() would 404 at the router
        // (before auth) when the pack is absent vs 403 when present, leaking
        // package-presence to unauthenticated callers — the exact oracle the
        // controller avoids by doing its class_exists() 404 INSIDE the
        // edit_definition gate (so unauthorized callers always get a uniform
        // 403, and only an AUTHORIZED caller sees the 404 when the pack is
        // missing). The class_exists-gated AI features are the UI affordance
        // (data-ai-build-url) and the container binding, not the route itself.
        //
        // Rate-limited on TOP of the edit_definition gate: unlike the other
        // Studio mutations (free, local DB writes), each ai-build call spends a
        // billable third-party LLM request, so an authorized-but-careless (or
        // compromised) operator could otherwise run up cost. 12/min per user is
        // generous for interactive authoring yet caps runaway/scripted spend.
        // The 3rd `throttle` arg is a KEY PREFIX (see ThrottleRequests::handle
        // — `$prefix.$signature`): without it the bare `throttle:12,1` bucket
        // is keyed only by domain+IP/user and would be SHARED with any other
        // bare-throttled route in the host app, so unrelated traffic could
        // starve (or be starved by) this AI cost budget. A dedicated prefix
        // isolates it.
        Route::post('/studio/{name}/ai-build', [StudioController::class, 'aiBuild'])
            ->middleware('throttle:12,1,flow-admin-ai-build')
            ->name('studio.ai-build');
        Route::get('/studio/{name}', [StudioController::class, 'show'])->name('studio.show');
        Route::get('/runs', [RunsController::class, 'index'])->name('runs.index');
        Route::get('/runs/{id}/monitor', [RunMonitorController::class, 'show'])->name('runs.monitor');
        Route::get('/runs/{id}/monitor-state', [RunMonitorController::class, 'state'])->name('runs.monitor-state');
        // Mutation endpoints (E-PR6) — each wraps a core FlowEngine seam in
        // Support\Authorize::action (deny-by-default) and returns the uniform
        // {success,message,data} JSON contract. Registered before the /runs/{id}
        // catch-all GET so the /cancel and /replay suffixes are not swallowed.
        Route::post('/runs/{id}/cancel', [RunDetailController::class, 'cancel'])->name('runs.cancel');
        Route::post('/runs/{id}/replay', [RunDetailController::class, 'replay'])->name('runs.replay');
        Route::get('/runs/{id}', [RunDetailController::class, 'show'])->name('runs.show');
        Route::get('/approvals', [ApprovalsController::class, 'index'])->name('approvals.index');
        // {tokenHash} is the SHA-256 token hash — exactly 64 lowercase/uppercase
        // hex chars. Constrain to that so an over-long or malformed segment can
        // never reach the controller/authorizer/logs, and to enforce the
        // documented contract. Not a secret: the plaintext token is never
        // recoverable from it (see ApprovalSummary).
        Route::post('/approvals/{tokenHash}/approve', [ApprovalsController::class, 'approve'])
            ->where('tokenHash', '[A-Fa-f0-9]{64}')->name('approvals.approve');
        Route::post('/approvals/{tokenHash}/reject', [ApprovalsController::class, 'reject'])
            ->where('tokenHash', '[A-Fa-f0-9]{64}')->name('approvals.reject');
        // {id} bounded to 1–18 digits: an outbox id is an integer PK, and
        // capping the length keeps `(int)` casts safely below PHP_INT_MAX so a
        // pathological long digit string can't overflow into a different row.
        Route::post('/outbox/{id}/redeliver', [OutboxController::class, 'redeliver'])
            ->where('id', '[0-9]{1,18}')->name('outbox.redeliver');
        Route::get('/advisor', [AdvisorController::class, 'index'])->name('advisor.index');
        // Registered unconditionally (no package-presence oracle — the
        // controller does its class_exists() 404 INSIDE the edit_definition
        // gate, same posture as studio.ai-build). Throttled: a scan reads run
        // history and can WRITE several draft versions via FlowAdvisor.
        Route::post('/advisor/scan', [AdvisorController::class, 'scan'])
            ->middleware('throttle:6,1,flow-admin-advisor-scan')
            ->name('advisor.scan');
        Route::get('/outbox', [OutboxController::class, 'index'])->name('outbox.index');
        Route::get('/definitions', [DefinitionsController::class, 'index'])->name('definitions.index');
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::get('/api/search', [ApiController::class, 'search'])->name('api.search');
        Route::get('/api/live', [ApiController::class, 'live'])->name('api.live');

        // Theme persistence — sets the `flow_admin_theme` cookie and
        // redirects back to the referrer. POST-only so the cookie write
        // is not triggered by a stray GET / link prefetcher / search
        // crawler. Lives inside the admin route group on purpose: an
        // anonymous browser must NOT be able to seed cookies on the
        // operator's domain.
        Route::post('theme', [ThemeController::class, 'toggle'])->name('theme.toggle');
    });
