# `padosoft/laravel-flow-admin` — LESSON

> Reusable findings from CI failures, Copilot review comments, local debugging, and design decisions.
> One section per learning. Date entries with `YYYY-MM-DD`. Newest at the top.

---

## 2026-07-18 — The E2E single-browser flake was a SILENT SEGFAULT of the experimental worker server, not a stall — and `artisan serve` never restarts a crashed server

The long-standing "a different single browser flakes each run" CI symptom was mis-diagnosed for months as a single-threaded `testbench serve` **stall** (the standing advice was "re-run the shard"). Reading an actual failing shard log end-to-end told a different story:

- The server served every request in **~0.04ms** up to test #11, then went **completely silent** — 26 s of zero requests.
- Every retry afterwards failed with **`page.goto: NS_ERROR_CONNECTION_REFUSED`** — the port was DEAD, not slow.
- **No PHP error/fatal/warning was logged.** A crash with no error output = a **segfault**.

Root cause: `PHP_CLI_SERVER_WORKERS` (added in v2.0.1 for concurrency) runs PHP's **experimental forking** built-in server, which segfaults silently under the E2E load (constant `/flow/api/live` poll + the run-monitor 2.5 s poll + the browser aborting in-flight requests as it navigates between tests). And crucially: **Laravel's `ServeCommand` does NOT restart a crashed server** — its `while ($process->isRunning())` loop only restarts on a `.env` mtime change (and only when `--no-reload` is NOT passed); when `php -S` dies the loop just exits and the port stays unbound forever. So the first test to hit the corpse eats its full 30 s timeout and the whole shard cascades on CONNECTION_REFUSED.

The v2.0.1 worker fix therefore traded a self-recovering stall for a **non-recovering crash** — median improved, tail got worse.

**Fix (definitive): supervise + respawn.** `scripts/serve-testbench.mjs` now owns a supervisor: on any UNEXPECTED child exit (not our own SIGTERM/SIGINT teardown) it respawns the serve process after a 500 ms socket-release pause, capped by a restart backstop. A crash becomes a sub-second port-rebind blip that Playwright's per-test retries (`retries: CI ? 2 : 0`) absorb. Verified locally by killing the live listener and asserting the port serves again via a NEW pid.

**How to apply:**
- When a dev server "hangs," distinguish **slow** (requests still complete, just late) from **dead** (`ECONNREFUSED`/`NS_ERROR_CONNECTION_REFUSED`, zero requests served). They have opposite fixes — concurrency vs. supervision. Read the server's own request log across the whole failure window, not just the test's final error line.
- A **silent** death (no error output) is a segfault/OOM, not an application exception — look at the process/runtime layer, not the app code.
- `PHP_CLI_SERVER_WORKERS` is experimental and crash-prone; if you use it, you MUST supervise the process, because `artisan serve` will not.
- The migrate + WAL steps run ONCE against a persistent DB file and the demo ReadModel is stateless, so respawning the serve process mid-run preserves all test state — supervision is safe here.

## 2026-07-18 — Macro E Gate G3: the full-branch review catches gating gaps a per-subtask diff can't

The macro-gate full-diff review of `task/v2e-studio` vs `main` (all of Macro E in one shot) surfaced two authorization consistency issues that every per-subtask PR review missed because each only saw its OWN slice:

