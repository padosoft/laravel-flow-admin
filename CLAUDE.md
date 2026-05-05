# Claude Instructions — `padosoft/laravel-flow-admin`

This file is the Claude-compatible entrypoint for the repository. It mirrors `AGENTS.md`.

## Read First

1. `docs/IMPLEMENTATION_PLAN.md`
2. `docs/PROGRESS.md`
3. `docs/RULES.md`
4. `docs/LESSON.md`
5. `.claude/skills/laravel-flow-admin-shell/SKILL.md`
6. `.claude/skills/copilot-pr-review-loop/SKILL.md`
7. `.claude/skills/pre-push-self-review/SKILL.md`

When dispatching a sub-agent, pass all 7 files as context. The shell skill explains the package, the design source, and which other skills to invoke for what.

## Non-Negotiable Rules

- Work through macro branches and subtask PRs as listed in `docs/IMPLEMENTATION_PLAN.md`. No direct commits on macro branches or `main` outside an explicit release window.
- Run the full local gate before every push: `composer validate --strict --no-check-publish`, `composer format:test`, `composer analyse`, `composer test`, `npm run lint`, `npm run build`, `npm run test:e2e`.
- Open every PR with `--reviewer copilot` (with GraphQL fallback if the CLI flag fails). Wait for the Copilot review and CI to converge.
- Merge only after every required CI job is green AND Copilot has approved or has zero must-fix outstanding.
- Update `docs/PROGRESS.md` on every meaningful handoff; update `docs/LESSON.md` whenever a Copilot comment / CI failure / debugging session yields a reusable insight.
- README test/assertion counts and Playwright scenario counts must match real tool output (skill `test-count-readme-sync`).
- Pixel-perfect against `.design-source/laravel-flow-admin/project/`. Dark theme is the default; light theme must work too.
- Mutations (resume, reject, replay, cancel, retry webhook) go through `DashboardActionAuthorizer` (default `DenyAllAuthorizer`). Never bypass.
- Never reference `Padosoft\LaravelFlow\{Persistence,Models,Queue,Jobs,Console}\*` from this package — they are `@internal`.

## CI and Status Checks

Workflow `.github/workflows/ci.yml` runs on PRs targeting `main` and `task/**`, plus pushes to `main`.

Required jobs (must all pass before merge):

- `php` — matrix `php: ['8.3','8.4']` running validate / Pint / PHPStan / PHPUnit.
- `frontend` — Node 20: `npm ci`, `npm run lint`, `npm run build`.
- `e2e` — Node 20 matrix `browser: ['chromium','firefox','webkit']`: `npm ci`, `npx playwright install --with-deps`, `npm run test:e2e -- --project=$browser` against the Testbench server.

Do not add `task/**` to push triggers — both macro and subtask branches use the `task/`/`subtask/` prefix and we trigger CI on PRs only for those.

## Skills

Always invoke the relevant skill before acting:

- `.claude/skills/laravel-flow-admin-shell/SKILL.md` — orientation, what the package is, where the design lives, which other skills to call.
- `.claude/skills/copilot-pr-review-loop/SKILL.md` — mandatory loop after every push to a PR branch.
- `.claude/skills/pre-push-self-review/SKILL.md` — checklist before every `git push`.
- `.claude/skills/playwright-enterprise-tester/SKILL.md` — when adding or fixing a Playwright scenario.
- `.claude/skills/create-admin-interface/SKILL.md` (orchestrator) → `admin-interface-backend` + `admin-interface-frontend` for new admin pages.
- `.claude/skills/test-count-readme-sync/SKILL.md` before pushing a branch that touches tests or README counts.
- `.claude/skills/review-pr-comments/SKILL.md` after Copilot posts a review.

## v0.1.0 Public Contract

- `Padosoft\LaravelFlowAdmin\Contracts\*` interfaces (read-model adapter, authorizer, view-model factories) are the public extension surface.
- `config/flow-admin.php` keys, asset publish tags (`flow-admin-config`, `flow-admin-views`, `flow-admin-assets`), and route names (`flow-admin.*`) are public contract.
- Internal namespaces (`Adapters`, `Http\Controllers`, `Support`, `ViewModels`) may change between minor versions until v1.0.

See `docs/IMPLEMENTATION_PLAN.md` §4 for the full Definition of Done.
