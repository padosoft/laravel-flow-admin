# `padosoft/laravel-flow-admin` — PROGRESS

> Durable handoff log. Read this first when restarting a session.
> Per-PR Copilot/CI history lives on the PR; only durable restart state lands here.

## Now / Next / Blocked

- **Now**: Macro 1 — Agent Operating System.
  - Subtask 1.1+1.2+1.3 bundled in branch `subtask/agent-os-1-import-rules` (single PR for the whole bookkeeping macro).
- **Next**: Macro 2 — Baseline Laravel 13 Package Tooling.
- **Blocked**: nothing. CI workflow not yet active — first PR of subtask 1.1 will exercise it on this repo.

## Active branches

| Branch | Base | Status | PR |
|--------|------|--------|----|
| `task/agent-operating-system` | `main` | open (macro) | — |
| `subtask/agent-os-1-import-rules` | `task/agent-operating-system` | open (in progress) | not pushed yet |

## Macro task status

| # | Macro | Branch | State |
|---|-------|--------|-------|
| 1 | Agent Operating System | `task/agent-operating-system` | in progress |
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
5. Run `composer install && npm ci` if the lockfiles are unfamiliar.
6. Run the local gate: `composer validate --strict --no-check-publish && composer format:test && composer analyse && composer test && npm run lint && npm run build && npm run test:e2e`.
7. If the next subtask is implementation: read the corresponding §3 entry in `IMPLEMENTATION_PLAN.md`.
8. If you are mid-PR loop: run `gh pr view <N> --json state,reviewDecision,statusCheckRollup` and continue the Copilot+CI loop per `.claude/skills/copilot-pr-review-loop/SKILL.md`.