### `dryRun()` (E-PR7) was the ONE authoring endpoint left ungated + unthrottled
`editGraph`/`storeDraft`/`publish`/`aiBuild` are all `Authorize::action('edit_definition', …)` and the compute/billable ones (`ai-build`, `advisor.scan`) carry a `throttle:`; `dryRun` had NEITHER. Its ORIGINAL rationale ("response is non-secret, so no gate") conflated two different risks — output-secrecy vs. compute-cost/consistency. Dry-running is an AUTHORING action (the editor's preview), so its peer is `editGraph` (gated), not the read-only `catalog()` (ungated). Fixed: gate on `edit_definition` + `throttle:30,1,flow-admin-dry-run`, add a deny-by-default Feature test, and bind AllowAll in the existing dry-run tests. **How to apply:** at a macro gate, list EVERY endpoint of one class (here: authoring mutations) side by side and check they share the same gate + throttle posture; a per-subtask review can't see that a sibling added later is inconsistent. "The output has no secrets" is NOT sufficient grounds to leave a compute-bearing, arbitrary-input endpoint ungated.

### `canViewRuns`/`canViewRunDetail`/`canViewKpis` are declared but wired NOWHERE — and that's deliberate, not a bug
Grepping `src/` shows all three view-authorizer methods are referenced only in `Support\Authorize`'s dispatch map, never called by a controller. This is CONSISTENT with the documented "read-only by default → browse on day 1 with deny-all" posture: enforcing views under `DenyAllAuthorizer` would hide everything and break that promise. So the methods are RESERVED forward-looking hooks, not dead code to wire blindly. **How to apply:** before "fixing" an unused authorizer/contract method by wiring it, check whether wiring it under the shipped default would CONTRADICT a documented value prop. If it's intentionally-reserved, make the contract honest (docblock: "RESERVED — not yet enforced") + a tracked follow-up, rather than enforcing it and breaking the default UX. Removing it would be a breaking interface change — also not free.

## 2026-07-17 — E-PR6 (working mutations): a new core `@api` field is invisible until every adapter→DTO→view hop forwards it

### The whole approve/reject-by-hash seam was dead on arrival because `tokenHash` was dropped at the adapter boundary
Core added `Dashboard\ApprovalSummary::$tokenHash` (the key `Flow::resumeByHash()`/`rejectByHash()` require). The admin has its OWN `Contracts\Dto\ApprovalSummary` + `ViewModels\ApprovalCard`, and BOTH `EloquentReadModel::mapApproval()` and `ArrayReadModel::mapApproval()` re-map core's DTO field-by-field. Every one of those hops silently ignored the new field, so the hash never reached the Blade form — the buttons would have had nothing to POST. **How to apply:** when you start consuming a newly-added core `@api` field, grep the ENTIRE chain (core DTO → your DTO → both adapters' mappers → view-model → blade) and forward it at each hop; a field appearing on the upstream DTO is NOT automatically visible downstream when an anti-corruption layer re-maps by hand. Add a read-model test asserting the field is populated end-to-end (we added a `tokenHash` assertion), not just a controller test.

### `redeliverWebhook` only acts on `failed` rows — gate the button on that, not the broader "retry-eligible" hint
Core's `EloquentWebhookOutboxRepository::redeliver()` CAS is `WHERE status = 'failed'` → returns false for anything else (unknown / delivered / pending / in-flight). The existing `OutboxRow::$canRetry` was `pending|failed|dead` (a broad visual hint). Reusing it for the button would have shown Redeliver on rows where the seam is a guaranteed no-op. Added a precise `canRedeliver = status === 'failed'`. **How to apply:** derive an action button's visibility from the seam's ACTUAL precondition, not a pre-existing looser flag with a different meaning.

### E2E on the `array` adapter can't round-trip an engine mutation — split the coverage
The E2E server runs the `array` read adapter (fixtures) + allow-all authorizer. A real `resumeByHash()`/`cancel()`/`redeliverWebhook()` needs genuinely persisted engine state, which the array fixtures don't create — so a happy-path click in E2E returns a mapped 409/503, not success. **How to apply:** prove the ENGINE round-trip (approve→succeeded, cancel→aborted, redeliver→pending) in PHPUnit Feature tests that boot real persistence (`MigratesFlowTables` + `persistence.enabled` + a file lock store + `$engine->execute()` on an approval-gated flow); prove the UI/UX layer (button renders on eligible rows, confirm gating on destructive actions, the fetch fires at the right endpoint) in Playwright. Don't try to force a full round-trip through the fixture adapter.

