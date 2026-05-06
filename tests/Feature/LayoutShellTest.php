<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlowAdmin\Tests\TestCase;

/**
 * Pin: the Macro 3.2 layout shell renders sidebar + topbar + content
 * around every admin page, the active-route highlight is honoured, and
 * the design-token stylesheet `<link>` is present.
 */
final class LayoutShellTest extends TestCase
{
    public function test_overview_page_renders_layout_shell(): void
    {
        $response = $this->get('/flow');

        $response->assertStatus(200);
        $response->assertSee('data-testid="flow-admin-shell"', false);
        $response->assertSee('data-testid="flow-admin-sidebar"', false);
        $response->assertSee('data-testid="flow-admin-topbar"', false);
        $response->assertSee('data-testid="flow-admin-overview-page"', false);
    }

    public function test_overview_links_design_token_stylesheet(): void
    {
        $response = $this->get('/flow');

        $response->assertStatus(200);
        // The layout MUST link the package's CSS via the named route, not
        // a hardcoded URL — drift in the route name should be a test
        // failure, not a silently-unstyled page.
        $response->assertSee('href="' . route('flow-admin.assets.css') . '"', false);
    }

    public function test_sidebar_marks_overview_as_active_on_root_route(): void
    {
        $response = $this->get('/flow');

        $response->assertStatus(200);
        // The layout passes `route => 'home'` from the overview page,
        // and the sidebar matches that against `data-route-key`. If
        // sidebar drift makes Overview unhighlighted, the operator
        // can't tell where they are.
        $response->assertSee('data-route-key="home"', false);
        $response->assertSee('class="nav-item active"', false);
    }
}
