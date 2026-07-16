<?php

declare(strict_types=1);
use Padosoft\LaravelFlowAdmin\Authorizers\AllowAllAuthorizer;
use Padosoft\LaravelFlowAdmin\Authorizers\DenyAllAuthorizer;

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
    |
    | If the env value is empty, whitespace-only, or resolves to no entries
    | after trim/filter, we fall back to ['web']. We never ship an empty
    | middleware array: that would silently disable session, CSRF, and the
    | session-driven authenticator on the admin routes — a footgun for
    | operators who set FLOW_ADMIN_MIDDLEWARE="" thinking they were disabling
    | only `auth`.
    */
    'middleware' => (function (): array {
        $resolved = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FLOW_ADMIN_MIDDLEWARE', 'web,auth')),
        ), static fn (string $name): bool => $name !== ''));

        return $resolved !== [] ? $resolved : ['web'];
    })(),

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
    | Action authorizer
    |--------------------------------------------------------------------------
    | Default deny-by-default implementation. Override in host apps to integrate
    | your permission model and make read / mutation actions available (bind
    | your own ActionAuthorizer in a service provider — not via this env var,
    | which only selects between the two SHIPPED, non-RBAC implementations).
    |
    | FLOW_ADMIN_AUTHORIZER=allow opts into AllowAllAuthorizer — dev/E2E only
    | (see testbench.yaml), never production. Any value other than the
    | literal "allow" keeps the deny-by-default binding, including an unset
    | or unrecognized env var.
    */
    'authorizer' => strtolower((string) env('FLOW_ADMIN_AUTHORIZER', 'deny')) === 'allow'
        ? AllowAllAuthorizer::class
        : DenyAllAuthorizer::class,

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