### Playwright strict-mode: `.first().locator('.muted')` matched the destination cell AND the placeholder
A row's destination `<td class="muted">` and the empty-action `<span class="muted">—</span>` both matched, so `toBeVisible()` threw a strict-mode violation (2 elements), not a visibility failure. **How to apply:** give action-cell placeholders/controls a dedicated `data-testid` and target that, rather than reusing a generic CSS class that also decorates unrelated cells.

### A defensive runtime guard is DEAD CODE under PHPStan when your own `array{...}` phpdoc already pins the shape
PR-review (Copilot) asked for a runtime guard on `FlowMutation::run()`'s array result (fail closed if `message` is missing). But the callable's `@param` was typed `callable(): (string|array{message: string, data?: array})`, so PHPStan level 8 *proves* `$result['message']` always exists and `is_string(...)`/`is_array(...)` are `always true` → `booleanOr.alwaysFalse` / `offsetAccess.always exists` errors. The type system already enforces the contract more strongly than a runtime check. **How to apply:** you can't have both a strict `array{...}` shape AND a "defensive" runtime guard for that same shape — pick one. If a reviewer wants the runtime guard (e.g. the value is built dynamically and callers aren't all statically verifiable), LOOSEN the declared type to `array<string, mixed>` so the guard is genuinely reachable; otherwise keep the strict shape and reply that static analysis already covers it. Do NOT reach for `@phpstan-ignore` — that's banned here.

### A single-browser E2E job failing (a different browser each push) is the single-threaded `testbench serve` flaking, not a code bug — NOW FIXED (2026-07-18)
Across three PR-42 CI rounds, the E2E matrix failed on a DIFFERENT browser each time (chromium round 2, webkit round 3) while the other two browsers — running the IDENTICAL specs as separate jobs, each with its own `serve-testbench.mjs` server — passed, and the full local run passed on all three. The failure signature: ONE test times out (~6s), then every subsequent test (including unrelated specs) fails fast (~200ms) — the classic "the single-process `php artisan serve` stalled/died mid-job and every later `page.goto` instantly fails" cascade, not an assertion failure. **ROOT-CAUSE FIX:** `scripts/serve-testbench.mjs` now sets `PHP_CLI_SERVER_WORKERS` (default 4) on the spawned server env, so `artisan serve`'s `php -S` pre-forks workers and handles requests concurrently — a slow mutation no longer blocks asset loads/polling. POSIX-only (Linux/macOS CI); PHP ignores it on Windows, so local Windows runs are unaffected. **How to apply:** a `php -S` / `artisan serve` dev server is single-threaded by default; for any E2E that makes concurrent requests, set `PHP_CLI_SERVER_WORKERS` in CI rather than living with the flake. (Historical workaround before the fix: re-run the flaked shard with `gh run rerun <id> --failed`.)

### A time-window-boundary KPI test WAS flaky (seeds `now - P1D`, query re-evaluated its own `now`) — NOW FIXED (2026-07-18)
`EloquentReadModelTest::test_kpi_window_boundary_does_not_double_count_a_run` seeded a run at exactly `now - P1D` and asserted it lands in exactly one KPI window; but the KPI query computed its OWN `now` microseconds later, so the boundary run occasionally flipped windows (~1 run in N). **FIX:** `EloquentReadModel` now takes an injectable `?Closure $clock` (default = system UTC now) used for BOTH KPI window edges (`kpis()`) and throughput buckets; the test injects a fixed instant so the seeded `started_at` and the query's window edges derive from the SAME `now`. Confirmed deterministic across 20 consecutive runs. **How to apply:** any time-window computation that a test also seeds against must read a SHARED, injectable clock — never `new DateTimeImmutable('now')` inline, which no test can freeze. Inject the clock (constructor `?Closure`), don't reach for global `Carbon::setTestNow()` unless the code already uses Carbon.

