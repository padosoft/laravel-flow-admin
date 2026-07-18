<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers\Assets;

/**
 * Serves the CSS chunk Vite split out for the Flow Studio bundle (mainly
 * `@xyflow/react`'s own stylesheet, imported inside `studio.jsx`) at
 * `flow-admin.assets.studio-css` (`GET /_flow-admin/assets/studio.css`).
 */
final class StudioCssController extends AbstractManifestAssetController
{
    protected function manifestKey(): string
    {
        return 'resources/js/studio.jsx';
    }

    protected function manifestAssetType(): string
    {
        return 'css';
    }

    protected function contentType(): string
    {
        return 'text/css; charset=utf-8';
    }
}
