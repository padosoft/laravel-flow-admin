# Changelog

All notable changes to `padosoft/laravel-flow-admin` will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