## 2026-07-17 — E-PR8b (Advisor inbox): a dependency that can't auto-wire needs its provider registered in the served testbench app

### `app(SomeClass::class)` 500s with `BindingResolutionException` when the class has non-injectable constructor params AND its provider isn't loaded

`padosoft/laravel-flow-ai`'s `FlowAdvisor` has a constructor of `(FlowDashboardReadModel, DefinitionRepository, array $analyzers, array $exposedFlowNames, ?PayloadRedactor, int $sampleSize)`. The two `array` params and the `int` have no container binding, so the container cannot **auto-wire** it — it resolves ONLY through the explicit `bind(FlowAdvisor::class, …)` in the AI pack's own service provider. This bit us because the earlier E-PR8a "Build with AI" feature worked in the served E2E app WITHOUT that provider being loaded: `FlowBuilderService` (its sibling) auto-wires fine (all its deps — `LlmClient`, `NodeRegistry`, `GraphValidator` — are bound), so we'd wrongly concluded the AI provider was active. It wasn't. `app(FlowAdvisor::class)` then threw `BindingResolutionException` (a sanitized 500 in the endpoint), and the advisor E2E failed. **How to apply:** before assuming a package's classes are resolvable in the `testbench serve` app, check whether the specific class AUTO-WIRES (all ctor params bound) or needs its provider. Orchestra Testbench's served app does NOT pick up a path-repo sibling's `extra.laravel.providers` auto-discovery the way a real consumer app does — so any optional package whose bindings you rely on at runtime must be listed EXPLICITLY in `testbench.yaml`'s `providers:` block. A real consumer `composer require`ing the package gets it via auto-discovery; the served E2E app does not. (The `laravel.log` dumped by the E2E failure step — see the 2026-07-16 lesson — is what surfaced the exception class immediately.)

### Registering the optional pack's provider does not undo an admin-side binding override

Adding `LaravelFlowAIServiceProvider` to `testbench.yaml` re-introduces the AI pack's own `LlmClient` bindings (global + the `FlowBuilderService` contextual one). The admin's dev/E2E `FakeLlmClient` override still wins because it is bound in the admin provider's **`boot()`**, which runs after every provider's `register()` — verified by re-running the full chromium E2E suite (all green, the "Build with AI" happy path still uses the fake). When you add a provider that competes for a binding you override, re-run the E2E that depends on the override, don't assume.

## 2026-07-17 — E-PR8a (AI Flow Builder): overriding a dependency's binding must account for CONTEXTUAL bindings, and the env-gate must live in config for larastan

### A global `singleton()`/`bind()` override does NOT reach a `when($class)->needs($abstract)->give()` contextual binding

To let the admin's Studio exercise the AI flow builder without a live model, we swap `padosoft/laravel-flow-ai`'s real (network) `LlmClient` for a deterministic `FakeLlmClient`. The obvious `$app->singleton(LlmClient::class, fn () => new FakeLlmClient)` looked sufficient — but `FlowBuilderService` never resolved the fake. The AI pack registers a **contextual** binding, `when(FlowBuilderService::class)->needs(LlmClient::class)->give(<guarded network client scoped to the 'ai.flow.builder' node identity>)`, and Laravel's container resolves a contextual binding **before** any global binding for the same abstract. So the class we most needed to fake kept getting the real client. **How to apply:** when you override a third-party binding that the third party ALSO binds contextually per-consumer, override BOTH — the global `singleton`/`bind` AND the matching `when($theirConsumer)->needs($abstract)->give($yourFake)`. Grep the dependency's service provider for `->when(` / `->needs(` before assuming a global override wins. Do the override in **`boot()`**, not `register()`: boot runs after every provider's `register()`, so your `give()` overwrites theirs regardless of provider load order (a fresh app has no deterministic register order across packages).

