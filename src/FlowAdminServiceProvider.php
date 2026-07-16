<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin;

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Padosoft\LaravelFlow\Contracts\DefinitionRepository;
use Padosoft\LaravelFlow\Dashboard\FlowDashboardReadModel;
use Padosoft\LaravelFlow\Node\NodeRegistry;
use Padosoft\LaravelFlowAdmin\Adapters\ArrayReadModel;
use Padosoft\LaravelFlowAdmin\Adapters\EloquentReadModel;
use Padosoft\LaravelFlowAdmin\Authorizers\AllowAllAuthorizer;
use Padosoft\LaravelFlowAdmin\Authorizers\DenyAllAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ReadModel;
use Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes\DemoChargeNode;
use Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes\DemoNotifyNode;
use Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes\DemoTriggerNode;
use Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes\DemoValidateNode;
use Padosoft\LaravelFlowAdmin\Http\Controllers\Assets\AdminCssController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\Assets\StudioCssController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\Assets\StudioJsController;
use Padosoft\LaravelFlowAdmin\Http\Controllers\ThemeController;

class FlowAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/flow-admin.php',
            'flow-admin'
        );

        $this->app->singleton(FlowDashboardReadModel::class, function (): FlowDashboardReadModel {
            return new FlowDashboardReadModel;
        });

        $this->app->singleton(EloquentReadModel::class, function ($app): EloquentReadModel {
            return new EloquentReadModel(
                $app->make(FlowDashboardReadModel::class),
                $app->make(DefinitionRepository::class),
                $app->make(NodeRegistry::class),
            );
        });

        $this->app->singleton(ReadModel::class, function ($app): ReadModel {
            $adapter = (string) $app->make('config')->get('flow-admin.adapter', 'eloquent');
            $arrayAdapter = ArrayReadModel::class;

            if (strtolower($adapter) === 'array' && class_exists($arrayAdapter)) {
                return $app->make($arrayAdapter);
            }

            return $app->make(EloquentReadModel::class);
        });

        $this->app->singleton(ActionAuthorizer::class, function ($app): ActionAuthorizer {
            $authorizerClass = $app->make('config')->get(
                'flow-admin.authorizer',
                DenyAllAuthorizer::class,
            );

            if (! is_string($authorizerClass) || ! class_exists($authorizerClass)) {
                return new DenyAllAuthorizer;
            }

            // FLOW_ADMIN_AUTHORIZER=allow (config/flow-admin.php) is meant
            // for local dev / E2E only. A stray or copy-pasted value in a
            // production .env would otherwise silently disable EVERY
            // mutation gate, not just Studio editing — refuse it here
            // regardless of what the config resolved to.
            if ($authorizerClass === AllowAllAuthorizer::class && $app->environment('production')) {
                Log::warning('laravel-flow-admin: FLOW_ADMIN_AUTHORIZER=allow is ignored in the production environment; falling back to DenyAllAuthorizer.');

                return new DenyAllAuthorizer;
            }

            /** @var class-string<ActionAuthorizer> $authorizerClass */
            $authorizer = $app->make($authorizerClass);
            if (! $authorizer instanceof ActionAuthorizer) {
                return new DenyAllAuthorizer;
            }

            return $authorizer;
        });
    }

    public function boot(): void
    {
        $this->registerDemoNodesIfInDemoMode();

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'flow-admin');

        // Register file-based (anonymous) Blade components under the
        // `flow-admin` prefix. Templates use `<x-flow-admin::icon name="…" />`
        // resolved from `resources/views/components/icon.blade.php`. The
        // path-based registration (vs the class-based `componentNamespace`)
        // keeps the design-system primitives template-only — no PHP class
        // boilerplate per icon/badge/button.
        Blade::anonymousComponentPath(
            path: __DIR__ . '/../resources/views/components',
            prefix: 'flow-admin',
        );

        $this->loadRoutesFrom(__DIR__ . '/../routes/flow-admin.php');

        $this->registerPackagedAssetRoutes();

        // Exempt the theme-preference cookie from EncryptCookies. The
        // value is `light` or `dark` (publicly knowable), and Macro 8
        // wires up a JS theme-mirror in the ⌘K palette that reads
        // `document.cookie`; an encrypted payload would break both
        // assertions in tests and the runtime read. The cookie is also
        // explicitly `httpOnly: false` for the same reason.
        EncryptCookies::except(ThemeController::COOKIE_NAME);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/flow-admin.php' => config_path('flow-admin.php'),
            ], 'flow-admin-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/flow-admin'),
            ], 'flow-admin-views');

            // flow-admin-assets tag publishes the *built* Vite bundle
            // (`.vite/manifest.json` + hashed `assets/*.{js,css}`), not the
            // pre-build source under `resources/`. Publishing source would
            // skip Vite, drop the manifest, and force consumers to re-bundle.
            //
            // The directory is generated by `npm run build` and is git-ignored
            // during development; release tarballs (Macro 9) include it via a
            // CI build-and-bundle step before `composer archive`. The guard
            // keeps `php artisan vendor:publish` honest before that point: if
            // the built tree is absent, the tag is simply not registered
            // instead of mapping a non-existent source.
            $builtAssetsPath = __DIR__ . '/../public/vendor/flow-admin';
            if (is_dir($builtAssetsPath)) {
                $this->publishes([
                    $builtAssetsPath => public_path('vendor/flow-admin'),
                ], 'flow-admin-assets');
            }
        }
    }

    /**
     * `ArrayReadModel`'s fixture graph/catalog reference node types
     * (`demo.trigger`, `demo.validate`, `demo.charge`, `demo.notify`) that
     * only exist as fixture DATA, not as real `NodeRegistry` entries.
     * `GraphValidator` (used by `StudioController::storeDraft()`, always
     * on the real registry regardless of `flow-admin.adapter`) would
     * reject every Studio-composed graph in demo mode as "unknown node
     * type" without this. Registering real handler classes for them ONLY
     * when `adapter === 'array'` keeps a production Eloquent deployment's
     * `NodeRegistry` free of demo/fixture noise — those deployments
     * register their own real node types via the host app.
     */
    private function registerDemoNodesIfInDemoMode(): void
    {
        if (strtolower((string) $this->app->make('config')->get('flow-admin.adapter', 'eloquent')) !== 'array') {
            return;
        }

        $registry = $this->app->make(NodeRegistry::class);

        // register() throws DuplicateNodeTypeException on a second call
        // for the same type — guard per type rather than registerMany(),
        // since boot() can plausibly observe an already-populated registry
        // (e.g. a host app registering the same handler class itself, or
        // a future test/console context that boots this provider twice).
        $handlers = [
            'demo.trigger' => DemoTriggerNode::class,
            'demo.validate' => DemoValidateNode::class,
            'demo.charge' => DemoChargeNode::class,
            'demo.notify' => DemoNotifyNode::class,
        ];

        foreach ($handlers as $type => $handlerClass) {
            if ($registry->has($type)) {
                continue;
            }

            $registry->register($handlerClass);
        }
    }

    /**
     * Register the package's static asset routes (`admin.css`, `studio.js`,
     * `studio.css`) served directly from `resources/`. `admin.css` is the
     * pixel-perfect port of `.design-source/project/styles.css` and provides
     * the design tokens (light + dark theme) consumed by every admin Blade
     * template; the studio assets back the React island canvas.
     *
     * The handlers are invokable controllers, NOT closures: Laravel cannot
     * serialise closures for `php artisan route:cache`, a common production
     * optimisation in consumer apps. The controller form keeps the package
     * route-cacheable and lets the cache headers (Last-Modified,
     * must-revalidate, max-age=300) live in tested code rather than here.
     *
     * Why Laravel routes instead of Vite-built link/script tags:
     * - Testbench's `serve` command does not expose this package's
     *   `public/vendor/flow-admin/` build output through its public dir, so
     *   the Vite hashed-asset URL is unreachable during E2E.
     * - Consumer apps that have run `php artisan vendor:publish
     *   --tag=flow-admin-assets` get the optimised hashed assets via the
     *   normal Vite manifest at runtime; these fallback routes are the
     *   "always works" path that does not require a publish step.
     * - They are intentionally registered outside `routes/flow-admin.php`
     *   so they do NOT inherit the admin middleware stack (`web,auth`):
     *   stylesheets/scripts must be reachable for unauthenticated users too,
     *   otherwise the login redirect would render unstyled.
     *
     * The routes live under `/_flow-admin/assets/{file}` — the underscore
     * prefix marks them as package-internal, away from the user-facing
     * `/flow` namespace.
     */
    private function registerPackagedAssetRoutes(): void
    {
        Route::get('/_flow-admin/assets/admin.css', AdminCssController::class)
            ->name('flow-admin.assets.css');

        Route::get('/_flow-admin/assets/studio.js', StudioJsController::class)
            ->name('flow-admin.assets.studio-js');

        Route::get('/_flow-admin/assets/studio.css', StudioCssController::class)
            ->name('flow-admin.assets.studio-css');
    }
}
