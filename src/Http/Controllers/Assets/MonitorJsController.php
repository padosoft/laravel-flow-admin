<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers\Assets;

/**
 * Serves the built live run-monitor React island bundle
 * (`resources/js/monitor.jsx` → `.vite/manifest.json` → hashed
 * `assets/monitor-*.js`) at `flow-admin.assets.monitor-js`
 * (`GET /_flow-admin/assets/monitor.js`).
 *
 * Same public-static-asset posture as {@see StudioJsController}: the bundle
 * carries no secrets or authorization decisions, so it is served outside the
 * `web,auth` middleware group like every other static admin asset.
 */
final class MonitorJsController extends AbstractManifestAssetController
{
    protected function manifestKey(): string
    {
        return 'resources/js/monitor.jsx';
    }

    protected function manifestAssetType(): string
    {
        return 'file';
    }

    protected function contentType(): string
    {
        return 'application/javascript; charset=utf-8';
    }
}