### Env-gated behavior belongs in `config/*.php`, read via `config()`; larastan forbids `env()` elsewhere

The fake-LLM opt-in was first written as `filter_var(env('FLOW_ADMIN_FAKE_LLM', …), FILTER_VALIDATE_BOOLEAN)` inside the provider — PHPStan (larastan `noEnvCallsOutsideOfConfig`) failed it: `env()` returns `null` once `config:cache` has run, so any `env()` call outside the `config/` directory is a production-time footgun. **How to apply:** put the `env()` read in `config/flow-admin.php` (`'ai' => ['fake' => filter_var(env('FLOW_ADMIN_FAKE_LLM', false), FILTER_VALIDATE_BOOLEAN)]`) and have the provider read `config('flow-admin.ai.fake')`. Bonus: this mirrors the existing `FLOW_ADMIN_AUTHORIZER` → `AllowAllAuthorizer` pattern, including the **production refusal** — a dev/E2E-only client (fake LLM, allow-all authorizer) must hard-refuse in `app()->environment('production')` even if the flag is mistakenly set there, because a fake silently answering real requests is worse than a hard failure.

### A dev/E2E fake driven through `testbench.yaml`'s `env:` needs the same production guard the config has

The "Build with AI" E2E runs the served app with `FLOW_ADMIN_FAKE_LLM: true` in `testbench.yaml`'s `env:` block (the only runtime knob the `testbench serve` grandchild actually reads — see the 2026-07-16 lesson). Because that same YAML would be a footgun if copied toward production, the provider's `app()->environment('production')` refusal is what makes shipping the flag safe. The fake returns a single node whose `type` the array adapter registers (`demo.trigger`), so the server-side `GraphValidator` pass inside `FlowBuilderService::build()` accepts it and the endpoint returns a real, loadable envelope — pick your fake's fixture to satisfy the REAL validator, not to bypass it.

---

## 2026-07-16 — E-PR3 (canvas editor): the `testbench serve` app silently used `testing`/`:memory:` (not the migrated file) on CI; plus the diagnostic that proved it, and a stray NUL byte

### When a `testbench serve` E2E write is green locally but red in CI with "no such table", the served app is on a DIFFERENT DB connection than the migration ran against

E-PR3's "save as draft" E2E POSTs to `storeDraft()` → `DefinitionRepository::createDraft()`, a real DB write. `scripts/serve-testbench.mjs` migrates core's tables into a persistent SQLite FILE and points the **migration process** at it via the spawned process's env (`DB_CONNECTION=sqlite`, `DB_DATABASE=<file>`). But `testbench serve` runs `artisan serve` → a `php -S` grandchild, and **that grandchild did not inherit the serve script's process env on CI's Ubuntu runner** — so the SERVED app fell back to testbench's default `testing` connection (`:memory:`), which has no tables. Every save 500'd with `SQLSTATE … no such table: flow_definitions (Connection: testing, Database: :memory:)` while the read-only scenarios passed (the `array` ReadModel adapter never touches the DB). It was green locally only because the local (Windows) process happened to propagate the env down to the built-in-server worker; CI didn't. The initial WRONG hypothesis was SQLite journal-lock contention (the 500 came back fast, which *looked* like an immediate SQLITE_BUSY) — it was actually "table doesn't exist because we're on the wrong database entirely."

