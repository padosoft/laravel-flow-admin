<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Persists the operator's preferred theme (`light` | `dark`) in the
 * `flow_admin_theme` cookie. The layout reads this cookie before falling
 * back to `config('flow-admin.theme_default')`.
 *
 * The endpoint is intentionally tiny — single-action invokable — and
 * uses POST so the cookie write is not idempotent on a refresh / GET
 * scrape. Successful submit redirects back to the referrer (or /flow if
 * the form was submitted from outside the admin) so the new theme takes
 * effect immediately on the page the user was on.
 */
final class ThemeController extends Controller
{
    public const ALLOWED_THEMES = ['light', 'dark'];

    public const COOKIE_NAME = 'flow_admin_theme';

    /** Cookie lifetime: 1 year, plenty for a UI preference. */
    public const COOKIE_LIFETIME_MINUTES = 525600;

    public function toggle(Request $request): RedirectResponse
    {
        $requested = (string) $request->input('theme', '');
        if (! in_array($requested, self::ALLOWED_THEMES, true)) {
            abort(422, 'Unsupported theme. Allowed: light, dark.');
        }

        $target = $this->resolveRedirectTarget($request);

        // Use a forever-style cookie (large minutes value) rather than
        // `Cookie::forever()` so the duration is explicit and reviewable.
        return redirect($target)->cookie(
            new Cookie(
                name: self::COOKIE_NAME,
                value: $requested,
                expire: time() + self::COOKIE_LIFETIME_MINUTES * 60,
                path: '/',
                secure: $request->secure(),
                httpOnly: false,  // Read by JS in Macro 8 ⌘K palette to mirror DOM data-theme.
                sameSite: 'Lax',
            )
        );
    }

    /**
     * Pick the post-toggle redirect destination. Prefer the Referer
     * header so the operator stays on the page they toggled from; fall
     * back to /flow if the referer is missing, external, or points at
     * the toggle endpoint itself (a rare double-submit edge case).
     */
    private function resolveRedirectTarget(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');
        $appHost = $request->getHttpHost();

        if ($referer === '') {
            return route('flow-admin.overview');
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        if ($refererHost !== $appHost) {
            return route('flow-admin.overview');
        }

        $refererPath = parse_url($referer, PHP_URL_PATH);
        if ($refererPath === parse_url(route('flow-admin.theme.toggle'), PHP_URL_PATH)) {
            return route('flow-admin.overview');
        }

        return $referer;
    }
}
