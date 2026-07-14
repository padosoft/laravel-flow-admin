# `padosoft/laravel-flow-admin` — LESSON

> Reusable findings from CI failures, Copilot review comments, local debugging, and design decisions.
> One section per learning. Date entries with `YYYY-MM-DD`. Newest at the top.

---

## 2026-07-14 — E-PR1 (React island pipeline): `defaults.run.working-directory` silently doesn't apply to `uses:` steps, and a "build once" fix that wasn't

### `actions/upload-artifact` / `actions/download-artifact`'s `path:` is workspace-root-relative even inside a job with `defaults.run.working-directory` set

This is the SAME footgun E-PR0's CI fix already hit once for `hashFiles()`, `setup-node`'s `cache-dependency-path`, and `upload-artifact`'s own `path:` — and it bit again here, in a *new* `download-artifact` step added later in the same job. `defaults.run.working-directory` only rewrites the cwd for `run:` (shell) steps; every `uses:` (action) step's own path-shaped inputs stay resolved against `$GITHUB_WORKSPACE` regardless. A `download-artifact` step with `path: public/vendor/flow-admin/` in a job whose checkout lives at `laravel-flow-admin/` (via the checkout step's own `path:`) silently downloads the artifact to a *sibling* of the real checkout — and the step still reports `outcome: success`, so an `if: steps.<id>.outcome != 'success'` fallback guard never fires either. The bug is invisible in the workflow file (no syntax error, no failed step) and only shows up as "the build output isn't where the app expects it" inside a LATER step.

**How to apply:** any time a job sets `defaults.run.working-directory` because it checks out into a subdirectory (the path-repo-sibling pattern this program uses whenever a package depends on an unreleased sibling via a local Composer `path` repository), audit **every** `uses:` step's path-shaped `with:` values by hand — `hashFiles()` calls in `if:` conditions, `cache-dependency-path`, `upload-artifact`/`download-artifact`'s `path`, anything else that looks like a filesystem path passed to an action rather than a shell command. Grep for the checkout subdirectory name in the job and manually verify each `uses:` step's paths are prefixed with it. Don't assume "the job builds and green-checks" proves the paths are right — a `download-artifact` landing in the wrong place doesn't fail the step, it just quietly breaks whatever consumes the download three steps later, exactly the way this one did.

### A "reduce the redundant build" fix that only moves code without measuring what it costs isn't actually a fix

The first pass at "the e2e browser matrix job builds the Vite bundle 3x, once per browser" moved the `npm run build` call from inside the `test:e2e` npm script into its own named CI step — which changed *where* the build ran, not *how many times*. Each matrix leg (chromium/firefox/webkit) is still a fully separate GitHub Actions runner with its own checkout, so the total build count was unchanged; the "fix" was purely cosmetic and a second review round caught it immediately by counting invocations against the actual job topology, not by reading the step names. The REAL fix needed cross-job artifact sharing (`frontend` job builds once, uploads via `actions/upload-artifact`; the `e2e` matrix downloads it). **How to apply:** when a review flags "this runs N times unnecessarily," verify the fix by tracing the actual execution graph (which jobs/matrix legs run, and whether each independently re-executes the step), not by confirming the code *looks* different — a step moved to a new name/location in the same job still runs the same number of times the job itself runs.

---

## 2026-07-14 — E-PR0 (Dashboard read-model rewrite): path-repo CI, "no search = no cap", and a "@api-only" surface exception

### `composer.json`'s local `path` repository needs its OWN sibling checkout step in CI — a single-repo `actions/checkout` silently breaks `composer install`

Retargeting `padosoft/laravel-flow` from a tagged release to `dev-main` via a local `path` repository (`"url": "../padosoft-laravel-flow"`) works locally because the sibling directory already exists on disk, but a GitHub Actions runner starts with only THIS repo checked out — `composer update`/`install` fails to resolve the path repository outright, and every downstream job (Pint, PHPStan, PHPUnit) never runs. Local Copilot CLI review caught this before it ever reached CI (a genuinely CI-breaking finding a local-only test run cannot see). Fix: checkout BOTH repos as true siblings under `$GITHUB_WORKSPACE` (`path: laravel-flow-admin` + `path: padosoft-laravel-flow`), add `defaults.run.working-directory: laravel-flow-admin` to the job, and manually re-prefix the handful of GitHub Action inputs that are workspace-root-relative regardless of `working-directory` (`hashFiles(...)` in `if:` conditions, `actions/setup-node`'s `cache-dependency-path`, `actions/upload-artifact`'s `path`) — `working-directory` only affects `run:` shell steps, not YAML-level expressions or other actions' own path inputs. `laravel-flow-ai`/`laravel-flow-connect` already solved this identically; this is the third repo to need it.

### "This filter can only express exact-match, so we cap at N most-recent rows" is a real regression if applied even when NO filtering needing that cap is actually active

When a read-model rewrite drops to a bounded-batch-then-filter-in-PHP approach because the target contract's filter DTOs can't express free-text search or an OR-of-statuses, it's tempting to route EVERY call through that same bounded path for simplicity — but that silently caps `total`/pagination even for the plain "browse everything" or "filter by one exact status" case, which the target contract's real server-side pagination could serve unbounded. A PR-level Copilot review caught this precisely because it reasons about "what did the OLD implementation guarantee that the NEW one doesn't" — the old raw-SQL adapter counted/paginated the full table; the naive rewrite silently narrowed that to the cap for every read, not just the cases that structurally need it. Fix: branch on whether the ACTUAL request needs client-side filtering (free-text search present, or — for a multi-mapped status like this program's admin `'failed'` → engine `[failed, aborted]` — a compound status) and only fall back to the bounded batch in that branch; everything else goes straight to the target's own paginated call.

### A documented "self-imposed narrower public-surface rule" (stricter than the dependency's own `@api` boundary) needs updating in the SAME PR that adds a deliberate, justified exception — not silently violated

This repo's `AGENTS.md` says "consume only `Dashboard\*` and the documented action API" — narrower than core's actual `@api` surface (which also includes stable contracts like `Contracts\DefinitionRepository`). Adding a real, justified dependency on `DefinitionRepository` (needed because `Dashboard\*` has no declared-graph-node primitive, and the alternative was resurrecting a real pre-existing step-count bug) without updating that rule reads, to a reviewer checking the repo's own stated boundary, as an unexplained violation — even though the dependency itself is perfectly SemVer-safe. Fix: update the rule to name the specific exception and its rationale in the SAME commit, not as an afterthought — the reviewer's complaint was really "this isn't documented as intentional," not "this is technically unsafe."

## 2026-05-06 — Subtask 2.3 Playwright CI green-up

### Workflow-step `env:` does NOT propagate to PHP `env()` reliably under `vendor/bin/testbench serve` — use `testbench.yaml` `env:` block

- GH Actions `env:` blocks export shell vars before the step's `run:`. PHP CLI inherits the shell env, so `getenv()` and `$_SERVER[...]` see the variable. But Laravel's `env()` helper (and our package's `env('FLOW_ADMIN_MIDDLEWARE', 'web,auth')` default) reads through `Dotenv\Repository\AdapterRepository`, which testbench's bootstrap rebinds. After `Application::create()` runs `LoadEnvironmentVariables` (the bundled `vendor/orchestra/testbench-core/laravel/.env`), our shell-exported `FLOW_ADMIN_MIDDLEWARE=web` was lost — `env()` returned `null` and the controller fell back to `['web', 'auth']`. Result on /flow: `Authenticate` middleware kicked in, redirected to `route('login')`, that route does not exist in testbench's bundled app, and we got `Symfony\\…\\RouteNotFoundException: Route [login] not defined.` rendered as a 500 — Playwright then timed out because 500 is not a `webServer.url` ready signal.
- The diagnostic step that surfaced this used `APP_DEBUG=true APP_KEY=…` to coax Laravel's exception page out of production (`<title>Laravel</title>` only) into debug mode. The actual exception class lived in a JSON-encoded `markdown` blob inside the rendered React error page — `head -c 4000` clipped before it; the right capture is `tail -c 1500` plus a targeted `grep -oE '<h1[^>]*>[^<]+</h1>'`.

**How to apply:** for any env override that the package code reads via `env()`, add it to `testbench.yaml` under the `env:` block:
```yaml
env:
  FLOW_ADMIN_MIDDLEWARE: web
  FLOW_ADMIN_ADAPTER: array
```
This block is processed by `Orchestra\Testbench\Foundation\Bootstrap\LoadEnvironmentVariablesFromArray` *after* the standard `LoadEnvironmentVariables` bootstrapper, so it always wins. The `testbench.yaml` is already a dev/test-only file — it never reaches consumer apps — so dropping `auth` here does not weaken the production default. Keep an explicit `FLOW_ADMIN_MIDDLEWARE: web` in the CI step env too as belt-and-suspenders documentation; `testbench.yaml` is the truthful source.

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

## 2026-05-06 — Macro 8 runtime + shell resilience

### Polling toast assertion should target the newest toast, not the first toast in stack

- Runtime boot emits an initial informational toast (`Flow Admin ready`). The polling toggle emits subsequent toasts (`Auto-refresh paused/resumed`).
- The original E2E assertion in `tests/e2e/macro8-runtime.spec.js` used `#flow-toast-stack .toast:first`, which is unstable because the oldest toast can remain present while new toasts append.
- This caused cross-browser failures even though the feature worked, because the assertion kept reading the bootstrap toast text.

**How to apply:** in toast-stack assertions, target the most recent toast (`.last()` in Playwright) when validating a newly triggered interaction toast. Keep this for any future queue-style UI notifications.

### Overview route must tolerate missing `flow_*` tables in lightweight shell tests

- Once overview became data-driven, baseline feature tests that only verify shell/theme started failing with `no such table: flow_runs` on in-memory DB contexts that intentionally do not run flow migrations.
- Controller-level guarded fallback (`safe(callable, default)`) preserves shell rendering for those tests while still using full read-model data when tables are present.

### Smoke test copy can drift after replacing placeholders

- Legacy smoke asserted `h1 = Flow Admin` from the early stub page.
- After implementing the real overview page title, the expected text must be updated or the suite reports false regressions.

### Macro 8 must be validated by interaction tests, not only static render checks

- Added e2e coverage for keyboard palette open (`Ctrl+K`) and live polling pause/resume feedback.
- This catches regressions in runtime wiring that static visual tests do not detect.
