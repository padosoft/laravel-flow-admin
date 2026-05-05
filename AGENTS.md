# Laravel Flow Admin — Agent Guide

This repository is the reusable package `padosoft/laravel-flow-admin`: a Laravel 13.x admin UI/UX for the headless workflow engine [`padosoft/laravel-flow`](https://github.com/padosoft/laravel-flow).

If a session restarts with missing context, read these files first, in this order:

1. `docs/IMPLEMENTATION_PLAN.md` — canonical 10-macro implementation plan.
2. `docs/PROGRESS.md` — current branch, open PRs, blocker, next step.
3. `docs/RULES.md` — operating rules (PR loop, gates, security, UI).
4. `docs/LESSON.md` — reusable findings from CI/Copilot/local debugging.
5. `.claude/skills/laravel-flow-admin-shell/SKILL.md` — repo-local shell skill.
6. `.claude/skills/copilot-pr-review-loop/SKILL.md` — mandatory PR loop.
7. `.claude/skills/pre-push-self-review/SKILL.md` — pre-push checklist.

When dispatching any sub-agent, **always pass these 7 files as context**.

## Operating Rules (NON-NEGOTIABLE)

- **Stack pinned:** Laravel `^13.0`, PHP `^8.3` (CI matrix on PHP 8.3 + 8.4). Frontend: Blade + Vite + Alpine.js. Tests: PHPUnit + Orchestra Testbench + Playwright.
- **Branch model:** one `task/<macro-slug>` per macro task; one `subtask/<macro-slug>-<n>-<short-name>` per implementation slice. Subtask PR → macro branch. Macro PR → `main`.
- **No commits on macro branches** unless the user explicitly overrides. Never on `main` outside a release tag/changelog window.
- **Pre-push gates (ALL must be green locally before any push):**
  ```bash
  composer validate --strict --no-check-publish
  composer format:test
  composer analyse
  composer test
  npm run lint
  npm run build
  npm run test:e2e
  ```
- **CI gates (every PR must pass on GitHub before merge):** PHP matrix (8.3 + 8.4), frontend (Node 20 lint + build), e2e matrix (chromium + firefox + webkit). All jobs are required status checks.
- **Copilot review loop (mandatory):** `gh pr create … --reviewer copilot` (with GraphQL fallback when needed). Wait for CI + Copilot. Fix every must-fix. Loop until green + approved.
- **Documentation:** update `docs/PROGRESS.md` on every meaningful handoff; `docs/LESSON.md` on every reusable discovery; README counts must match real PHPUnit/Playwright output.
- **Security:** no secrets in UI/JSON/audit/log/exception. Mutations gated by `DashboardActionAuthorizer` (`DenyAllAuthorizer` default). Plain approval tokens never re-rendered after issuance.
- **Public surface:** consume only `Padosoft\LaravelFlow\Dashboard\*` and the documented action API. Never reference `Persistence`/`Models`/`Queue`/`Jobs`/`Console` namespaces of the engine.
- **UI:** pixel-perfect against `.design-source/project/`. Dark default. Density high. Border radius ≤ 8px. No nested cards. Every icon-only button needs `aria-label` + tooltip.

## Branch and PR Loop

Macro branches (one per task in `docs/IMPLEMENTATION_PLAN.md`):

- `task/agent-operating-system`
- `task/baseline-tooling`
- `task/design-system-shell`
- `task/read-model-adapter`
- `task/pages-overview-runs`
- `task/pages-run-detail`
- `task/pages-misc`
- `task/cmdk-search`
- `task/hardening-release`
- `task/lessons-harvest`

For each subtask:

1. Create a subtask branch from the active macro branch.
2. Implement the smallest coherent slice.
3. Run the local gates above.
4. Open a PR from the subtask branch into the macro branch with `--reviewer copilot`.
5. Wait for reported CI checks and Copilot comments.
6. Fix all red CI and actionable Copilot comments.
7. Repeat steps 5-6 until CI is green and review comments are resolved.
8. Merge the subtask PR into the macro branch (squash).
9. When the macro branch is complete, open a macro PR into `main` and run the same loop.

If `gh pr edit <PR> --add-reviewer @copilot` fails because of GitHub CLI project-scope errors or the `copilot` login does not resolve, fall back to the GraphQL `requestReviewsByLogin` mutation documented in `.claude/skills/copilot-pr-review-loop/SKILL.md`.

If a tool is unavailable, blocked, or remote CI/Copilot cannot be verified, do **not** fake completion — record the exact blocker and the next remote step in `docs/PROGRESS.md`.

## Local Gates (canonical commands)

```bash
composer validate --strict --no-check-publish
composer format:test       # Laravel Pint --test
composer analyse           # PHPStan level 8 (Larastan)
composer test              # PHPUnit Unit + Feature + Architecture suites
npm run lint               # ESLint on resources/js
npm run build              # Vite production build
npm run test:e2e           # Playwright E2E (chromium by default in dev)
```

## v0.1.0 Stability Hooks

- `Padosoft\LaravelFlowAdmin\Contracts\*` is the public extension surface (read-model adapter, authorizer, view-model factories).
- Internal namespaces (`Adapters`, `Http\Controllers`, `Support`, `ViewModels`) are subject to change between minor versions before v1.0.
- Configuration keys in `config/flow-admin.php` are part of the public contract from v0.1.0.
- Asset publishing tags: `flow-admin-config`, `flow-admin-views`, `flow-admin-assets`. Renaming requires an upgrade guide entry.
- Route names (`flow-admin.overview`, `flow-admin.runs.index`, …) are part of the public contract.
