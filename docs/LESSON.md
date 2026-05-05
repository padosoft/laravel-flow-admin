# `padosoft/laravel-flow-admin` — LESSON

> Reusable findings from CI failures, Copilot review comments, local debugging, and design decisions.
> One section per learning. Date entries with `YYYY-MM-DD`. Newest at the top.

---

## 2026-05-05 — Macro 1 PR #1 Copilot review

### `actions/setup-node@v4` validates `cache-dependency-path` BEFORE later step `if:` guards run

- Even if subsequent steps are gated by `if: hashFiles('package.json') != ''`, the `setup-node` action itself fails up-front when `cache: npm` is configured with `cache-dependency-path: package-lock.json` and no lockfile exists — the failure happens at action setup time, not at run time.
- Result on a scaffold PR with no `package.json`/`package-lock.json` yet: the Frontend job is RED at the very first step, taking the E2E job (which `needs: [frontend]`) into a SKIPPED state. The "scaffold-only PR stays green" assumption breaks.

**How to apply:** detect the manifest with a small `id: pkg` step that writes `present`/`lockfile` to `$GITHUB_OUTPUT`, then gate `actions/setup-node` itself with `if: steps.pkg.outputs.present == 'true'`. Use a separate setup step variant without `cache:` when the lockfile is missing. Do not rely on `hashFiles()` evaluated mid-job to gate an action's own validation phase.

### `.claude/settings.local.json` is per-machine — gitignore it

- The Claude Code convention `*.local.*` filename means "user/machine-local config, do not share". Committing it leads to permission-allowlist drift across contributors.
- The PR review surfaced this immediately on the first PR.

**How to apply:** at the start of every new repo, add `.claude/settings.local.json` to `.gitignore` and never `git add` it. If you need to share Claude Code settings across the team, use `.claude/settings.json` (no `local`).

### Every `.claude/skills/*/SKILL.md` MUST start with YAML frontmatter

- Files without `name:` and `description:` frontmatter are not indexable by Claude Code's skill discovery — agents cannot invoke them by trigger phrase.
- The repo's own `copilot-instructions.md` and `RULES.md` mention the convention, but Copilot still flags missing frontmatter as a P1 because the mismatch breaks the documented contract.

**How to apply:** when copying skills from a sibling repo, run `head -3 .claude/skills/*/SKILL.md` to spot any file that does not start with `---`. Add a frontmatter block with a precise, trigger-rich `description:` (when to invoke, what scope, what files) before the first commit.

### When you scrub a plan/README, drop personal email + local Windows paths

- Public package repos should not embed contributor emails (use GitHub handles) or machine-local mirror paths (`C:\Users\<user>\…`). They go stale and leak personal info.
- Use role-based contacts (`@lopadova`, `@padosoft`) and link the canonical GitHub URL.

**How to apply:** before committing any `docs/*.md`, grep for `@padosoft.com`, `C:\\`, `/Users/`, `/home/` patterns and replace with handle/URL equivalents.

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
