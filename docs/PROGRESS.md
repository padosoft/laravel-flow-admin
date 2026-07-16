# `padosoft/laravel-flow-admin` — PROGRESS

> Durable handoff log. Read this first when restarting a session.
> Per-PR Copilot/CI history lives on the PR; only durable restart state lands here.

## Now / Next / Blocked

- **Now (2026-07-17)**: **E-PR3 MERGED** (PR #36 squash-merged into `task/v2e-studio` as `8a51243`, branch deleted). All 6 CI checks green (PHP 8.3/8.4, Frontend, E2E chromium/firefox/webkit), all 9 Copilot/Codex review threads resolved. **Next: E-PR4 (Versioning UI)** — publish (immutability confirmation modal) + version list + visual diff, on a subtask branch off `task/v2e-studio`. Remaining Macro E: E-PR4→E-PR8, then Gate G3, then Macro G (release). E-PR5 is gated on a small core PR adding `cache_hit` to `Dashboard\StepSummary`.
- **E-PR3 outcome (the hard part was CI, not the feature)**: the editor itself (palette, drag&drop, typed-connection validation, inspector, save-as-draft, node delete with edge pruning) landed cleanly; the multi-round fight was the E2E "save as draft" 500 that was green locally, red in CI. Root cause (confirmed only after adding a CI step that dumps the testbench `laravel.log` on failure): the `testbench serve` app wrote to testbench's default `testing`/`:memory:` connection — empty per request → `no such table: flow_definitions` — because `artisan serve` runs the `php -S` worker with CWD = the skeleton's `public_path()` and drops non-passthrough env, so the serve script's process-env `DB_*` never reached it. Fix: force `DB_CONNECTION: sqlite` in `testbench.yaml` and migrate the skeleton's OWN `database_path('database.sqlite')` (both processes boot the `@testbench` skeleton → identical absolute path). WAL journal mode + a `dragConnect` boundingBox guard + a `.toPass()` retry on the flaky react-flow node-selection click also landed. Two false starts along the way (a WAL-lock hypothesis, then a relative-`DB_DATABASE` path that resolves against the wrong CWD) were both caught by local Copilot review before merge. See `docs/LESSON.md` 2026-07-16 entry.

<!-- superseded detail below retained for history -->
- **(historical) 2026-07-16**: **E-PR3 (canvas editor) PR #36** (`task/v2e-03-canvas-editor` → `task/v2e-studio`) — fixing red CI E2E jobs. **Confirmed root cause via a new "dump laravel.log on E2E failure" CI step**: the `testbench serve` app's save-as-draft write ran against testbench's default `testing`/`:memory:` connection (empty → `no such table: flow_definitions` → 500), NOT the persistent SQLite file the serve script migrated — because the `php -S` grandchild did not inherit the serve script's process env on CI's Ubuntu runner (green locally, where it did). **Fix**: force `DB_CONNECTION: sqlite` in `testbench.yaml`'s `env:` block (read by the served app itself; does NOT leak into the phpunit suite), and migrate the skeleton's OWN `database_path('database.sqlite')` from `serve-testbench.mjs` — both the migrate process and the served app boot the `@testbench` skeleton so both compute the identical absolute path via the connection's `env('DB_DATABASE', database_path(...))` default, with no relative-path/CWD/env-inheritance dependency (a first attempt using a relative `DB_DATABASE` in testbench.yaml was WRONG — `artisan serve` runs the `php -S` worker with CWD = the skeleton's `public_path()`, caught by local Copilot review round 7). Verified by inspecting the skeleton `database/database.sqlite` after an E2E run: the persisted `OrderCheckoutFlow`/`studio-e2e-delete-node-regression` draft rows prove the served app wrote to that exact absolute path. An earlier WAL-journal hypothesis (`scripts/enable-wal.php`) was wrong-but-kept as genuine defense now that writes actually hit the shared file concurrently with the `/flow/api/live` poll reads. Also addressed all 4 PR-review comments (3 Copilot + 1 Codex P2): tempnam leak in `MigratesFlowTables`, undefined `--border-default`/`--text-primary` CSS tokens (→ `--border`/`--text`), guaranteed test DB cleanup via `tearDown()`, `recomputeEdgeValidity()` so a fan-in-invalid wire recovers after its conflicting edge is deleted. Local Copilot review NO_FINDINGS on the round-6 code (round 6a caught a stray NUL byte in a fan-in key, fixed).
- **Local gate (2026-07-16, PHP 8.5 fresh server)**: Pint ✅, PHPStan level 8 ✅, PHPUnit ✅ (147 tests / 769 assertions, unaffected by testbench.yaml DB env), ESLint ✅, build ✅, Playwright studio-editor chromium 7/7. **Next remote step**: push the connection fix, re-verify CI E2E green on PR #36 head (this is the real test — local always passed because it inherited the env), re-request Copilot review, resolve comments, merge into `task/v2e-studio`. Earlier: **E-PR0 DONE** (PR #30 + docs #31), **E-PR1 DONE** (PR #33), **E-PR2 DONE** (PR #35) — see "Macro E" section below.
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
| `task/v2e-studio` | `main` | **ACTIVE — Macro E (Flow Studio UI)**. E-PR0 merged (PR #30 + docs follow-up PR #31); E-PR1 merged (PR #33 + docs follow-up PR #34). Next subtask: E-PR2 (read-only canvas rendering), branch off `task/v2e-studio`, e.g. `task/v2e-02-canvas-readonly`. |
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
