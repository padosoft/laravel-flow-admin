# `padosoft/laravel-flow-admin` — PROGRESS

> Durable handoff log. Read this first when restarting a session.
> Per-PR Copilot/CI history lives on the PR; only durable restart state lands here.

## Now / Next / Blocked

- **Now**: Macro 2 (`task/baseline-tooling`) — subtask 2.3 in progress on
  `subtask/baseline-2-vite-alpine-eslint-playwright`. Subtask 2.1+2.2 (PR #9)
  squash-merged onto `task/baseline-tooling` at SHA `c83b7d9`.
- **Next**:
  1. Finish subtask 2.3 — push branch, open PR with `--reviewer copilot`
     (GraphQL fallback as in `.claude/skills/copilot-pr-review-loop/SKILL.md`),
     loop Copilot+CI, merge squash.
  2. Macro 2 PR `task/baseline-tooling` → `main`. Loop. Merge.
  3. Macros 3 → 10 per `docs/IMPLEMENTATION_PLAN.md` §3 (Design System,
     Read Model, Overview+Runs, Run Detail, Approvals/Outbox/Definitions/Settings,
     CmdK+AutoRefresh, README WOW + v0.1.0 release, LESSON harvest).
- **Blocked**: nothing.

### Subtask 2.3 — local validation status (handoff)

Local PowerShell gate (Windows):

| Gate | Result |
|------|--------|
| `composer validate --strict --no-check-publish` | ✅ green |
| `composer format:test` (Pint) | ✅ green |
| `composer analyse` (PHPStan level 8) | ✅ green |
| `composer test` (PHPUnit) | ✅ green — 13 tests / 24 assertions |
| `npm run lint` (ESLint flat config) | ✅ green |
| `npm run build` (Vite 5) | ✅ green — manifest + admin + styles in `public/vendor/flow-admin/` |
| `npm run test:e2e` chromium | ⚠️ **local Windows**: Testbench serves `/flow` 200 but Playwright `webServer.url` polling stays in a 1-second-per-poll loop instead of hitting a normal Playwright sub-second poll. Logs were truncated mid-run (>30s of identical `/flow` ~0.18ms log lines from testbench). Two hypotheses to confirm in the next session: (a) Windows PATHEXT/quoting still misbehaves under cmd.exe and Playwright never gets a clean response code; (b) testbench serve emits console output that Playwright is interpreting as healthcheck failure. **CI on Ubuntu uses the POSIX branch of `scripts/serve-testbench.mjs` (no cmd.exe wrapper) and should be much cleaner.** Push and let CI verify before further local debugging. |

Files added/changed in this subtask:

- `package.json` (Vite 5 / Alpine 3 / ESLint 9 / Playwright 1.49 — root level so CI `frontend` job picks it up)
- `package-lock.json` (committed for reproducible `npm ci`)
- `eslint.config.js` (flat config — required by ESLint 9)
- `vite.config.js` (input: `resources/js/admin.js` + `resources/css/admin.css`, output: `public/vendor/flow-admin/`)
- `playwright.config.js` (chromium/firefox/webkit projects + `webServer` block invoking `node scripts/serve-testbench.mjs`)
- `resources/js/admin.js` (Alpine bootstrap)
- `resources/css/admin.css` (token-system stub — full port lands in Macro 3)
- `scripts/serve-testbench.mjs` (cross-platform launcher for `php vendor/bin/testbench serve` — POSIX branch is `spawn('php', …)`; Windows branch is `spawn('cmd.exe', ['/d','/s','/c', cmdLine])` with quoted testbench path because the repo path contains a space)
- `tests/e2e/smoke.spec.js` (asserts `GET /flow` → 200 + `<h1>` contains "Flow Admin")
- `config/flow-admin.php` — `middleware` is now env-driven via `FLOW_ADMIN_MIDDLEWARE` (default `web,auth`), so the E2E webServer can boot without `auth` middleware. Default still `['web','auth']`. Tests still green (the existing `test_config_is_loaded` already asserts only key presence, not env-driven values).

## Macro 1 — DONE ✅

Squash-merged onto `main` at SHA `f32ac2f` (macro PR #2).

| PR | Type | Squash SHA |
|----|------|------------|
| #1 | subtask (rules/skills/agents/docs/CI scaffold) | `2c6f478` on `task/agent-operating-system` |
| #3 | subtask hotfix (design-path fix in 7 entrypoints) | `e9c8194` on `task/agent-operating-system` |
| #4 | subtask hotfix (6 genuine Copilot issues: PROGRESS/CI/plan/PII/README) | `d4a2e49` on `task/agent-operating-system` |
| #2 | macro → main | `f32ac2f` on `main` |

## Active branches

| Branch | Base | Status |
|--------|------|--------|
| `task/baseline-tooling` | `main` | open (macro) — Macro 2 |
| `subtask/baseline-2-vite-alpine-eslint-playwright` | `task/baseline-tooling` | in progress (subtask 2.3 frontend tooling) |

## Macro task status

| # | Macro | Branch | State |
|---|-------|--------|-------|
| 1 | Agent Operating System | `task/agent-operating-system` | ✅ merged on main `f32ac2f` |
| 2 | Baseline Tooling Laravel 13 | `task/baseline-tooling` | in progress — subtask 2.1+2.2 merged (`c83b7d9`); subtask 2.3 (frontend) pushing |
| 3 | Design System & Layout Shell | `task/design-system-shell` | not started |
| 4 | Read Model Adapter | `task/read-model-adapter` | not started |
| 5 | Pages — Overview & Runs | `task/pages-overview-runs` | not started |
| 6 | Pages — Run Detail | `task/pages-run-detail` | not started |
| 7 | Pages — Approvals/Outbox/Definitions/Settings | `task/pages-misc` | not started |
| 8 | ⌘K Palette + Auto-refresh + Toasts | `task/cmdk-search` | not started |
| 9 | Hardening, README, Release | `task/hardening-release` | not started |
| 10 | Harvest LESSON.md → rules/skills | `task/lessons-harvest` | not started |

## Restart steps

If you re-enter this repo from a cold start:

1. Open `docs/IMPLEMENTATION_PLAN.md` — confirm the canonical plan is unchanged.
2. Open this file (`docs/PROGRESS.md`) — find the Active branches table.
3. `git fetch --all && git switch <branch from table>`.
4. `git status` — confirm clean working tree.
5. If `composer.json` exists: `composer install`. If `package.json` exists: `npm ci`. (Not present yet during Macro 1 scaffold — skip if absent.)
6. Run the relevant local gates (skip commands for tools not yet present): `composer validate --strict --no-check-publish && composer format:test && composer analyse && composer test` (when `composer.json` exists) + `npm run lint && npm run build && npm run test:e2e` (when `package.json` and `playwright.config.js` exist).
7. If the next subtask is implementation: read the corresponding §3 entry in `IMPLEMENTATION_PLAN.md`.
8. If you are mid-PR loop: run `gh pr view <N> --json state,reviewDecision,statusCheckRollup` and continue the Copilot+CI loop per `.claude/skills/copilot-pr-review-loop/SKILL.md`.
