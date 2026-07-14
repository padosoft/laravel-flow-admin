# `padosoft/laravel-flow-admin` — PROGRESS

> Durable handoff log. Read this first when restarting a session.
> Per-PR Copilot/CI history lives on the PR; only durable restart state lands here.

## Now / Next / Blocked

- **Now (2026-07-14)**: Macro E (Flow Studio UI, Laravel Flow 2.0 program) started. **E-PR0 DONE**: `task/v2e-00-hygiene` merged into macro branch `task/v2e-studio` via PR #30 (squash, commit `d4c63f5`), 6 rounds of local Copilot CLI review + 2 rounds of PR-level Copilot/Codex review, all converged to zero outstanding findings. Delivered: `composer.json` retargeted to core `dev-main` via a local `path` repository (core has no v2 tag yet); `EloquentReadModel` rewritten to route every read through core's `@api` `Dashboard\FlowDashboardReadModel` + `Contracts\DefinitionRepository` (zero raw `DB::table('flow_*')` calls); a real pre-existing bug fixed (declared step count was reading step-execution rows, not the definition's declared graph node count); plain listing/single-status filtering on runs/approvals/outbox now uses true unbounded server-side pagination (only free-text search — or, for runs, the compound `'failed'` status / a flow-prefix filter — still scans the 200-most-recent-runs bound, since core's exact-match-only filters can't express those queries server-side); a real N+1 eliminated on the runs list (was one `findRun()` call — 5 queries + full detail hydration — per row; fixed via a small companion core PR, `padosoft/laravel-flow#90`, adding `FlowDashboardReadModel::stepCounts()`); the Claude Design "Flow Studio UI" template copied into `design/claude-design-template/` (separate from the pre-existing `.design-source/project/`, the already-implemented v1 panel design); CI's `php`/`e2e` jobs updated to checkout core as a true sibling directory so the path-repo dependency resolves on GitHub Actions.
- **Now (validated locally, 2026-07-14)**:
  - `composer validate --strict --no-check-publish` ✅
  - `composer format:test` ✅
  - `composer analyse` (PHPStan level 8) ✅
  - `composer test` ✅ (111 tests, 606 assertions)
- **Next**: E-PR1 — React island pipeline (Vite build, mount point, asset publishing, `@xyflow/react` dependency), on a subtask branch off `task/v2e-studio`, per `docs/superpowers/plans/2026-07-14-macro-e-studio-ui.md` in the core repo.
- **Blocked**: none.

### Prior state (pre-Macro-E, archived)

- Roadmap implementation merged to `main` via macro PR #20 (`task/read-model-adapter` → `main`, merge SHA `2c80d11`); validated locally on 2026-05-06 at 101 tests / 584 assertions, `npm run test:e2e` 18 passed / 3 visual-gated skipped; PR #19 (subtask) and PR #20 (macro) both merged with all required CI checks green. Publishing release tags was the next step before Macro E's `dev-main` retarget superseded it (`padosoft/laravel-flow` has no v2 tag yet, so a new admin release waits for that).

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
| `main` | n/a | active release branch |
| `task/read-model-adapter` | `main` | merged via PR #20 |
| `subtask/read-model-2-eloquent` | `task/read-model-adapter` | merged via PR #19 |

## Macro task status

| # | Macro | Branch | State |
|---|-------|--------|-------|
| 1 | Agent Operating System | `task/agent-operating-system` | ✅ merged on main `f32ac2f` |
| 2 | Baseline Tooling Laravel 13 | `task/baseline-tooling` | ✅ merged on main `1f5d0ed` |
| 3 | Design System & Layout Shell | `task/design-system-shell` | ✅ merged on main `617e427` |
| 4 | Read Model Adapter | `task/read-model-adapter` | ✅ merged on main `2c80d11` |
| 5 | Pages — Overview & Runs | `task/pages-overview-runs` | ✅ delivered in `2c80d11` |
| 6 | Pages — Run Detail | `task/pages-run-detail` | ✅ delivered in `2c80d11` |
| 7 | Pages — Approvals/Outbox/Definitions/Settings | `task/pages-misc` | ✅ delivered in `2c80d11` |
| 8 | ⌘K Palette + Auto-refresh + Toasts | `task/cmdk-search` | ✅ delivered in `2c80d11` |
| 9 | Hardening, README, Release | `task/hardening-release` | ✅ docs/release artifacts merged |
| 10 | Harvest LESSON.md → rules/skills | `task/lessons-harvest` | ✅ lesson harvest merged |

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
