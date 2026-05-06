<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers\Assets;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the package's design-token stylesheet (the verbatim port of
 * `.design-source/project/styles.css`) at the named route
 * `flow-admin.assets.css` (`GET /_flow-admin/assets/admin.css`).
 *
 * Why an invokable controller (not a closure):
 *   Closures cannot be route-cached. Consumer apps that run
 *   `php artisan route:cache` as part of their deploy pipeline would
 *   fail with `LogicException: Your routes file contains a closure`.
 *   The controller class form keeps the package safe to cache without
 *   asking the consumer to special-case our routes.
 *
 * Why the route is registered outside `routes/flow-admin.php`:
 *   Stylesheets must be reachable by unauthenticated browsers — sending
 *   the asset through the `web,auth` middleware stack would 302 it to
 *   `/login` and the rendered `/flow` page would render unstyled before
 *   auth ever completed. The package-internal `_flow-admin` URL prefix
 *   keeps the asset namespace clearly separate from the `/flow`
 *   user-facing routes.
 *
 * Why Last-Modified + must-revalidate (not just a long max-age):
 *   The CSS body is NOT content-hashed (Macro 3.2 will introduce the
 *   Vite-built hashed-asset variant via `flow-admin-assets` publish).
 *   With a 24h `max-age` and no validator, browsers would keep stale
 *   CSS for a full day after a package upgrade. Sending
 *   `Last-Modified` lets the browser send `If-Modified-Since` and the
 *   server reply 304 when the file mtime is unchanged. The short
 *   `max-age=300` + `must-revalidate` keeps caches fresh while still
 *   amortising network cost across a same-session navigation.
 */
final class AdminCssController
{
    /**
     * Absolute path to the package-bundled stylesheet on disk. Resolved
     * lazily so the constructor can stay parameterless (Laravel resolves
     * the controller via the container, no explicit binding required).
     */
    private function resolvePath(): string
    {
        return __DIR__ . '/../../../../resources/css/admin.css';
    }

    public function __invoke(Request $request): BinaryFileResponse|Response
    {
        $path = $this->resolvePath();
        $lastModified = filemtime($path);

        if ($lastModified !== false) {
            $ifModifiedSince = $request->headers->get('If-Modified-Since');
            if (is_string($ifModifiedSince) && strtotime($ifModifiedSince) === $lastModified) {
                return new Response(status: 304, headers: [
                    'Cache-Control' => 'public, max-age=300, must-revalidate',
                    'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
                ]);
            }
        }

        $response = response()->file($path, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=300, must-revalidate',
        ]);

        if ($lastModified !== false) {
            $response->setLastModified((new \DateTimeImmutable('@' . $lastModified)));
        }

        return $response;
    }
}
