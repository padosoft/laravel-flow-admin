# `padosoft/laravel-flow-admin` — PROGRESS

> Durable handoff log. Read this first when restarting a session.
> Per-PR Copilot/CI history lives on the PR; only durable restart state lands here.

## Now / Next / Blocked

- **Now (2026-07-14)**: Macro E (Flow Studio UI, Laravel Flow 2.0 program) in progress on macro branch `task/v2e-studio`. **E-PR0 DONE** (PR #30 + docs follow-up PR #31) and **E-PR1 DONE** (PR #33) — see "Macro E" section below for both subtasks' full detail.
- **Now (validated locally, 2026-07-14 — full gate, PHP + frontend + e2e, on `task/v2e-studio` post-E-PR1)**:
  - `composer validate --strict --no-check-publish` ✅
  - `composer format:test` ✅
  - `composer analyse` (PHPStan level 8) ✅
  - `composer test` ✅ (121 tests, 651 assertions)
  - `npm run lint` ✅
  - `npm run build` ✅
  - `npm run test:e2e` ✅ (21/21 passed, 3 visual-gated skipped)
- **Next**: E-PR2 — read-only canvas rendering (render a published `GraphDefinition`, nodes from the catalog, typed color-coded wires per the verified 6-case `PortType` legend), on a subtask branch off `task/v2e-studio`, per `docs/superpowers/plans/2026-07-14-macro-e-studio-ui.md` in the core repo.
- **Blocked**: none.

## Macro E — Flow Studio UI (in progress)

- **E-PR1 DONE** (2026-07-14): `task/v2e-01-react-island` merged into `task/v2e-studio` via PR #33 (squash, commit `0d59d0d`), 5 rounds of local Copilot CLI review + 3 rounds of PR-level review (2 CI-only iterations fixing real bugs the review didn't need to catch, 1 substantive Copilot/Codex round), all converged to zero outstanding findings. Delivered: `react`/`react-dom`/`@xyflow/react` dependencies; `resources/js/studio.jsx` (a React island proving the build/serve/mount pipeline with an empty `ReactFlowProvider`/`ReactFlow` canvas); `GET /flow/studio` page + sidebar nav entry; `AbstractManifestAssetController` (resolves Vite's `.vite/manifest.json` to serve the current content-hashed build output through package-internal routes, since Testbench's `serve` can't expose this package's own `public/vendor/flow-admin/` through its public dir) with a path-traversal containment check and RFC-7232-correct 304 semantics; `@stack('head')`/`@stack('scripts')` on the layout. **3 real CI bugs found and fixed post-review** (all recorded in `docs/LESSON.md` 2026-07-14): (1) `defaults.run.working-directory` doesn't apply to `uses:` steps — a `download-artifact` path bug; (2) a "build once instead of 3x" fix that was cosmetic (moved the build call, didn't dedupe it — real fix needed cross-job `upload-artifact`/`download-artifact`); (3) `upload-artifact`'s default `include-hidden-files: false` silently dropped `.vite/manifest.json`, breaking the Studio E2E test specifically while every other page kept passing. A stale `package-lock.json` (missing two optional transitive deps due to registry-state drift, unrelated to any `package.json` change) also broke CI's `npm ci` once and was fixed by a clean regenerate.
- **E-PR0 DONE** (2026-07-14): `task/v2e-00-hygiene` merged into `task/v2e-studio` via PR #30 (squash, commit `d4c63f5`) + docs follow-up PR #31 (squash, commit `2751815`), 6 rounds of local Copilot CLI review + 2 rounds of PR-level review, all converged to zero outstanding findings. Delivered: `composer.json` retargeted to core `dev-main` via a local `path` repository (core has no v2 tag yet); `EloquentReadModel` rewritten to route every read through core's `@api` `Dashboard\FlowDashboardReadModel` + `Contracts\DefinitionRepository` (zero raw `DB::table('flow_*')` calls); a real pre-existing bug fixed (declared step count was reading step-execution rows, not the definition's declared graph node count); plain listing/single-status filtering on runs/approvals/outbox now uses true unbounded server-side pagination (only free-text search — or, for runs, the compound `'failed'` status / a flow-prefix filter — still scans the 200-most-recent-runs bound); a real N+1 eliminated on the runs list (fixed via a small companion core PR, `padosoft/laravel-flow#90`, adding `FlowDashboardReadModel::stepCounts()`); the Claude Design "Flow Studio UI" template copied into `design/claude-design-template/`; CI's `php`/`e2e` jobs updated to checkout core as a true sibling directory.

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
| `task/v2e-studio` | `main` | **ACTIVE — Macro E (Flow Studio UI)**. E-PR0 merged (PR #30 + docs follow-up PR #31). Next subtask: E-PR1 (React island pipeline), branch off `task/v2e-studio`, e.g. `task/v2e-01-react-island`. |
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