**How to apply:** two facts drive the fix. (1) `artisan serve` (what `testbench serve` runs) spawns the `php -S` worker with **CWD = the skeleton's `public_path()`** (`ServeCommand::startProcess()` → `new Process($cmd, public_path(), …)`) and DROPS every non-passthrough env var from that worker (`(new Collection($_ENV))->mapWithKeys(...)` keeps only `static::$passthroughVariables`) — so neither a relative `DB_DATABASE` nor an absolute one set in the serve script's process env reaches or resolves correctly for the served app. (Locally it "worked" only because that machine's `variables_order` populated `$_ENV` AND the skeleton had no `.env` at the moment, so the whole env was forwarded — pure luck, absent on CI.) (2) The one runtime knob that DOES apply is **`testbench.yaml`'s `env:` block**, which the served app loads itself (it does NOT leak into PHPUnit — that's `phpunit.xml`-driven, so it's safe for unit/feature tests). So: set `DB_CONNECTION: sqlite` in `testbench.yaml` to force the file connection off `testing`/`:memory:`, and do NOT try to set `DB_DATABASE` there at all — instead migrate the skeleton's OWN `database_path('database.sqlite')` from `serve-testbench.mjs`. Both the migrate process and the served app boot the `@testbench` skeleton, so both compute the identical absolute `database_path('database.sqlite')` (`base_path()` = `vendor/orchestra/testbench-core/laravel`) via the connection's default `env('DB_DATABASE', database_path('database.sqlite'))` — no relative path, no CWD, no env-inheritance dependency. **Verify by inspecting the skeleton's `database/database.sqlite` after an E2E run** — the persisted `flow_definitions` rows prove the served app wrote THERE, i.e. to the same absolute path CI will use, not to some env-forwarded local path. WAL journal mode + `busy_timeout` (via `scripts/enable-wal.php`) is kept as genuine defense now that writes actually hit the shared file concurrently with the `/flow/api/live` poll's reads — but it was NOT the fix here.

### The single most useful move was making the server-side exception visible in CI

The caught-and-logged 500 (`StudioController::storeDraft`'s `catch` logs via `Log::warning` and returns a generic body) put the real `QueryException` in the testbench app's `storage/logs/laravel.log`, which is NOT in the Playwright/webServer stdout — so for two rounds the actual cause was a black box and produced a plausible-but-wrong hypothesis. A one-line CI step that `tail`s that log on `if: failure()` turned "fast 500, must be a lock" into "Connection: testing, Database: :memory: — wrong DB." **How to apply:** the FIRST response to an opaque E2E 500 behind a caught exception is to surface the server log in CI, before theorizing about the mechanism. `.claude`-review reasoning from the diff alone will rationalize a wrong root cause; the log is ground truth.

### A single NUL byte (U+0000) in a source file turns it "binary" to git and ships into the bundle

A fan-in dedup key was written as `` `${edge.target}\0${edge.targetHandle}` `` with a literal NUL between the interpolations instead of a printable delimiter. JS tolerates embedded NULs in strings/`Set` keys, and neither operand can contain one, so de-dup kept working — but git rendered the whole file as `Binary files … differ` (so `git diff`/`git blame`/PR-review tooling silently stopped showing changes for it), and the NUL survived `npm run build` into `public/vendor/flow-admin/assets/*.js`. **How to apply:** when `git diff --stat` reports `Bin <a> -> <b> bytes` for a file you edit as text, do NOT shrug it off — grep the file for NUL bytes (`python -c "print(open(p,'rb').read().count(b'\x00'))"`) before committing. Match the file's existing delimiter convention (this file uses `.` for composite keys, e.g. `${wire.targetNodeId}.${wire.targetPortKey}`).

---

## 2026-07-14 — E-PR1 (React island pipeline): `defaults.run.working-directory` silently doesn't apply to `uses:` steps, and a "build once" fix that wasn't

### `actions/upload-artifact` / `actions/download-artifact`'s `path:` is workspace-root-relative even inside a job with `defaults.run.working-directory` set

This is the SAME footgun E-PR0's CI fix already hit once for `hashFiles()`, `setup-node`'s `cache-dependency-path`, and `upload-artifact`'s own `path:` — and it bit again here, in a *new* `download-artifact` step added later in the same job. `defaults.run.working-directory` only rewrites the cwd for `run:` (shell) steps; every `uses:` (action) step's own path-shaped inputs stay resolved against `$GITHUB_WORKSPACE` regardless. A `download-artifact` step with `path: public/vendor/flow-admin/` in a job whose checkout lives at `laravel-flow-admin/` (via the checkout step's own `path:`) silently downloads the artifact to a *sibling* of the real checkout — and the step still reports `outcome: success`, so an `if: steps.<id>.outcome != 'success'` fallback guard never fires either. The bug is invisible in the workflow file (no syntax error, no failed step) and only shows up as "the build output isn't where the app expects it" inside a LATER step.

**How to apply:** any time a job sets `defaults.run.working-directory` because it checks out into a subdirectory (the path-repo-sibling pattern this program uses whenever a package depends on an unreleased sibling via a local Composer `path` repository), audit **every** `uses:` step's path-shaped `with:` values by hand — `hashFiles()` calls in `if:` conditions, `cache-dependency-path`, `upload-artifact`/`download-artifact`'s `path`, anything else that looks like a filesystem path passed to an action rather than a shell command. Grep for the checkout subdirectory name in the job and manually verify each `uses:` step's paths are prefixed with it. Don't assume "the job builds and green-checks" proves the paths are right — a `download-artifact` landing in the wrong place doesn't fail the step, it just quietly breaks whatever consumes the download three steps later, exactly the way this one did.

### A "reduce the redundant build" fix that only moves code without measuring what it costs isn't actually a fix

The first pass at "the e2e browser matrix job builds the Vite bundle 3x, once per browser" moved the `npm run build` call from inside the `test:e2e` npm script into its own named CI step — which changed *where* the build ran, not *how many times*. Each matrix leg (chromium/firefox/webkit) is still a fully separate GitHub Actions runner with its own checkout, so the total build count was unchanged; the "fix" was purely cosmetic and a second review round caught it immediately by counting invocations against the actual job topology, not by reading the step names. The REAL fix needed cross-job artifact sharing (`frontend` job builds once, uploads via `actions/upload-artifact`; the `e2e` matrix downloads it). **How to apply:** when a review flags "this runs N times unnecessarily," verify the fix by tracing the actual execution graph (which jobs/matrix legs run, and whether each independently re-executes the step), not by confirming the code *looks* different — a step moved to a new name/location in the same job still runs the same number of times the job itself runs.

### `actions/upload-artifact`'s default `include-hidden-files: false` silently drops dotfiles/dot-directories — and the step still reports success

Once the working-directory path bug (above) and the "build once, actually" cross-job artifact sharing were both fixed, CI still failed — but only on the Studio E2E test specifically, on all 3 browsers, while every other page's E2E test kept passing. The build output has 5 files (`.vite/manifest.json` + 4 hashed `assets/*`); the upload step's own log said "there will be 4 files uploaded." `actions/upload-artifact@v7` (like recent v4 versions) defaults `include-hidden-files` to `false` and silently excludes anything under a dot-prefixed path — `.vite/manifest.json` — with no warning, no error, and a normal "successfully uploaded" log line. The downloaded copy in the `e2e` job therefore had the built JS/CSS but no manifest, so `AbstractManifestAssetController::resolveBuiltPath()` correctly 404'd (that part of the code was never wrong), the Studio page rendered its Blade shell with a `<script src>` pointing at a 404, and React consequently never mounted — while every OTHER page (which doesn't depend on the Vite build at all) kept passing, which is what made this look like a Studio-specific bug at first rather than an artifact-transfer one.

**How to apply:** any time a `path:` fed to `upload-artifact` can contain a dotfile or dot-directory (a `.vite/`, `.next/`, `.output/` manifest, a `.env.production` a build step wrote, etc.), set `include-hidden-files: true` explicitly — never rely on the default. And when a CI failure is scoped to "everything downstream of one build artifact fails, everything else passes," suspect the artifact transfer itself before the code that consumes it — check the upload step's own "N files uploaded" log line against the actual expected file count from a local build.

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
