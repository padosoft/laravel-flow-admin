# Copilot Instructions — `padosoft/laravel-flow-admin`

This repository is a **Laravel 13.x package** that ships an admin UI/UX for the headless workflow engine [`padosoft/laravel-flow`](https://github.com/padosoft/laravel-flow). It is not a standalone application — host apps install it via Composer.

## Project context Copilot must know

- **Stack**: Laravel `^13.0`, PHP `^8.3` (CI matrix on PHP 8.3 + 8.4). Frontend: Blade + Vite + Alpine.js. Tests: PHPUnit + Orchestra Testbench + Playwright.
- **Public surface**: only `Padosoft\LaravelFlow\Dashboard\*` (`FlowDashboardReadModel`, `DashboardActionAuthorizer`, public DTOs `RunDetail`/`ApprovalSummary`/`KpiSummary`) and the documented action API (`Flow::resume`, `Flow::reject`, `flow:replay`, `flow:deliver-webhooks`).
- **Internal surface (do NOT reference)**: `Padosoft\LaravelFlow\{Persistence,Models,Queue,Jobs,Console}\*`. Flag any code that type-hints, extends, mocks, or reflects on these.
- **Design contract**: pixel-perfect against `.design-source/project/`. The exported `styles.css` is the visual contract; classnames and CSS tokens (`--bg`, `--text`, `--accent`, `--status-*`, `--radius-*`, etc.) are part of the public contract for v0.x and must not be renamed silently.
- **Security posture**:
  - `DashboardActionAuthorizer` ships as `DenyAllAuthorizer` by default. Reject any change that flips the default to permissive.
  - Plain approval tokens are returned only at issuance time. Flag any code path that persists, logs, re-renders, or copies a plain token to the clipboard from server data.
  - Operator-facing errors must be sanitised (no stack traces, framework internals, or raw provider payloads).
- **Read model adapter**: production binding is `EloquentReadModel`; dev/Playwright binding is `ArrayReadModel` (deterministic seed 42). Adapter is selectable via `config('flow-admin.adapter')`.

## Review priorities

1. **Bugs**: status transitions, missing migrations, stale view-model state, paused-but-no-token, replay of compensated runs, duplicate `Resume`/`Reject` calls, CSRF expiry, N+1 queries on listing pages.
2. **Security**: secret leaks (tokens, API keys, signing material) anywhere in HTML/JSON/audit/log/exception, missing authorizer checks on mutation endpoints, missing CSRF on POST forms, missing confirm modal for destructive actions.
3. **A11y / UX**: icon-only buttons missing `aria-label`/tooltip, status badges using off-palette colours, nested cards, border radius >8px, density regressions (rows >40px standard / >32px tight), broken keyboard navigation in palette/drawer/modal.
4. **Test coverage**: new public behaviour without a Feature test; new UI without a Playwright scenario; README counts drifting from real PHPUnit/Playwright output.
5. **Public contract drift**: changes to `Padosoft\LaravelFlowAdmin\Contracts\*`, `config/flow-admin.php` keys, asset publish tags (`flow-admin-config`, `flow-admin-views`, `flow-admin-assets`), or route names (`flow-admin.*`) without an entry in `docs/UPGRADE.md` and an architecture test update.
6. **Laravel package ergonomics**: service provider correctness, config publish tags, view namespace, route name pattern, asset publish manifest, Testbench coverage.

## CI gates this repo runs (every PR must pass all of them)

- `php` — matrix `php: ['8.3','8.4']`: `composer validate --strict --no-check-publish` · `composer format:test` · `composer analyse` · `composer test`.
- `frontend` — Node 20: `npm ci` · `npm run lint` · `npm run build`.
- `e2e` — Node 20 matrix `browser: ['chromium','firefox','webkit']`: `npm ci` · `npx playwright install --with-deps` · `npm run test:e2e -- --project=$browser`.

If any of those is failing on this PR, that is a must-fix before merge.

## Repo conventions Copilot should enforce

- Branch model: `task/<macro-slug>` for macro tasks; `subtask/<macro-slug>-<n>-<short-name>` for slices. Subtask PR → macro branch; macro PR → `main`.
- Commits never include `dd()`/`dump()`/`console.log`/breakpoint markers. Flag if found.
- Files in `.claude/skills/*/SKILL.md` always have a YAML frontmatter `name:` and `description:`.
- `docs/PROGRESS.md` is updated on every meaningful handoff; `docs/LESSON.md` after every reusable discovery.

## Out of scope for this repo

- Business logic of workflow execution. That lives in `padosoft/laravel-flow`.
- Anything that is not the admin UI or its supporting bindings (read-model adapter, view-models, controllers, Blade views, CSS, JS, fixtures).
