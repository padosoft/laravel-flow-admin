# Changelog

All notable changes to `padosoft/laravel-flow-admin` will be documented in this file.

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
