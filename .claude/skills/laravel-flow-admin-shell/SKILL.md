---
name: laravel-flow-admin-shell
description: Anchor skill for the padosoft/laravel-flow-admin package. Read FIRST when opening this repo to understand stack (Laravel 13 + PHP 8.3/8.4 + Blade + Vite + Alpine.js + Playwright), where the design source lives (.design-source/), the read-model contract from padosoft/laravel-flow we consume, and the macro/subtask branch + Copilot review loop. Trigger when starting any work in this repo, when restarting a session, or when a sub-agent is dispatched.
---

# laravel-flow-admin — Shell Skill

This is the entrypoint skill for the `padosoft/laravel-flow-admin` repository.

## What this package is

A **Laravel 13.x** package that ships a **server-rendered admin UI/UX** for the headless workflow engine [`padosoft/laravel-flow`](https://github.com/padosoft/laravel-flow).

The package consumes only the public `@api` surface of `padosoft/laravel-flow`:

- `Padosoft\LaravelFlow\Dashboard\FlowDashboardReadModel` — read access to runs, steps, approvals, outbox, KPIs.
- `Padosoft\LaravelFlow\Dashboard\DashboardActionAuthorizer` — per-action authorisation (deny-by-default).
- Public DTOs: `RunDetail`, `ApprovalSummary`, `KpiSummary`.
- Status constants on `Padosoft\LaravelFlow\FlowRun`.
- Action API: `Flow::resume`, `Flow::reject`, `flow:replay`, `flow:deliver-webhooks`.

The package **never** type-hints, extends, or mocks `Padosoft\LaravelFlow\{Persistence,Models,Queue,Jobs,Console}\*` — those are `@internal`.

## Stack (decided, see `docs/IMPLEMENTATION_PLAN.md`)

| Layer | Choice |
|-------|--------|
| PHP | `^8.3` (CI matrix `8.3` + `8.4`) |
| Laravel | `^13.0` |
| Frontend build | Vite |
| CSS | vanilla CSS with design tokens (light/dark) ported from `.design-source/project/styles.css` |
| JS | Alpine.js (theme toggle, palette, drawer, tabs, polling) |
| PHP test | PHPUnit + Orchestra Testbench (Laravel 13) |
| E2E | Playwright (chromium + firefox + webkit) |
| Static analysis | PHPStan level 8 (Larastan) |
| Style | Laravel Pint |
| CI | GitHub Actions, 3 jobs (php matrix, frontend, e2e matrix) |

## Read FIRST when opening this repo / restarting a session

1. `docs/IMPLEMENTATION_PLAN.md` — canonical plan (macro tasks, subtasks, exit criteria).
2. `docs/PROGRESS.md` — handoff state (active branch, open PRs, blocker).
3. `docs/RULES.md` — operating rules.
4. `docs/LESSON.md` — reusable findings.
5. `.claude/skills/laravel-flow-admin-shell/SKILL.md` — this file (orientation + skill priority map).
6. `.claude/skills/copilot-pr-review-loop/SKILL.md` — mandatory Copilot+CI loop.
7. `.claude/skills/pre-push-self-review/SKILL.md` — pre-push self-review checklist.

When dispatching a sub-agent, **always pass these 7 files as context.** This list aligns with `AGENTS.md` and `CLAUDE.md`; if you change it here, update both entrypoints in the same commit.

## Where the design source lives

The visual baseline is exported under `.design-source/project/`:

- `index.html` — entry HTML.
- `styles.css` — design tokens + components (light + dark themes).
- `shell.jsx`, `ui.jsx`, `app.jsx` — layout shell, icons, root component.
- `page-overview.jsx`, `page-runs.jsx`, `page-run-detail.jsx`, `page-misc.jsx` — page implementations.
- `data.jsx` — deterministic mock data (seed 42).
- `tweaks-panel.jsx` — runtime preferences (theme, step viz).

Goal: **pixel-perfect** Blade port. Do not change classnames or colour tokens unless the design is explicitly updated.

## Branch strategy & PR loop (NON-NEGOTIABLE)

- One branch per macro task: `task/<macro-slug>` (e.g. `task/agent-operating-system`, `task/baseline-tooling`, `task/design-system-shell`).
- One subtask branch per coherent slice: `subtask/<macro-slug>-<n>-<short-name>`.
- Subtask PR → macro branch. Macro PR → `main`.
- Every PR runs the Copilot review + CI loop (see `copilot-pr-review-loop` skill).
- Local gates BEFORE every push: `composer validate --strict --no-check-publish`, `composer format:test`, `composer analyse`, `composer test`, `npm run lint`, `npm run build`, `npm run test:e2e`.
- Merge only when CI is green on all jobs (php matrix + frontend + e2e matrix) AND Copilot review is APPROVED or has zero must-fix outstanding.

## When to invoke other skills

- Opening a PR or pushing to a PR branch → **`copilot-pr-review-loop`** (mandatory loop).
- Before pushing to any branch → **`pre-push-self-review`** (checklist).
- Adding/modifying a Playwright scenario → **`playwright-enterprise-tester`**.
- Adding/modifying an admin page or controller → **`create-admin-interface`** (orchestrator) → **`admin-interface-backend`** + **`admin-interface-frontend`**.
- Modifying README or test counts → **`test-count-readme-sync`**.
- After receiving Copilot review → **`review-pr-comments`** to triage and address.

## Security guarantees the package upholds

- No secrets/tokens/keys in UI, JSON, logs, audit, exception messages.
- All mutations (`resume`, `reject`, `replay`, `cancel`, retry-webhook) are gated by `DashboardActionAuthorizer` (default `DenyAllAuthorizer`).
- Approval tokens are SHA-256 hashed at rest (the package guarantees this); the admin only ever displays the plain token at issuance, never re-renders it.
- Operator-facing errors are sanitised (no stack traces, no raw framework internals).

## Done definition for v0.1.0

See §4 of `docs/IMPLEMENTATION_PLAN.md`. Short version:
- 10 macro PRs merged on `main` with full CI green.
- README WOW-style with screenshots, badges, comparison table.
- Smoke install verified on a vanilla Laravel 13 app.
- Tag `v0.1.0` + GitHub release published.
- `docs/LESSON.md` harvested into `.claude/rules` and `.claude/skills`.
