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

    /*
    |--------------------------------------------------------------------------
    | AI flow builder
    |--------------------------------------------------------------------------
    | The Studio editor's "Build with AI" panel calls padosoft/laravel-flow-ai's
    | FlowBuilderService to turn a natural-language prompt into a VALIDATED draft
    | graph the operator reviews before saving. The panel is only offered when
    | that optional package is installed; this section only supplies the default
    | model when a request omits one.
    |
    | FLOW_ADMIN_FAKE_LLM=1 swaps the real (network) LLM client for a
    | deterministic fake — dev/E2E only, never production (see
    | FlowAdminServiceProvider::bindFakeLlmClientIfOptedIn()).
    */
    'ai' => [
        'model' => env('FLOW_ADMIN_AI_MODEL', 'claude-sonnet-5'),

        // Dev/E2E-only switch: bind the deterministic FakeLlmClient instead of
        // the real (network) LLM client. The provider refuses this in the
        // production environment regardless of the value (see
        // FlowAdminServiceProvider::bindFakeLlmClientIfOptedIn()).
        'fake' => filter_var(env('FLOW_ADMIN_FAKE_LLM', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
