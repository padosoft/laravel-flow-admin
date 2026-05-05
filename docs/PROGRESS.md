# `padosoft/laravel-flow-admin` — PROGRESS

> Durable handoff log. Read this first when restarting a session.
> Per-PR Copilot/CI history lives on the PR; only durable restart state lands here.

## Now / Next / Blocked

- **Now**: Macro 1 PR loop — `subtask/agent-os-1-import-rules` PR #1 open, awaiting CI (php/frontend/e2e jobs gated by `hashFiles()` so should report success on this scaffold-only PR) and Copilot Code Review.
- **Next**: When PR #1 is green + Copilot resolved → squash-merge into `task/agent-operating-system`, then open macro PR `task/agent-operating-system` → `main`. Then start Macro 2 (`task/baseline-tooling`).
- **Blocked**: nothing.

## Active branches

| Branch | Base | Status | PR |
|--------|------|--------|----|
| `task/agent-operating-system` | `main` | open (macro) | — |
| `subtask/agent-os-1-import-rules` | `task/agent-operating-system` | PR open | https://github.com/padosoft/laravel-flow-admin/pull/1 (Copilot requested via GraphQL fallback) |

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
