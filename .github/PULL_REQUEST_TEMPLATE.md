# PR — `padosoft/laravel-flow-admin`

> Fill the sections below before requesting review. Empty sections block merge.

## Scope

- **Macro task**: <!-- e.g. Macro 3 — Design System & Layout Shell -->
- **Subtask**: <!-- e.g. 3.1 — Port styles.css with design tokens -->
- **Plan reference**: `docs/IMPLEMENTATION_PLAN.md` §3, subtask `<id>`.

## Summary

<!-- What changed and WHY in 2-4 bullet points. The "what" should be self-evident from the diff; the "why" should not. -->

-
-

## Local gates run (all green)

- [ ] `composer validate --strict --no-check-publish`
- [ ] `composer format:test`
- [ ] `composer analyse`
- [ ] `composer test`
- [ ] `npm run lint`
- [ ] `npm run build`
- [ ] `npm run test:e2e`

## Tests added/updated

- **Feature/Unit (PHPUnit)**: <!-- list new test classes/methods -->
- **Architecture**: <!-- list new architecture pins -->
- **Playwright (E2E)**: <!-- list new specs and scenarios -->

## UI changes (if any)

- [ ] Pixel-perfect against `.design-source/project/` reference.
- [ ] Dark theme + light theme both verified.
- [ ] Border radius ≤ 8px, no nested cards, no marketing hero.
- [ ] Every icon-only button has `aria-label` + tooltip.
- [ ] Screenshot attached below.

<!-- Drag screenshot(s) here -->

## Security checklist

- [ ] No secret/token/API key surfaces in JSON, HTML, log, audit, or exception.
- [ ] Mutations (resume/reject/replay/cancel/retry-webhook) gated by `DashboardActionAuthorizer`.
- [ ] CSRF token on every POST form.
- [ ] Confirm modal on every destructive action.

## Docs touched

- [ ] `docs/PROGRESS.md` updated (active branches, next step).
- [ ] `docs/LESSON.md` updated (if reusable insight discovered).
- [ ] README counts in sync with real `composer test` and `npm run test:e2e` output (skill `test-count-readme-sync`).
- [ ] `Comparison vs alternatives` table updated (if a new capability shipped).

## Public contract impact

- [ ] No changes to `Padosoft\LaravelFlowAdmin\Contracts\*`.
- [ ] No changes to public config keys (`config/flow-admin.php`).
- [ ] No renamed asset publish tag or route name.

If any of the above is checked NO, link the matching `docs/UPGRADE.md` entry: <!-- link -->

## Review hints

<!-- Anything specific Copilot or human reviewers should focus on. -->
