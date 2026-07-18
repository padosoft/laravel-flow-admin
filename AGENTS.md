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
- **Public surface:** consume only `Padosoft\LaravelFlow\Dashboard\*`, `Padosoft\LaravelFlow\Contracts\DefinitionRepository`, `Padosoft\LaravelFlow\Node\NodeRegistry`/`NodeDefinition`/`Attributes\*`/`PortType`, `Padosoft\LaravelFlow\Graph\GraphSerializer`/`GraphValidator`/`StoredDefinition`/`Exceptions\InvalidGraphException` (the named exceptions), and `Padosoft\LaravelFlow\Executor\DryRun\DryRunPlanner`/`ExecutionPlan`/`CostEstimate` — all `@api`-stable, needed for declared graph/step-count reads, the Studio canvas's node catalog, the Studio editor's structural/semantic graph validation on save (E-PR3), and the Studio editor's static dry-run execution-plan + cost estimate (E-PR7), none of which `Dashboard\*` has a primitive for — plus the documented action API. Never reference `Persistence`/`Models`/`Queue`/`Jobs`/`Console`, or any `Executor\*` namespace OTHER than the named `Executor\DryRun\*` `@api` classes, of the engine.
- **AI pack surface (optional dep `padosoft/laravel-flow-ai`):** consume only its `@api` classes — `Builder\FlowBuilderService`/`FlowBuilderResult`, `Advisor\FlowAdvisor`/`Suggestion`/`Finding`/`Analyzer`, `Contracts\LlmClient`, and the `Llm\LlmRequest`/`LlmResponse` value objects — for the Studio "Build with AI" endpoint (E-PR8a) and the advisor suggestions inbox (E-PR8b). NOTE: `FlowAdvisor` cannot auto-wire (its constructor takes non-injectable arrays), so it resolves only via the AI pack's own provider — the served E2E app registers that provider in `testbench.yaml`. The advisor scan authorizes an ALL-FLOWS action, so it calls `ActionAuthorizer::canEditDefinition('')` with an EMPTY flow name (documented on that contract): a host authorizer scoping edits per-flow must special-case `''`, never treat it as an unscoped allow. Never bind or reference its `Guardrails\*`, `Llm\Drivers\*`, `Mcp\*`, or `Nodes\*` internals. The dependency is optional: gate AI features behind `class_exists(FlowBuilderService::class)` so the admin still boots without it — but gate the UI affordance and the container binding, NOT the route registration. The AI route stays registered unconditionally and does its `class_exists()` 404 INSIDE the authorization gate, so an unauthorized caller always gets a uniform 403 regardless of package presence (gating the route itself would 404-at-router when absent vs 403 when present, leaking package-presence to anonymous callers). The dev/E2E `FakeLlmClient` (in `src/Ai/`) is bound only via `config('flow-admin.ai.fake')` and must hard-refuse in the production environment.
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

## Program lessons (Macro E — Studio UI + working mutations, folded from docs/LESSON.md)

Durable admin-specific learnings. Apply on every new page/mutation:

- **Mutation/authoring controllers stay thin and gated.** EVERY mutation/authoring endpoint (approve/reject-by-hash, cancel, replay, redeliver, dry-run, ai-build, advisor-scan) wraps its body in `Support\Authorize::action('<action>', …)` (deny-by-default: the shipped `DenyAllAuthorizer` denies all), and compute/billable or arbitrary-input endpoints also carry a route `throttle:` with a dedicated key prefix. The E-PR6 run/approval/outbox mutations (approve/reject/cancel/replay/redeliver) additionally return the `{success,message,data}` contract via the SHARED `Support\FlowMutation::run()` exception→HTTP mapper: `FlowInputException`→422, `PersistenceUnavailableException`→503 (caught BEFORE its `FlowExecutionException` parent — covers approvals AND cancel/replay/redeliver outages), `FlowExecutionException`→409 (curated message surfaced — only ids already in the URL), unknown `Throwable`→sanitized 500 (class-only log). The Studio `dryRun()`/`aiBuild()` and `AdvisorController::scan()` are gated the same way but shape their OWN endpoint-specific JSON (`{flow,plan,cost}`, `{success,message,graph}`, `{success,message,suggestions}`) with their own sanitized-500 handling — they do NOT route through `FlowMutation`.
- **UI eligibility flags MUST mirror the endpoint's route + controller constraints exactly**, or the button renders then 404s / is a guaranteed no-op. `OutboxRow::canRedeliver` = `status==='failed'` AND canonical numeric id (`(string)((int)$id)===$id`, ≤18 digits, >0 — matching the `[0-9]{1,18}` route + int cast). `ApprovalCard::canDecide()` = pending AND a 64-hex hash (matching the `[A-Fa-f0-9]{64}` `{tokenHash}` route). `RunDetailViewModel::canCancel()/canReplay()` = active vs terminal on the public status slug.
- **A new core `@api` field is invisible until every hop forwards it**: core `Dashboard\*` DTO → admin `Contracts\Dto\*` → BOTH adapters' `map*()` (Eloquent + Array) → `ViewModels\*` → blade. Add a read-model test asserting it's populated end-to-end (the approval `tokenHash` seam was dead-on-arrival until threaded through all five).
- **Server messages in the DOM go through `textContent`, never `innerHTML`.** The shared `[data-flow-action]` runner + `window.flowAdminToast` render server-provided messages (which can carry a run id/flow name from the URL) — build the toast with `textContent` so a `<`/`>` can't inject. Destructive actions (reject/cancel) carry `data-confirm`.
- **CI E2E single-browser flake = re-run the shard, not code.** The 3-browser matrix flakes a DIFFERENT single browser on most pushes (single-threaded `testbench serve` stalls → timeout-then-cascade). `gh run rerun <id> --failed`; confirm with a full local `npm run test:e2e` across all three browsers.
- **`ActionAuthorizer::canViewRuns()/canViewRunDetail()/canViewKpis()` are RESERVED, unenforced by design** — enforcing views under the deny-all default would break the documented "browse on day 1" value prop. Documented as reserved; do not wire (breaks default UX) or remove (breaking interface change).
- **The E2E `array` adapter can't round-trip an engine mutation** (no real persisted state) — prove engine round-trips in PHPUnit Feature tests (real persistence via `MigratesFlowTables` + a file lock store + `$engine->execute()`), and the UI/UX layer (eligibility rendering, confirm gating, the fetch fires) in Playwright.

## v0.1.0 Stability Hooks

- `Padosoft\LaravelFlowAdmin\Contracts\*` is the public extension surface (read-model adapter, authorizer, view-model factories).
- Internal namespaces (`Adapters`, `Http\Controllers`, `Support`, `ViewModels`) are subject to change between minor versions before v1.0.
- Configuration keys in `config/flow-admin.php` are part of the public contract from v0.1.0.
- Asset publishing tags: `flow-admin-config`, `flow-admin-views`, `flow-admin-assets`. Renaming requires an upgrade guide entry.
- Route names (`flow-admin.overview`, `flow-admin.runs.index`, …) are part of the public contract.
