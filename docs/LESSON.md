# `padosoft/laravel-flow-admin` — LESSON

> Reusable findings from CI failures, Copilot review comments, local debugging, and design decisions.
> One section per learning. Date entries with `YYYY-MM-DD`. Newest at the top.

---

## 2026-05-06 — Subtask 2.3 Playwright CI green-up

### `vendor/bin/testbench serve` does NOT auto-discover the host package's providers

- `extra.laravel.providers` in the host package's `composer.json` is the discovery contract for **consumer** Laravel apps that depend on the package — not for the package's own dev-time Testbench server.
- Without a `testbench.yaml` at the repo root, `vendor/bin/testbench serve` boots the bundled Testbench Laravel app with **zero** of the host package's providers registered. Routes from `routes/flow-admin.php` never load and `/flow` returns 404 — even though PHPUnit Feature tests pass (those use `getPackageProviders()` on the test case).
- The CI symptom is misleading: Playwright sees /flow returning 404 fast, our pre-Playwright-1.50 versions retry until webServer.timeout fires (`Timed out waiting 120000ms from config.webServer.`), and the report blames the webServer instead of the actual route registration miss.

**How to apply:** every package that runs `vendor/bin/testbench serve` for E2E (or local DX) ships a `testbench.yaml` at the repo root explicitly listing the package providers:
```yaml
laravel: '@testbench'
providers:
  - Padosoft\LaravelFlowAdmin\FlowAdminServiceProvider
```
This is independent from `extra.laravel.providers` (which serves consumer apps) and from `tests/TestCase.php::getPackageProviders()` (which serves PHPUnit). Without all three the package is not actually wired in any of the three contexts.

### Vite `outDir` inside `publicDir` causes infinite recursive copy on Windows

