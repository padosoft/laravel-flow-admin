<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padosoft\LaravelFlowAdmin\Http\Controllers\OverviewController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\ThemeController;

Route::prefix(config('flow-admin.prefix', 'flow'))
    ->middleware(config('flow-admin.middleware', ['web', 'auth']))
    ->name('flow-admin.')
    ->group(function () {
        Route::get('/', [OverviewController::class, 'index'])->name('overview');

        // Theme persistence — sets the `flow_admin_theme` cookie and
        // redirects back to the referrer. POST-only so the cookie write
        // is not triggered by a stray GET / link prefetcher / search
        // crawler. Lives inside the admin route group on purpose: an
        // anonymous browser must NOT be able to seed cookies on the
        // operator's domain.
        Route::post('theme', [ThemeController::class, 'toggle'])->name('theme.toggle');
    });
