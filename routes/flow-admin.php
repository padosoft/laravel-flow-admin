<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padosoft\LaravelFlowAdmin\Http\Controllers\OverviewController;

Route::prefix(config('flow-admin.prefix', 'flow'))
    ->middleware(config('flow-admin.middleware', ['web', 'auth']))
    ->name('flow-admin.')
    ->group(function () {
        Route::get('/', [OverviewController::class, 'index'])->name('overview');
    });
