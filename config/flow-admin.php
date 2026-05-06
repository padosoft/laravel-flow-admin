<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    | The URI prefix for all laravel-flow-admin routes.
    */
    'prefix' => env('FLOW_ADMIN_PREFIX', 'flow'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    | Applied to every admin route. Require auth in production by default.
    | Override with FLOW_ADMIN_MIDDLEWARE="web,auth,verified" or similar.
    | An empty value disables the middleware stack (useful for E2E smoke tests).
    */
    'middleware' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FLOW_ADMIN_MIDDLEWARE', 'web,auth')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Read model adapter
    |--------------------------------------------------------------------------
    | 'eloquent' — default, reads from the flow_* tables via padosoft/laravel-flow.
    | 'array'    — deterministic seed-42 fixtures; used for Playwright E2E tests.
    */
    'adapter' => env('FLOW_ADMIN_ADAPTER', 'eloquent'),

    /*
    |--------------------------------------------------------------------------
    | Auto-refresh polling interval (milliseconds)
    |--------------------------------------------------------------------------
    */
    'polling_interval_ms' => (int) env('FLOW_ADMIN_POLLING_MS', 4000),

    /*
    |--------------------------------------------------------------------------
    | Default theme
    |--------------------------------------------------------------------------
    | 'dark' or 'light'. Persisted per-user in cookie flow_admin_theme.
    */
    'theme_default' => env('FLOW_ADMIN_THEME', 'dark'),

    /*
    |--------------------------------------------------------------------------
    | Default step visualization
    |--------------------------------------------------------------------------
    | 'timeline', 'gantt', or 'dag'.
    */
    'step_viz_default' => env('FLOW_ADMIN_STEP_VIZ', 'timeline'),
];
