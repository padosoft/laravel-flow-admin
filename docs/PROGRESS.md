# `padosoft/laravel-flow-admin` — PROGRESS

> Durable handoff log. Read this first when restarting a session.
> Per-PR Copilot/CI history lives on the PR; only durable restart state lands here.

## Now / Next / Blocked

- **Now**: Macro 2 (`task/baseline-tooling`) — subtask `baseline-1-composer-phpunit-pint-phpstan` starting.
- **Next**: After Macro 2 → Macro 3 Design System.
- **Blocked**: nothing.

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
| `subtask/baseline-1-composer-phpunit-pint-phpstan` | `task/baseline-tooling` | in progress |

## Macro task status

| # | Macro | Branch | State |
|---|-------|--------|-------|
| 1 | Agent Operating System | `task/agent-operating-system` | ✅ merged on main `f32ac2f` |
| 2 | Baseline Tooling Laravel 13 | `task/baseline-tooling` | not started |
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