- Default Vite config copies `publicDir` (default `public/`) into `outDir`. If `outDir` is `public/vendor/flow-admin`, the copy nests `public/vendor/flow-admin/vendor/flow-admin/...` on every build until the path exceeds Windows' MAX_PATH (260) and the build either crashes with `ENOTEMPTY` or silently wedges on the next run.
- Once the deep tree exists, `Remove-Item -Recurse` and `cmd /c rmdir /s /q` both fail because the path is too long even for the long-path `\\?\` prefix in some PowerShell builds. The reliable cleanup is `robocopy <empty-dir> public/vendor /MIR` followed by `Remove-Item public/vendor`.

**How to apply:** when the Vite output lives inside `public/`, set `publicDir: false` on the root config (or `build.copyPublicDir: false`). This package does not use a separate static-public source tree — all assets are emitted into `outDir` from `resources/`, so disabling the copy is correct. Document the why in a comment on the config so a future contributor does not reintroduce the recursion by adding a `public/static-stuff/` folder and re-enabling `publicDir`.

---

## 2026-05-06 — Subtask 2.3 Playwright web-server cross-platform launcher

### Node `spawn('php', …)` on Windows fails for two compounding reasons

- Node's `spawn` without `shell: true` does **not** honour Windows `PATHEXT`, so `spawn('php', args)` returns `ENOENT` even when `php.exe` is on `PATH`.
- Adding `shell: true` triggers the Node 22+ deprecation about non-escaped args, and **also** misparses paths that contain spaces — for this repo `vendor/bin/testbench` lives under `…\Visual Basic\Ai\laravel-flow-admin\…`, and the space in `Visual Basic` causes cmd to split the testbench path into two arguments, breaking the launch (`spawn EINVAL`).
- A `where php` lookup followed by `spawn(absolutePath, args)` works for `.exe`, but if `where` returns a `.bat`/`.cmd` shim first (common on dev machines that wrap PHP through Composer scripts) Node ≥18 refuses to spawn it without `shell: true`, putting us back to the previous problem.

**How to apply:** in cross-platform launchers like `scripts/serve-testbench.mjs`, branch on `process.platform`:

- POSIX: plain `spawn('php', [testbench, …])`.
- Windows: `spawn('cmd.exe', ['/d','/s','/c', `php "${testbench}" serve …`], { windowsVerbatimArguments: true })`. The `cmd.exe /d /s /c` invocation lets cmd resolve `php` via PATHEXT, and the explicit double-quotes around the testbench path survive the space in `Visual Basic`. Keep the args list to `cmd.exe` minimal (only the single command-string after `/c`) to avoid re-quoting issues.

CI runs on Linux so it always takes the POSIX branch — Windows quirks never reach the green-bar path. The Windows branch exists only for local DX on the maintainer's box.

### Make `flow-admin.middleware` env-driven so E2E can skip auth without forking config

- The default `['web', 'auth']` is correct for production but blocks E2E smoke specs (which would have to seed an authenticated session for every spec just to GET `/flow`).
- Hard-coding two configs (one for prod, one for tests) drifts; coupling the smoke to a fixture login is overkill for a "does the bundle wire up?" check.

**How to apply:** keep the single `config/flow-admin.php` `middleware` key as the public contract, but read it from `FLOW_ADMIN_MIDDLEWARE` (CSV, default `web,auth`). The E2E launcher (`scripts/serve-testbench.mjs`) sets `FLOW_ADMIN_MIDDLEWARE=web` so testbench serve routes through `web` only. Production deployments override via real env. The existing `test_config_is_loaded` already asserts only key presence — no test breakage.

---

## 2026-05-06 — Macro PR #2 Codex pass

### Tarball extract path mismatched the docs we wrote citing it

- The Claude Design archive ships with a top-level folder named after the bundle (`laravel-flow-admin/`) holding `project/`, `chats/`, `README.md`. When we extracted into `.design-source/`, the resulting tree was `.design-source/{project,chats,README.md}` — NOT `.design-source/laravel-flow-admin/project/`.
- We then wrote 4 docs (shell skill, AGENTS.md, docs/RULES.md ×2) referencing the wrong path. Codex caught it on macro PR #2 review (P2). The wrong path would have sent every UI implementer to a missing directory.

**How to apply:** after any tarball extract, run `ls -d <extract-dir>/*` and capture the actual top-level paths into a single anchor variable, then propagate that variable into docs by search/replace. Never hand-write the path twice. Before pushing, run `grep -rn "<wrong-path>" . --include="*.md"` to confirm 0 matches across **every** entrypoint (`AGENTS.md`, `CLAUDE.md`, `docs/RULES.md`, `.claude/skills/*/SKILL.md`, `.github/copilot-instructions.md`, `.github/PULL_REQUEST_TEMPLATE.md`). The first sweep on this lesson missed 3 of those entrypoints because we only fixed the 4 files Copilot's first pass had cited; Codex's second pass caught CLAUDE.md, .github/copilot-instructions.md, and .github/PULL_REQUEST_TEMPLATE.md as still wrong.

---

## 2026-05-06 — Macro 1 PR #1 second Copilot pass

### `composer update` in CI is non-reproducible once a lockfile exists

- Even with `--prefer-dist --no-interaction --no-progress`, `composer update` recalculates the dependency tree on every CI run, ignoring `composer.lock` and producing drift between runs (and between local + CI).
- Before lockfile exists (Macro 1 scaffold), `composer install` would fail because there is no lock to install from. So the install step needs to be lockfile-aware, not statically `composer update` or `composer install`.

**How to apply:** every CI Composer install step uses `if [ -f composer.lock ]; then composer install --prefer-dist ...; else composer update --prefer-dist ...; fi`. Lock files become enforced from Macro 2 onward; the conditional keeps the scaffold green and the production runs reproducible.

### Mock data fixtures must use `*.example.test` / `*.example.com`, not real-looking emails

- Even when `.design-source/` is `export-ignore`d from the Composer dist, files committed to the public repo are scanned by GitHub secret/PII scanners and indexed by anyone browsing the source tree.
- Real-looking domain emails (`m.rossi@example.com`, `admin@padosoft.com`) trigger false positives on PII scanners and contradict the same rule we apply to docs (no personal info in public repos).

**How to apply:** all mock fixtures (now and in Macro 4 ArrayReadModel) use the IETF-reserved test TLDs: `*.example.test` (preferred for actor identifiers), `*.example.com` (only for canonical examples). Never use `padosoft.com`, real first-name+lastname, or any real domain in fixtures.

---

## 2026-05-06 — README assets folder

### `resources/screenshoots/` typo is preserved on purpose

- The screenshots directory is `resources/screenshoots/` (sic — double `o`), not `resources/screenshots/`. The user created the folder under that name and it is referenced from the README spec in `docs/IMPLEMENTATION_PLAN.md` Macro 9.2.
- We deliberately do **not** rename it: any external reader who already has a draft of the README, a tweet preview, or a forked branch would break. Stable URLs > spelling.

**How to apply:** when adding a new screenshot, drop it under `resources/screenshoots/` with the `laravel-flow-admin-<page>.png` naming. Do not try to "fix" the folder name. If a future major bump warrants it, do the rename atomically in a single PR with link redirects + an UPGRADE entry.

### Screenshots are NOT export-ignored

- `.gitattributes` `export-ignore`s `.design-source/`, `.github/`, `.claude/`, `docs/`, `tests/`, etc. — but **not** `resources/`. The `resources/screenshoots/*.png` files MUST land in the Composer dist so that Packagist renders the README inline images.
- Forgetting this would publish a broken README on the package page even though the GitHub README looks fine.

**How to apply:** never add `/resources export-ignore` to `.gitattributes`. If you want to slim the dist, prune individual files inside `resources/` instead.

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
