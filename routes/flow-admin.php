<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padosoft\LaravelFlowAdmin\Http\Controllers\ApiController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\ApprovalsController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\DefinitionsController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\OutboxController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\OverviewController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\RunDetailController;
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
        Route::get('/studio/{name}/graph', [StudioController::class, 'graph'])->name('studio.graph');
        Route::get('/studio/{name}', [StudioController::class, 'show'])->name('studio.show');
        Route::get('/runs', [RunsController::class, 'index'])->name('runs.index');
        Route::get('/runs/{id}', [RunDetailController::class, 'show'])->name('runs.show');
        Route::get('/approvals', [ApprovalsController::class, 'index'])->name('approvals.index');
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
