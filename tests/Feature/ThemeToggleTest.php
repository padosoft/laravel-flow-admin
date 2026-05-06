<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlowAdmin\Http\Controllers\ThemeController;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;

/**
 * Pin: theme toggle endpoint persists the operator's choice in the
 * `flow_admin_theme` cookie, the layout reads it, and invalid input is
 * rejected at 422.
 */
final class ThemeToggleTest extends TestCase
{
    public function test_default_theme_renders_when_no_cookie_present(): void
    {
        $response = $this->get('/flow');

        $response->assertStatus(200);
        // Default theme from config/flow-admin.php is 'dark'.
        $response->assertSee('<html lang="en" data-theme="dark">', false);
    }

    public function test_theme_cookie_overrides_config_default(): void
    {
        // The cookie is exempt from EncryptCookies (see FlowAdminServiceProvider),
        // so the test must inject it as plain via withUnencryptedCookies —
        // withCookies() encrypts by default and the middleware would
        // refuse to decrypt our plain marker, falling back to default.
        $response = $this->withUnencryptedCookies(['flow_admin_theme' => 'light'])
            ->get('/flow');

        $response->assertStatus(200);
        $response->assertSee('<html lang="en" data-theme="light">', false);
    }

    public function test_invalid_theme_cookie_falls_back_to_config_default(): void
    {
        // A tampered or stale cookie value must NOT slip through to the
        // <html data-theme="…"> attribute — the layout must validate
        // against the allowed list.
        $response = $this->withUnencryptedCookies(['flow_admin_theme' => 'neon'])
            ->get('/flow');

        $response->assertStatus(200);
        $response->assertSee('<html lang="en" data-theme="dark">', false);
        $response->assertDontSee('data-theme="neon"', false);
    }

    public function test_post_theme_endpoint_sets_cookie_and_redirects(): void
    {
        $response = $this->post('/flow/theme', ['theme' => 'light']);

        $response->assertStatus(302);
        // The cookie is set unencrypted (by EncryptCookies::except in
        // the ServiceProvider), so the assertion must skip decryption.
        $response->assertCookie(ThemeController::COOKIE_NAME, 'light', encrypted: false);
    }

    public function test_post_theme_rejects_unsupported_value(): void
    {
        $response = $this->post('/flow/theme', ['theme' => 'neon']);

        $response->assertStatus(422);
        $response->assertCookieMissing(ThemeController::COOKIE_NAME);
    }

    public function test_post_theme_rejects_missing_value(): void
    {
        $response = $this->post('/flow/theme', []);

        $response->assertStatus(422);
        $response->assertCookieMissing(ThemeController::COOKIE_NAME);
    }

    public function test_post_theme_redirects_back_to_referer_when_same_host(): void
    {
        $response = $this->withHeaders(['Referer' => 'http://localhost/flow/runs'])
            ->post('/flow/theme', ['theme' => 'dark']);

        $response->assertStatus(302);
        $response->assertRedirect('http://localhost/flow/runs');
    }

    public function test_post_theme_ignores_external_referer(): void
    {
        // External referer (e.g. someone landed on /flow from a Slack
        // link) must NOT cause the post-toggle redirect to leave the
        // app — fall back to the overview route.
        $response = $this->withHeaders(['Referer' => 'https://evil.example.test/'])
            ->post('/flow/theme', ['theme' => 'light']);

        $response->assertStatus(302);
        $response->assertRedirect(route('flow-admin.overview'));
    }
}
