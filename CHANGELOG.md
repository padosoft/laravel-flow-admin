# Changelog

All notable changes to `padosoft/laravel-flow-admin` will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.2] - 2026-07-18

### Fixed
- **E2E CI reliability (definitive fix)** — completes the `v2.0.1` concurrency change. Root-caused the residual single-browser-shard flake from a CI shard log: the server served fast (~0.04ms) until it went **silent mid-run**, then every request got `NS_ERROR_CONNECTION_REFUSED` with **no PHP error logged** — a **silent segfault** of PHP's *experimental* `PHP_CLI_SERVER_WORKERS` forking built-in server (stressed by the constant `/flow/api/live` poll + the run-monitor 2.5s poll + the browser aborting in-flight requests as it navigates). Because Laravel's `ServeCommand` never restarts a *crashed* server (its loop only restarts on a `.env` change), the port stayed dead and the whole shard cascaded into timeouts. `scripts/serve-testbench.mjs` now **supervises the serve process and respawns it on any unexpected exit**, turning a fatal 30s-then-cascade crash into a sub-second port-rebind blip that Playwright's per-test retries absorb. Dev/CI harness only — no change to the shipped package or its runtime.

## [2.0.1] - 2026-07-18

### Fixed
- **E2E CI reliability**: the Testbench serve app now pre-forks `PHP_CLI_SERVER_WORKERS` workers (default 4, overridable) **with `--no-reload`** (required — Laravel's `ServeCommand` otherwise silently falls back to a single worker) so a slow mutation round-trip no longer blocks the single-threaded `php -S` and cascades a whole browser shard into timeouts (the recurring "a different single browser flakes each run" symptom). POSIX-only; ignored on Windows.
- **Deterministic KPI window test**: `EloquentReadModel` now derives its KPI window edges from an injectable clock, so a run seeded exactly at a window boundary is classified against the same instant the query uses — removing the microsecond race that made `test_kpi_window_boundary_does_not_double_count_a_run` flaky.

## [2.0.0] - 2026-07-18

The **Flow Studio** release — a full operator console for the Flow 2.0 engine. Requires [`padosoft/laravel-flow`](https://github.com/padosoft/laravel-flow) `^2.0`; the AI features are unlocked by the optional [`padosoft/laravel-flow-ai`](https://github.com/padosoft/laravel-flow-ai) `^1.0`.

### Added

- **Flow Studio canvas** (React + `@xyflow/react`): a read-only view of a flow's published graph and a full drag-and-drop **editor** (palette from the node catalog, typed-connection validation, node inspector, save-as-draft), gated by `ActionAuthorizer::canEditDefinition()`. Node `config` is redacted on the read-only endpoint and only exposed (unredacted) behind the edit gate.
- **Flow versioning UI**: version list (draft/published/archived), one-click **Publish** behind an immutability confirmation, and a server-side node-level **visual diff** between any two versions.
- **Live run monitor**: subscribes to core's private broadcast channel via Laravel Echo when broadcasting is enabled, or **falls back to polling**; renders all nine `NodeState` colors + a cache-hit badge.
- **Working mutations**: **Approve / Reject** an approval by its token hash (`Flow::resumeByHash`/`rejectByHash`), **Cancel** / **Replay** a run (`Flow::cancel`/`replay`), and **Redeliver** a failed webhook (`Flow::redeliverWebhook`) — every mutation deny-by-default through your `ActionAuthorizer` and mapped to a uniform `{success,message,data}` JSON contract (`PersistenceUnavailableException`→503, state conflicts→409).
- **Dry-run planner** (Kahn-wave plan + cost estimate, zero rows written), **Build with AI** (natural-language → validated draft graph via `padosoft/laravel-flow-ai`), and the **Flow Advisor inbox** (deterministic run-history suggestions). All three `edit_definition`-gated and rate-limited.
- Overview dashboard (KPIs, sparklines, queue health), runs index + detail, approvals inbox, webhook outbox, definitions, settings; ⌘K command palette; pixel-perfect dark + light themes; deny-by-default `ActionAuthorizer` (`DenyAllAuthorizer`) with `AllowAllAuthorizer`/`FakeLlmClient` hard-refused in production.

### Changed

- Requires core `^2.0` (was the v1 engine); consumes core's `@api` read model + the new dashboard mutation seams.

## [1.0.0] - 2026-05-06

Stable promotion of `0.1.1` (same commit) — the first SemVer-covered release of the read-only admin panel.

## [0.1.1] - 2026-05-06

### Added
- Wow-level community README (screenshots, badges, step-by-step setup).

### Changed
- CI/build dependency bumps (esbuild/vite, `actions/*`).

## [0.1.0] - 2026-05-06

### Added
- Laravel 13 package skeleton, service provider, config and routes.
- Design-system shell (sidebar, topbar, breadcrumb, dark/light theme).
- Read model contract with `eloquent` and `array` adapters.
- Overview, runs list, run detail, approvals, outbox, definitions, settings pages.
- Macro 8 runtime: command palette (`Ctrl/Cmd+K`), live polling endpoint, toast notifications.
- PHPUnit, PHPStan, Pint, Playwright and CI-ready scripts.

### Changed
- Overview page moved from placeholder heading to data-driven dashboard.
- E2E smoke now validates `Overview` title (not legacy `Flow Admin` stub).

### Security
- Default mutation authorizer remains deny-by-default.
- Theme endpoint validates input and same-host redirect safety.
