# `padosoft/laravel-flow-admin` — Operating Rules

> Single source of truth for behaviour rules in this repo.
> Cross-referenced from `AGENTS.md`, `CLAUDE.md`, `.github/copilot-instructions.md`, and `.claude/skills/laravel-flow-admin-shell/SKILL.md`.

## Source Of Truth

- Implementation plan: `docs/IMPLEMENTATION_PLAN.md`.
- Handoff state: `docs/PROGRESS.md`.
- Reusable findings: `docs/LESSON.md`.
- Agent entrypoints: `AGENTS.md` and `CLAUDE.md`.
- Repo-local shell skill: `.claude/skills/laravel-flow-admin-shell/SKILL.md`.
- Design source: `.design-source/project/`.

## Stack Direction

- Laravel `^13.0`, PHP `^8.3` (CI hard-gates on PHP 8.3 + 8.4).
- Frontend: Blade + Vite + Alpine.js (no SPA framework).
- CSS: vanilla CSS with design tokens ported from the design source. No Tailwind in v0.x.
- Tests: PHPUnit + Orchestra Testbench (Laravel 13). Playwright (chromium + firefox + webkit) for E2E.
- Static analysis: PHPStan level 8 (Larastan).
- Style: Laravel Pint.

## Implementation Defaults

- Prefer immutable view-models (`final readonly class`) over Eloquent models in views.
- Keep Laravel integration at the edges: `FlowAdminServiceProvider`, config, routes, view namespace, asset publish tags.
- Server-render everything that can be server-rendered. Use Alpine only for genuinely client-side concerns (theme toggle, palette, drawer, polling).
- Read model adapter is selectable via `config('flow-admin.adapter')`: `eloquent` (default, prod) or `array` (dev/Playwright fixtures).
- Mutations always go through `DashboardActionAuthorizer` — no exceptions.
- Approval token plain text is shown only at issuance, never re-rendered.

## Preflight Rules (before writing code)

Run a short local design review and make the patch plan explicit:

- Public contract / BC: identify new methods, bindings, default behaviour changes, upgrade hazards.
- Edge cases: enumerate invalid input, empty result sets, paused-but-no-token, replay of compensated runs, missing migrations, CSRF token expiry.
- Diagnostics: 4xx/5xx must be actionable and never expose framework internals.
- Docs / README: decide whether shipped capability wording, config examples, or test/assertion counts must change.
- Tests: add explicit feature tests + at least one Playwright scenario per new public behaviour.

## Security Rules

- Never expose API keys, tokens, secrets, partial secret previews, or webhook signing material in HTML, JSON, audit rows, exception messages, or logs.
- Plain approval tokens are returned only on the immediate `FlowRun` issuance — they are never re-rendered, copied to clipboard from server data, or exposed to the dashboard once persisted.
- Every mutation passes through `DashboardActionAuthorizer`. The default binding is `DenyAllAuthorizer`. Production deployments MUST bind a real authorizer; `AllowAllAuthorizer` is dev-only.
- Sanitise operator-facing errors. No stack traces, no raw provider payloads, no framework internals leaked to JSON or HTML.
- Confirm dialogs (modal) are required for `Replay`, `Cancel`, `Reject`, `Retry webhook`. The confirm action posts a form with CSRF token.
- Asset and view publishing tags expose only safe names. No `--tag=flow-admin-secrets`-style tags ever.

## Testing Rules

Every package subtask runs the relevant subset:

```bash
composer validate --strict --no-check-publish
composer format:test
composer analyse
composer test
npm run lint
npm run build
npm run test:e2e
```

- Feature tests use Testbench + SQLite + the published `padosoft/laravel-flow` migrations.
- For every new public behaviour add at least one Feature test AND at least one Playwright scenario.
- Playwright scenarios must use the `array` adapter via env (`FLOW_ADMIN_ADAPTER=array`) so fixtures are deterministic.
- Visual regression Playwright snapshots have a 0.05 tolerance on layout pages (Overview, Runs, Run Detail).
- Copilot review counts and README counts must match `composer test` and `npm run test:e2e --reporter=line` outputs.

## Documentation Rules

- Update `docs/PROGRESS.md` after meaningful handoff points. Per-PR Copilot/CI history stays on the PR; only durable restart state lands in PROGRESS.
- Update `docs/LESSON.md` after reusable discoveries from Copilot comments, CI failures, local tooling workarounds, or design decisions.
- Date entries with `YYYY-MM-DD`.
- README must never promise unimplemented behaviour as available.
- README test/assertion counts must match the actual PHPUnit + Playwright output.
- When adding/improving any feature, review README section `Comparison vs alternatives` and update it. Use status-prefix format: `✅ YES — …`, `⚠️ PARTIAL — …`, `❌ NO — …`.

## PR Rules

- Macro branch per macro task (`task/<macro-slug>`).
- Subtask branch per coherent implementation slice (`subtask/<macro-slug>-<n>-<short-name>`).
- Subtask PR targets the macro branch. Macro PR targets `main`.
- Request Copilot Code Review for every PR (`--reviewer copilot` with GraphQL fallback).
- CI is expected for PRs targeting `main` and `task/**`, plus pushes to `main`.
- Merge only after local gates, Copilot review, and reported CI checks are clean.
- If a PR reports no checks, verify the workflow trigger and base branch, update the trigger if needed, then re-check the same PR. Do not merge until checks for the current head are visible and green.
- If GitHub access is unavailable, record the exact blocked remote step in `docs/PROGRESS.md`.

## UI Rules

- Pixel-perfect against `.design-source/project/`. The exported `styles.css` is the visual contract for v0.x.
- Dark theme default; light theme supported via `data-theme="light"` on `<html>`. Persist user choice in cookie `flow_admin_theme`.
- Density: row 40px standard, 32px tight; topbar 48px; sidebar 232px wide.
- Border radius ≤ 8px (`--radius-md`). No values larger than that.
- No nested cards. No marketing hero. No landing page. Build the operative admin as the first screen.
- Every icon-only button has an `aria-label` + tooltip.
- Tabular nums for numeric/timestamp/duration cells.
- Status badges use the design tokens `--status-{running,success,failed,paused,pending,compensated}` — do not invent new status colours.

## v0.1.0 Stability Rules

- Public extension surface is `Padosoft\LaravelFlowAdmin\Contracts\*`. Adapters, view models, controllers, support classes are internal.
- Public configuration keys in `config/flow-admin.php` are part of the contract. Renaming requires an upgrade guide entry and a major bump.
- Public asset publish tags (`flow-admin-config`, `flow-admin-views`, `flow-admin-assets`) are part of the contract.
- Public route names (`flow-admin.*`) are part of the contract.
- The `tests/Architecture/` suite pins these contracts. Update it in the same PR when intentionally evolving them.
