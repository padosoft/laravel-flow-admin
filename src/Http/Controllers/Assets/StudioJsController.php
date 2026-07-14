<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers\Assets;

/**
 * Serves the built Flow Studio React island bundle
 * (`resources/js/studio.jsx` → `.vite/manifest.json` → hashed
 * `assets/studio-*.js`) at `flow-admin.assets.studio-js`
 * (`GET /_flow-admin/assets/studio.js`).
 *
 * Registered outside the `web,auth` middleware group (same as
 * `AdminCssController`), so it's reachable by an unauthenticated browser.
 * The bundle itself carries no secrets or authorization decisions — same
 * public-static-asset posture as any other JS/CSS file a browser fetches
 * without credentials — so exposing it pre-auth is not a data exposure;
 * it's simply consistent with how every other static admin asset is
 * served (and required for the CSS route's own "unauthenticated login
 * redirect must still render styled" case, which this route reuses the
 * resolver plumbing from).
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
