<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers\Assets;

/**
 * Serves the built Flow Studio React island bundle
 * (`resources/js/studio.jsx` → `.vite/manifest.json` → hashed
 * `assets/studio-*.js`) at `flow-admin.assets.studio-js`
 * (`GET /_flow-admin/assets/studio.js`).
 */
final class StudioJsController extends AbstractManifestAssetController
{
    protected function manifestKey(): string
    {
        return 'resources/js/studio.jsx';
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
