<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers\Assets;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves any hashed file from the package's Vite build output
 * (`public/vendor/flow-admin/assets/{file}`).
 *
 * The named entry controllers ({@see StudioJsController} etc.) resolve one
 * entry's file through the manifest, but a multi-entry build with more than
 * one React island makes Vite split shared code (React itself) into a
 * SHARED chunk (`jsx-runtime-*.js`) that each entry ES-imports by its hashed
 * filename. Testbench's `serve` doesn't expose the package's public dir, so
 * that sibling chunk would 404 — breaking every React island — without this
 * catch-all. It sits AFTER the named asset routes (which win for their exact
 * paths) and serves the remaining hashed chunks.
 *
 * Safety: the `{file}` route constraint forbids slashes (no traversal), and
 * the resolved realpath is refused unless it stays inside the built
 * `assets/` directory. The build output contains no secrets — same pre-auth
 * static-asset posture as the named asset controllers.
 */
final class PackagedAssetController
{
    private function assetsRoot(): string
    {
        return __DIR__ . '/../../../../public/vendor/flow-admin/assets';
    }

    public function __invoke(string $file): BinaryFileResponse|Response
    {
        $assetsRoot = realpath($this->assetsRoot());
        $resolved = realpath($this->assetsRoot() . '/' . $file);

        if (
            $assetsRoot === false
            || $resolved === false
            || ! str_starts_with($resolved, $assetsRoot . DIRECTORY_SEPARATOR)
            || ! is_file($resolved)
        ) {
            return new Response(
                "Built asset not found — run `npm run build` in the package before serving this route.\n",
                404,
                ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'],
            );
        }

        return response()->file($resolved, [
            'Content-Type' => $this->contentTypeFor($file),
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ]);
    }

    private function contentTypeFor(string $file): string
    {
        return match (true) {
            str_ends_with($file, '.js') => 'application/javascript; charset=utf-8',
            str_ends_with($file, '.css') => 'text/css; charset=utf-8',
            default => 'application/octet-stream',
        };
    }
}
