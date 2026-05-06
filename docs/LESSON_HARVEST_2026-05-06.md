# LESSON Harvest 2026-05-06

## Categorization

| Learning | Category | Target |
| --- | --- | --- |
| Overview page must survive missing `flow_*` tables in shell/theme tests | Rule | `docs/RULES.md` + controller fallback pattern |
| E2E smoke text assertions drift when placeholder headings are replaced | Checklist | `.claude/skills/pre-push-self-review/SKILL.md` |
| Command palette/polling runtime should be test-covered via keyboard + toggle interactions | Skill | `.claude/skills/pre-push-self-review/SKILL.md` and e2e suite |

## Applied
- Added `OverviewController::safe()` fallback wrapper for read-model calls that may fail before migrations.
- Updated smoke e2e heading expectation from placeholder to current page title.
- Added `tests/e2e/macro8-runtime.spec.js` covering `Ctrl+K` and live polling toggle.
