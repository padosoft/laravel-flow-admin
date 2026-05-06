# `padosoft/laravel-flow-admin` — PROGRESS

> Durable handoff log. Read this first when restarting a session.
> Per-PR Copilot/CI history lives on the PR; only durable restart state lands here.

## Now / Next / Blocked

- **Now**: Completed local implementation sweep for Macro 4→10 scope on `subtask/read-model-2-eloquent`:
  `ReadModel` contract + adapters, all page controllers/views, runtime API (`/flow/api/search`, `/flow/api/live`), Macro 8 interactions (⌘K, polling toggle, toasts), and Macro 9 docs/release files are present in working tree.
- **Now (validated locally on 2026-05-06)**:
  - `composer validate --strict --no-check-publish` ✅
  - `composer format:test` ✅
  - `composer analyse` ✅
  - `composer test` ✅ (101 tests, 584 assertions)
  - `npm run lint` ✅
  - `npm run build` ✅
  - `npm run test:e2e` ✅ (18 passed, 3 visual-gated skipped)
- **Now**: follow-up hardening fixes applied on `subtask/read-model-2-eloquent`:
  - `ArrayReadModel` now merges defaults + disk fixture + constructor fixture (constructor fixture has precedence).
  - `Authorize` now logs sanitized `actor/context` and obfuscated token hashes.
  - `EloquentReadModel` removed `Padosoft\LaravelFlow\FlowRun` dependency and now uses local status constants.
- **Next**: push latest hardening commit on PR #19, then complete mandatory Copilot review gate and merge subtask into `task/read-model-adapter`.
- **Blocked**: none locally; remote step remains waiting for Copilot review activity on PR #19.

## Macro 2 — DONE ✅

Squash-merged onto `main` at SHA `1f5d0ed` (macro PR #11).

| PR | Type | Squash SHA |
|----|------|------------|
| #9 | subtask 2.1+2.2 (composer / phpunit / pint / phpstan / ServiceProvider skeleton + 13 tests) | `c83b7d9` on `task/baseline-tooling` |
| #10 | subtask 2.3 (Vite + Alpine + ESLint 9 + Playwright 1.59 + testbench.yaml + 23 tests / 34 assertions) | `34d3ee0` on `task/baseline-tooling` |
| #12 | subtask review hotfix (7 Copilot threads on macro PR) | `c2d6ed1` on `task/baseline-tooling` |
| #11 | macro → main | `1f5d0ed` on `main` |

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
| `task/read-model-adapter` | `main` | open (macro) — Macro 4 |
| `subtask/read-model-1-viewmodels` | `task/read-model-adapter` | done (PR #18 merged) |
| `subtask/read-model-2-eloquent` | `task/read-model-adapter` | in progress — `ReadModel` contract, `EloquentReadModel`, `ArrayReadModel`, `ActionAuthorizer` + tests |

## Macro task status

| # | Macro | Branch | State |
|---|-------|--------|-------|
| 1 | Agent Operating System | `task/agent-operating-system` | ✅ merged on main `f32ac2f` |
| 2 | Baseline Tooling Laravel 13 | `task/baseline-tooling` | ✅ merged on main `1f5d0ed` |
| 3 | Design System & Layout Shell | `task/design-system-shell` | ✅ merged on main `617e427` |
| 4 | Read Model Adapter | `task/read-model-adapter` | implementation complete locally, PR loop pending |
| 5 | Pages — Overview & Runs | `task/pages-overview-runs` | implementation complete locally, PR loop pending |
| 6 | Pages — Run Detail | `task/pages-run-detail` | implementation complete locally, PR loop pending |
| 7 | Pages — Approvals/Outbox/Definitions/Settings | `task/pages-misc` | implementation complete locally, PR loop pending |
| 8 | ⌘K Palette + Auto-refresh + Toasts | `task/cmdk-search` | implementation complete locally, PR loop pending |
| 9 | Hardening, README, Release | `task/hardening-release` | docs/release artifacts complete locally; remote release loop pending |
| 10 | Harvest LESSON.md → rules/skills | `task/lessons-harvest` | lesson harvest file added locally; PR loop pending |

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
