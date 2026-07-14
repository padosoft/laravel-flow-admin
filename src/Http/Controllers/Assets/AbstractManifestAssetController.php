<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers\Assets;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a single asset from the package's OWN Vite build output
 * (`public/vendor/flow-admin/`), resolved through `.vite/manifest.json`
 * rather than a fixed filename — the built filename is content-hashed and
 * changes on every source edit.
 *
 * Why a package-internal route instead of a Vite-built `<script>`/`<link>`
 * tag pointing at `public_path('vendor/flow-admin/...')` (the path a
 * consumer gets after `php artisan vendor:publish --tag=flow-admin-assets`):
 * Testbench's `serve` command does not expose this package's
 * `public/vendor/flow-admin/` build output through ITS public dir — only
 * through the PACKAGE's own filesystem path — so the manifest-resolved
 * hashed URL is unreachable during local dev / Playwright E2E without a
 * publish step. Reading the manifest and streaming the file directly (same
 * pattern as `AdminCssController`, generalised here since a build-hashed
 * asset can't be served from a fixed path) works identically whether or not
 * `vendor:publish` has ever run.
 *
 * Subclasses supply the manifest entry key and which output (`file` — the
 * entry's own JS, or the first `css` chunk it pulls in) to serve.
 */
abstract class AbstractManifestAssetController
{
    /**
     * The manifest's entry key, e.g. `resources/js/studio.jsx` — matches
     * `vite.config.js`'s `build.rollupOptions.input` source path, NOT the
     * built output filename.
     */
    abstract protected function manifestKey(): string;

    /**
     * `'file'` serves the entry's own built asset; `'css'` serves the
     * first CSS chunk Vite split out for that entry (present only when
     * the entry — or something it imports — pulls in CSS).
     */
    abstract protected function manifestAssetType(): string;

    abstract protected function contentType(): string;

    private function buildRoot(): string
    {
        return __DIR__ . '/../../../../public/vendor/flow-admin';
    }

    private function resolveBuiltPath(): ?string
    {
        $manifestPath = $this->buildRoot() . '/.vite/manifest.json';

        if (! is_file($manifestPath)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            return null;
        }

        $entry = $manifest[$this->manifestKey()] ?? null;

        if (! is_array($entry)) {
            return null;
        }

        if ($this->manifestAssetType() === 'css') {
            $css = $entry['css'] ?? null;
            $relative = is_array($css) && isset($css[0]) && is_string($css[0]) ? $css[0] : null;
        } else {
            $relative = isset($entry['file']) && is_string($entry['file']) ? $entry['file'] : null;
        }

        if ($relative === null) {
            return null;
        }

        // Defense in depth: the manifest is build-tool-controlled, not
        // user input, but this route is reachable pre-auth — resolve the
        // real filesystem path and refuse to serve anything outside
        // buildRoot() rather than trusting the manifest's relative path
        // to never contain `..`/an absolute path.
        $buildRoot = realpath($this->buildRoot());
        $resolved = realpath($this->buildRoot() . '/' . $relative);

        if ($buildRoot === false || $resolved === false || ! str_starts_with($resolved, $buildRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $resolved;
    }

    public function __invoke(Request $request): BinaryFileResponse|Response
    {
        $path = $this->resolveBuiltPath();

        if ($path === null || ! is_file($path)) {
            return new Response(
                "Built asset not found — run `npm run build` in the package before serving this route.\n",
                404,
                ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store'],
            );
        }

        $lastModified = filemtime($path);

        if ($lastModified !== false) {
            $ifModifiedSince = $request->headers->get('If-Modified-Since');
            $ifModifiedAt = is_string($ifModifiedSince) ? strtotime($ifModifiedSince) : false;

            // Per RFC 7232 §3.3: a client's If-Modified-Since is a "not
            // modified since" floor, not an exact-match key — a client
            // MAY legitimately send a timestamp later than the resource's
            // real mtime (clock skew, second-level rounding). 304 whenever
            // the client's cached copy is at least as fresh as the file.
            if ($ifModifiedAt !== false && $ifModifiedAt >= $lastModified) {
                return new Response(status: 304, headers: [
                    'Cache-Control' => 'public, max-age=300, must-revalidate',
                    'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
                ]);
            }
        }

        $response = response()->file($path, [
            'Content-Type' => $this->contentType(),
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ]);

        if ($lastModified !== false) {
            $response->setLastModified(new \DateTimeImmutable('@' . $lastModified));
        }

        return $response;
    }
}
