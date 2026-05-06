<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlowAdmin\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pin: the design-token stylesheet must be reachable WITHOUT going through
 * the admin middleware stack (`web,auth`). An unauthenticated browser
 * landing on the login redirect would otherwise render unstyled, and the
 * /flow page itself would 401/redirect long before the <link> ever
 * resolved. Macro 3 subtask 3.1.
 */
final class AssetRouteTest extends TestCase
{
    public function test_admin_css_is_served_with_text_css_content_type(): void
    {
        $response = $this->get('/_flow-admin/assets/admin.css');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/css; charset=utf-8');
    }

    public function test_admin_css_response_contains_design_tokens(): void
    {
        $response = $this->get('/_flow-admin/assets/admin.css');

        $response->assertStatus(200);

        // We can't use $response->assertSee*: those parse the body as HTML
        // and strip text/CSS-only payloads to ''. Pull the streamed body
        // explicitly and assert against the raw CSS string.
        $body = $this->extractStreamedBody($response->baseResponse);

        // Sanity-check a handful of design tokens that MUST be in the
        // ported stylesheet — drift here means the port is broken or
        // the route is serving a different file.
        $this->assertStringContainsString('--font-sans', $body);
        $this->assertStringContainsString('--bg-elevated', $body);
        $this->assertStringContainsString('--radius-md', $body);
    }

    public function test_admin_css_route_is_named_for_blade_link_tags(): void
    {
        // Blade templates link via `route('flow-admin.assets.css')`. If the
        // route name drifts, every page silently loses its stylesheet.
        $url = route('flow-admin.assets.css');

        $this->assertStringEndsWith('/_flow-admin/assets/admin.css', $url);
    }

    public function test_admin_css_response_emits_revalidatable_cache_headers(): void
    {
        // Pin: the asset is NOT content-hashed (Macro 3.2 introduces the
        // hashed Vite variant). To avoid 24h-stale CSS after a deploy, the
        // controller must send Last-Modified + must-revalidate, with a
        // short max-age that still amortises navigation costs.
        $response = $this->get('/_flow-admin/assets/admin.css');

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control');
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('must-revalidate', (string) $cacheControl);
        $this->assertStringContainsString('max-age=300', (string) $cacheControl);
        $response->assertHeader('Last-Modified');
    }

    public function test_admin_css_returns_304_when_if_modified_since_matches(): void
    {
        // Pin the conditional-GET path: a browser sending
        // If-Modified-Since equal to the file's mtime gets back 304 (not
        // a 200 with a fresh body). This is the bandwidth-saving leg of
        // the "no content hash + must-revalidate" caching contract.
        $first = $this->get('/_flow-admin/assets/admin.css');
        $first->assertStatus(200);
        $lastModified = $first->headers->get('Last-Modified');
        $this->assertNotNull($lastModified);

        $second = $this->withHeaders([
            'If-Modified-Since' => $lastModified,
        ])->get('/_flow-admin/assets/admin.css');

        $second->assertStatus(304);
    }

    /**
     * `Response::file()` returns a `BinaryFileResponse` that streams its
     * content via `sendContent()` to the SAPI rather than buffering it on
     * the response object — `getContent()` returns false in that case.
     * For tests, capture the streamed bytes via output buffering.
     */
    private function extractStreamedBody(Response $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
