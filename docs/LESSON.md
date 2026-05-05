# `padosoft/laravel-flow-admin` — LESSON

> Reusable findings from CI failures, Copilot review comments, local debugging, and design decisions.
> One section per learning. Date entries with `YYYY-MM-DD`. Newest at the top.

---

## 2026-05-05 — Bootstrap

### Design handoff is gzipped tar from `api.anthropic.com/v1/design/h/<id>`

- Fetching `https://api.anthropic.com/v1/design/h/<HASH>?open_file=index.html` returns a **gzipped tar archive** as `application/gzip`, not HTML.
- WebFetch will report the body as binary; the helpful side-effect is it saves the bytes to `~/.claude/projects/<project-slug>/<session>/tool-results/webfetch-*.bin`.
- To use it: `gunzip` then `tar -xf` and follow `README.md` inside the archive.
- The archive contains `chats/` (transcripts), `project/` (the prototype HTML/CSS/JSX), and a top-level `README.md` instructing coding agents on how to read it.

**How to apply:** when a user gives a Claude Design URL and WebFetch returns "binary content saved", look for the `.bin` artefact, treat it as gzip-tar, extract to `.design-source/`, then read `index.html` and follow its imports.

### Copy reusable rules/skills/agents from sibling Padosoft repos at the start

- The repo `padosoft-laravel-flow` already ships a mature `.claude/{rules,skills,agents,commands,instructions}/` set covering Laravel 13 admin patterns, the Copilot PR review loop, and the pre-push self-review checklist.
- Copying these into a new admin repo at the start saves days of bookkeeping and inherits hard-won learnings.
- Adapt only when the original references a name/concept that does not exist in the new repo (e.g. drop `laravel-flow-enterprise` skill in favour of a repo-specific shell skill).

**How to apply:** at Macro 1 of any new Padosoft Laravel admin repo, run a parameterised copy from the closest sibling and adapt only the few files that mention the old repo by name. Do not redesign the rule set from scratch.

### Branch naming `task/<macro>` and `subtask/<macro>-<n>-<name>` collides with CI triggers

- If you trigger CI on `push: branches: ['task/**']`, every subtask push will spawn a duplicate run alongside the PR run, wasting compute.
- Trigger CI on `push: [main]` + `pull_request: [main, 'task/**']`. Push triggers stay narrow; PR triggers cover both subtask→macro and macro→main flows.

**How to apply:** copy the workflow trigger pattern verbatim from `padosoft-laravel-flow/.github/workflows/ci.yml`. Do not "improve" the trigger to include subtask branch pushes — that pattern was burned in twice.
