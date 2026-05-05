# `padosoft/laravel-flow-admin` — Implementation Plan

> Ultimo aggiornamento: **2026-05-05**
> Owner: [`@padosoft`](https://github.com/padosoft) · Maintainer: [`@lopadova`](https://github.com/lopadova)
> Repository: [`padosoft/laravel-flow-admin`](https://github.com/padosoft/laravel-flow-admin)

Questo documento è la **Single Source of Truth** del piano di implementazione.
È letto:

- da ogni nuovo agente che riapre la sessione
- prima di qualunque sub-agent dispatchato in parallelo
- prima di aprire una PR (per validare che la subtask sia "in piano")

Se il piano cambia, **questo file viene aggiornato nello stesso commit della modifica**.

---

## 0. Mission & non-goals

### Mission
Realizzare un **package Laravel 13.x** (`padosoft/laravel-flow-admin`) che fornisca una **UI/UX admin panel** per il package headless [`padosoft/laravel-flow`](https://github.com/padosoft/laravel-flow), basata sul design HTML/CSS approvato (Claude Design — `eZVMtDE08LRjCs4EOsNgzg`) ed estraibile in qualunque app Laravel host.

### Non-goals
- Non duplicare la business logic di `padosoft/laravel-flow`. Il package consuma `FlowDashboardReadModel` + `DashboardActionAuthorizer`.
- Non spedire dati di esempio nel package: la mock data del prototipo serve solo per Playwright/dev mode (driver `array`).
- Non assumere uno stack frontend SPA. Si va di **Blade + Vite + Alpine.js** (server-rendered).

### Stack di riferimento (deciso)
| Layer | Scelta |
|-------|--------|
| PHP | `^8.3` (CI matrix `8.3` + `8.4`) |
| Laravel | `^13.0` |
| Frontend build | Vite |
| CSS | CSS vanilla con design tokens da `styles.css` (no Tailwind in v0.1 per non importare 50KB superflui — la palette è già definita) |
| JS interactivity | Alpine.js (theme toggle, command palette, drawer, tabs, polling) |
| Test PHP | PHPUnit + Orchestra Testbench (Laravel 13) |
| Test E2E | Playwright (Chromium + Firefox + Webkit) |
| Static analysis | PHPStan livello 8 |
| Code style | Laravel Pint |
| CI | GitHub Actions (PHP 8.3 + 8.4 matrix + Node 20) |

---

## 1. Operating rules (NON NEGOZIABILI)

Queste regole sono replicate in `AGENTS.md`, `CLAUDE.md`, `.claude/rules/`, `.github/copilot-instructions.md`. Cambiarle qui senza propagarle è un bug del repo.

### 1.1 Branch & PR loop
- **Una branch per macro task** (`task/<slug>`).
- Ogni **subtask** parte dalla macro branch e apre PR **verso la macro branch**.
- Quando la macro è completa → PR macro **verso `main`**.
- Su ogni PR (sub o macro):
  1. Test locali tutti verdi (PHPUnit + Vite build + Playwright + Pint + PHPStan).
  2. `gh pr create … --reviewer copilot` (con fallback GraphQL `requestReviewsByLogin` se `--reviewer copilot` fallisce — vedi `.claude/skills/copilot-pr-review-loop/SKILL.md`).
  3. Attendere CI verde (PHP 8.3 + 8.4 + Node + Playwright).
  4. Attendere commenti Copilot (2-15 min).
  5. Per ogni must-fix: fixare, push, **GOTO 3**.
  6. Merge **solo** quando: CI verde su tutti i job + Copilot review approvata o senza must-fix outstanding.
- Mai fare commit diretti su una macro branch o su `main` (eccetto release tag/changelog quando esplicitamente autorizzato).
- Mai usare `--no-verify`, `--no-gpg-sign`, `--force` su `main`.

### 1.2 Quality gates locali (eseguiti **prima** di ogni `git push`)
```bash
composer validate --strict --no-check-publish
composer format:test          # Pint --test
composer analyse              # PHPStan livello 8
composer test                 # PHPUnit (Unit + Feature + Architecture)
npm run lint                  # ESLint su resources/js
npm run build                 # Vite build (deve passare senza warning)
npm run test:e2e              # Playwright (chromium per default in dev, full matrix in CI)
```
La skill `pre-push-self-review` (importata) elenca i footgun specifici da scartare prima del push.

### 1.3 CI gates (GitHub Actions, obbligatori)
Workflow `.github/workflows/ci.yml` triggerato su PR verso `main` e `task/**` + push su `main`. Job:

| Job | Matrix | Comandi |
|-----|--------|---------|
| `php` | `php: ['8.3','8.4']` | `composer validate --strict --no-check-publish` · `composer format:test` · `composer analyse` · `composer test` |
| `frontend` | `node: ['20']` | `npm ci` · `npm run lint` · `npm run build` |
| `e2e` | `node: ['20']`, `browser: ['chromium','firefox','webkit']` | `composer install` · `php artisan serve` (Testbench skeleton) · `npm ci` · `npx playwright install --with-deps` · `npm run test:e2e -- --project=$browser` |

**Una PR è mergeable solo se TUTTI i job sono `success`.** I job sono richiesti come *required status checks* lato GitHub repo settings (manuale, una tantum, e documentato in `docs/PROGRESS.md`).

### 1.4 Documentazione obbligatoria per ogni subtask
- `docs/PROGRESS.md` — handoff durabile (ramo attivo, PR aperte, prossimo step). Aggiornato a ogni handoff/end-of-session.
- `docs/LESSON.md` — lezioni riusabili (footgun, scoperte, fix non ovvi, commenti Copilot ricorrenti). Aggiornato dopo ogni Copilot review.
- README test/coverage counts devono **sempre** corrispondere all'output reale di PHPUnit / Playwright.

### 1.5 Sicurezza
- Mai esporre token, secret, API key in log/UI/payload/audit/JSON response.
- Token di approvazione **solo hashati** lato package; il dashboard riceve l'hash via `ApprovalTokenManager::hashToken($plain)`.
- `DashboardActionAuthorizer` default = `DenyAllAuthorizer` (override esplicito richiesto in produzione).
- Ogni mutazione (resume/reject/replay/cancel/retry-webhook) passa per il authorizer + conferma UX.
- Errori operatore sanitizzati (no stack trace, no internals raw).

### 1.6 UI/UX
- **Pixel-perfect** col design `.design-source/project/`.
- Dark mode default, light mode supportato.
- Densità alta (row 40px standard, 32px tight; topbar 48px; sidebar 232px).
- Border-radius ≤ 8px.
- Mai nesting di card. Mai marketing hero. Niente landing.
- Ogni icon-button ha `aria-label` + tooltip.
- Tabular nums per numeri/durate/timestamp.

### 1.7 Compatibilità con `padosoft/laravel-flow`
- Si consuma **solo** la public API pubblica (`@api` classes/contracts):
  - `Padosoft\LaravelFlow\Dashboard\FlowDashboardReadModel`
  - `Padosoft\LaravelFlow\Dashboard\DashboardActionAuthorizer`
  - DTO: `RunDetail`, `ApprovalSummary`, `KpiSummary`
  - Status constants su `Padosoft\LaravelFlow\FlowRun`
  - Action API: `Flow::resume($token, …)`, `Flow::reject($token, …)`, `flow:replay`, `flow:deliver-webhooks`
- **Mai** type-hint o reference a `Padosoft\LaravelFlow\{Persistence,Models,Queue,Jobs,Console}\*` (sono `@internal`).

---

## 2. Macro tasks roadmap

| # | Macro task | Branch | Output principale |
|---|------------|--------|-------------------|
| 1 | Agent Operating System | `task/agent-operating-system` | `.claude/`, `AGENTS.md`, `CLAUDE.md`, `docs/{RULES,PROGRESS,LESSON}.md`, `.github/{copilot-instructions.md,PULL_REQUEST_TEMPLATE.md,workflows/ci.yml}` |
| 2 | Baseline Laravel 13 package tooling | `task/baseline-tooling` | `composer.json`, `package.json`, `vite.config.js`, `phpunit.xml`, `pint.json`, `phpstan.neon.dist`, `playwright.config.js`, src/ skeleton (ServiceProvider/config/routes), Testbench scaffold |
| 3 | Design system & layout shell | `task/design-system-shell` | `resources/css/admin.css` (token-based), `resources/js/admin.js` (Alpine bootstrap), layout Blade `views/layouts/app.blade.php` con sidebar/topbar/breadcrumbs, tema toggle persistito in cookie |
| 4 | Read model adapter & contracts | `task/read-model-adapter` | `Adapters\EloquentReadModel` + `Adapters\ArrayReadModel` (dev/test), `ViewModels\*`, dependency injection nel ServiceProvider, copertura unit test |
| 5 | Pages — Overview & Runs list | `task/pages-overview-runs` | Routes `/flow`, `/flow/runs`; Controllers; Blade views; KPIs, throughput chart inline SVG, recent runs table, filtri stato/flow/search, pagination |
| 6 | Pages — Run Detail + step viz | `task/pages-run-detail` | Route `/flow/runs/{id}`; steps panel timeline/gantt/DAG (Alpine `data-step-viz`), tab Details/Input/Output/Audit, JSON drawer, modal replay/cancel |
| 7 | Pages — Approvals, Outbox, Definitions, Settings | `task/pages-misc` | Route `/flow/approvals`, `/flow/outbox`, `/flow/definitions`, `/flow/settings`; modali approve/reject; retry webhook; success-rate bar |
| 8 | Command palette + auto-refresh + toasts | `task/cmdk-search` | Alpine ⌘K palette, fuzzy search, polling `setInterval` toggleable, toast bus globale |
| 9 | Hardening, README, release | `task/hardening-release` | README WOW (stile AskMyDocs), `CHANGELOG.md`, `UPGRADE.md`, `CONTRIBUTING.md`, `SECURITY.md`, install in fresh L13 app verificato, tag `v0.1.0` + GitHub release |
| 10 | Harvest LESSON.md → skills/rules | `task/lessons-harvest` | Aggiornamento `.claude/rules/*` e `.claude/skills/*` con i learning del progetto, patch release `v0.1.1` |

---

## 3. Subtasks dettagliati

> **Convenzione subtask**: `subtask/<macro-slug>-<n>-<short-name>`. Esempio: `subtask/agent-os-1-import-rules`.
> Ogni subtask ha **OBJ** (obiettivo), **DET** (dettagli implementativi), **GR** (guardrail/test), **EXIT** (definition of done).

### Macro 1 — Agent Operating System (`task/agent-operating-system`)

#### 1.1 — Import & adattamento rules/skills
- **OBJ**: Portare in repo le rules e skills mature da `padosoft-laravel-flow` e `product_image_discovery_admin`, adattate a `laravel-flow-admin`.
- **DET**:
  - Copiare `.claude/rules/{rule-pr-workflow.md, rule-no-debug-in-commits.md, rule-naming-conventions.md, rule-type-hints.md, rule-early-return.md, rule-frontend-js-css.md, rule-no-git-worktree.md, rule-code-structure.md}` → `.claude/rules/`.
  - Copiare `.claude/rules/laravel/{rule-laravel13-defaults.md, rule-admin-interface-structure.md, rule-admin-ajax-pattern.md, rule-domain-patterns.md, rule-form-request-dto-service-flow.md, rule-exception-handling.md, rule-logging-security.md}` → `.claude/rules/laravel/`.
  - Copiare `.claude/rules/playwright/{rule-frontend-testability-contracts.md, rule-ci-test-failure-analysis.md}` → `.claude/rules/playwright/`.
  - Copiare `.claude/skills/{copilot-pr-review-loop, pre-push-self-review, review-pr-comments, create-admin-interface, admin-interface-backend, admin-interface-frontend, admin-interface-component-audit, playwright-enterprise-tester, test-count-readme-sync, create-controller, create-test, create-service}` → `.claude/skills/`.
  - Adattare ogni skill rimuovendo riferimenti `laravel-flow-enterprise` e sostituendoli con `laravel-flow-admin` dove non pertinenti.
  - Creare nuova skill **`flow-admin-blade-component`** che spiega la convenzione partials/icons/Alpine.
- **GR**:
  - `grep -R "laravel-flow-enterprise" .claude/` non deve restituire match (eccetto file storici espliciti).
  - Ogni file in `.claude/skills/*/SKILL.md` deve avere frontmatter YAML con `name` e `description`.
  - Lint markdown: nessun link rotto (`markdown-link-check` opzionale).
- **EXIT**: PR sub mergeable; `.claude/` popolato; AGENTS.md punta ai file giusti.

#### 1.2 — AGENTS.md / CLAUDE.md / docs/RULES.md
- **OBJ**: Entrypoint stabile per agenti Claude / Codex / Copilot.
- **DET**:
  - `AGENTS.md`: read-first list (`docs/IMPLEMENTATION_PLAN.md`, `docs/PROGRESS.md`, `docs/RULES.md`, `docs/LESSON.md`, `.claude/skills/copilot-pr-review-loop/SKILL.md`, `.claude/skills/pre-push-self-review/SKILL.md`), branch list, PR loop riferito a sezione 1 di questo file.
  - `CLAUDE.md`: mirror sintetico di AGENTS.md, focus su Claude Code.
  - `docs/RULES.md`: tutte le regole sezione 1 espanse.
  - `docs/PROGRESS.md`: scheletro con sezione "Now / Next / Blocked" e "Active branches".
  - `docs/LESSON.md`: scheletro con header "## YYYY-MM-DD — <topic>".
- **GR**: file presenti, link interni risolvibili, test `tests/Architecture/DocsLinkTest.php` (PHPUnit) verifica esistenza dei file linkati.
- **EXIT**: PR sub mergeable; CI verifica esistenza file.

#### 1.3 — `.github/` (copilot-instructions, PR template, CI workflow scaffold)
- **OBJ**: Configurare GitHub per la PR loop e CI.
- **DET**:
  - `.github/copilot-instructions.md`: contesto progetto + regole review (priorità: bug status transition, security secret, UI a11y, test coverage).
  - `.github/PULL_REQUEST_TEMPLATE.md`: checklist PR (link a IMPLEMENTATION_PLAN, gates eseguiti localmente, screenshot UI, scenario Playwright nuovo).
  - `.github/workflows/ci.yml`: 3 job (php matrix, frontend, e2e matrix browser) — pipeline definita in §1.3.
  - `.github/CODEOWNERS`: `* @lopadova`.
  - `.github/dependabot.yml`: weekly su composer + npm + github-actions.
- **GR**:
  - Workflow YAML validato con `actionlint` (run su CI stesso o pre-push).
  - PR template parsabile da `gh pr create --body-file`.
- **EXIT**: PR sub mergeable; aprire la prima sub PR in modo che CI parta sul template.

---

### Macro 2 — Baseline Laravel 13 Package Tooling (`task/baseline-tooling`)

#### 2.1 — composer.json + Pint + PHPStan + PHPUnit + Testbench
- **OBJ**: Tooling PHP completo allineato a `padosoft/laravel-flow`.
- **DET**:
  - `composer.json`:
    ```json
    {
      "name": "padosoft/laravel-flow-admin",
      "description": "Professional UI/UX admin panel for padosoft/laravel-flow",
      "license": "Apache-2.0",
      "require": {
        "php": "^8.3",
        "illuminate/support": "^13.0",
        "illuminate/routing": "^13.0",
        "illuminate/view": "^13.0",
        "padosoft/laravel-flow": "^1.0"
      },
      "require-dev": {
        "orchestra/testbench": "^10.0",
        "phpunit/phpunit": "^11.0",
        "laravel/pint": "^1.18",
        "phpstan/phpstan": "^2.0",
        "larastan/larastan": "^3.0"
      },
      "autoload": { "psr-4": { "Padosoft\\LaravelFlowAdmin\\": "src/" } },
      "autoload-dev": { "psr-4": { "Padosoft\\LaravelFlowAdmin\\Tests\\": "tests/" } },
      "extra": {
        "laravel": {
          "providers": ["Padosoft\\LaravelFlowAdmin\\FlowAdminServiceProvider"]
        }
      },
      "scripts": {
        "format": "pint",
        "format:test": "pint --test",
        "analyse": "phpstan analyse --memory-limit=2G",
        "test": "phpunit"
      }
    }
    ```
  - `pint.json` (Laravel preset).
  - `phpstan.neon.dist` livello 8 + larastan extension.
  - `phpunit.xml` con suite Unit / Feature / Architecture.
  - `tests/TestCase.php` estende `Orchestra\Testbench\TestCase` e registra `FlowAdminServiceProvider` + `Padosoft\LaravelFlow\FlowServiceProvider`.
- **GR**: `composer validate --strict --no-check-publish` verde. `composer test` esegue una suite vuota verde (placeholder test).
- **EXIT**: PR sub mergeable; CI php matrix verde.

#### 2.2 — Service Provider, config, routes
- **OBJ**: Skeleton package Laravel.
- **DET**:
  - `src/FlowAdminServiceProvider.php`: registra config `flow-admin.php`, view namespace `flow-admin`, route file `routes/flow-admin.php`, asset publish (`--tag=flow-admin-assets`), config publish (`--tag=flow-admin-config`), view publish (`--tag=flow-admin-views`).
  - `config/flow-admin.php`: `prefix` (default `flow`), `middleware` (default `['web','auth']`), `authorizer` class binding, `polling_interval_ms` (default `4000`), `theme_default` (`dark`), `step_viz_default` (`timeline`).
  - `routes/flow-admin.php`: gruppo route con prefix/middleware presi da config; placeholder GET `/` → `OverviewController@index`.
  - `src/Http/Controllers/OverviewController.php`: stub che ritorna `view('flow-admin::pages.overview', [])`.
  - `resources/views/pages/overview.blade.php`: stub `<h1>Flow Admin</h1>`.
- **GR**:
  - Test `tests/Feature/ServiceProviderTest.php`: provider register → config caricato, route registrata, view namespace risolvibile.
  - Test architettura: nessuna classe in `src/` referenzia namespace `@internal` di `padosoft/laravel-flow`.
- **EXIT**: PR sub mergeable.

#### 2.3 — Vite + Alpine + ESLint + Playwright bootstrap
- **OBJ**: Tooling frontend completo.
- **DET**:
  - `package.json`: `dev`, `build`, `lint`, `test:e2e`, `test:e2e:headed`. Deps: `vite`, `alpinejs`, `eslint`, `@playwright/test`.
  - `vite.config.js`: input `resources/css/admin.css` + `resources/js/admin.js`, output `public/vendor/flow-admin/`.
  - `resources/js/admin.js`: import Alpine + start.
  - `resources/css/admin.css`: stub iniziale (sarà popolato in Macro 3).
  - `playwright.config.js`: `baseURL` da env, projects chromium/firefox/webkit, screenshot only-on-failure.
  - `tests/e2e/smoke.spec.js`: scenario base `expect(page.url()).toContain('/flow')`.
  - `scripts/serve-testbench.mjs` o equivalente per esporre l'app Testbench durante i test E2E.
- **GR**:
  - `npm run build` produce manifest + asset.
  - `npm run lint` verde.
  - `npm run test:e2e` esegue lo smoke (Testbench server boot + Playwright).
- **EXIT**: PR sub mergeable; CI frontend + e2e job verdi.

---

### Macro 3 — Design System & Layout Shell (`task/design-system-shell`)

#### 3.1 — Port `styles.css` con design tokens
- **OBJ**: Replicare il sistema di token light/dark del prototipo dentro `resources/css/admin.css`.
- **DET**:
  - Copiare integralmente i blocchi `:root` e `[data-theme="dark"]` da `.design-source/project/styles.css`.
  - Estrarre componenti CSS (sidebar, topbar, kpi, badge, table, modal, drawer, palette, toast, step list, gantt, dag, charts) — manteniamo classi identiche per facilitare port pixel-perfect.
  - Importare font Geist via Google Fonts (CSS @import) come nel prototipo.
- **GR**:
  - Visual regression Playwright: snapshot di `/flow` (pagina vuota con layout) confrontato vs reference (tolerance 0.1).
  - Lint CSS: `stylelint` con regole standard (no errori).
- **EXIT**: PR sub mergeable.

#### 3.2 — Layout Blade (`layouts/app.blade.php`) + sidebar + topbar + breadcrumbs
- **OBJ**: Shell server-rendered che replica `shell.jsx`.
- **DET**:
  - `resources/views/layouts/app.blade.php`: html con `data-theme`, slot `@yield('content')`, include `partials.sidebar`, `partials.topbar`.
  - `partials/sidebar.blade.php`: nav Operate (Overview/Runs/Approvals/Outbox) + Configure (Definitions/Settings) + footer user chip. Badge counters da view-model.
  - `partials/topbar.blade.php`: breadcrumbs, live-pill, search-trigger, icon buttons (auto-refresh, notifications, theme toggle).
  - Icone come componenti Blade `<x-flow-admin::icon name="home" />` (set lucide-style esportato in `resources/views/components/icon.blade.php`, switch su `name`).
  - Theme toggle persistito in cookie `flow_admin_theme` (Alpine + ricarica server-side per data-theme).
- **GR**:
  - Test feature: GET `/flow` → 200 + contiene `data-theme="dark"`.
  - Playwright `tests/e2e/shell.spec.js`:
    - Sidebar nav clicca Runs → URL cambia
    - Theme toggle → `html[data-theme]` cambia
    - Breadcrumbs renderizzati correttamente
    - Live-pill visibile
    - Cmd+K trigger button visibile (palette implementata in Macro 8 — ora solo placeholder)
- **EXIT**: PR sub mergeable.

---

### Macro 4 — Read Model Adapter & Contracts (`task/read-model-adapter`)

#### 4.1 — `ViewModels` (data shape per le views)
- **OBJ**: DTO immutabili che mappano le DTO di `padosoft/laravel-flow` su shape adatte alle view Blade.
- **DET**:
  - `src/ViewModels/RunRow.php`, `StepRow.php`, `KpiTile.php`, `ThroughputBucket.php`, `ApprovalCard.php`, `OutboxRow.php`, `DefinitionRow.php`, `AuditEvent.php`, `RunDetailViewModel.php`.
  - Ogni VM ha factory statica `fromDto(...)` che accetta il DTO del package.
- **GR**: Unit test 1:1 (snapshot DTO → VM).
- **EXIT**: PR sub mergeable.

#### 4.2 — `EloquentReadModel` adapter
- **OBJ**: Implementazione default di `FlowDashboardReadModel` che legge dalle tabelle `flow_*` via `padosoft/laravel-flow`.
- **DET**:
  - `src/Adapters/EloquentReadModel.php` implementa `FlowDashboardReadModel`.
  - Methods: `listRuns`, `findRun`, `listApprovals`, `pendingApprovals`, `listWebhookOutbox`, `kpis`.
  - Bind nel ServiceProvider come default (override-able via config).
  - Eager loading per evitare N+1.
  - Filtri opzionali (status, flow_def, query) accettati come parametri aggiuntivi.
- **GR**:
  - Feature test con DB SQLite + migrations di `padosoft/laravel-flow` (publish + migrate).
  - PHPStan livello 8 verde.
  - Test architettura: nessun reference a `Padosoft\LaravelFlow\Persistence` o `Models` da fuori dell'adapter.
- **EXIT**: PR sub mergeable.

#### 4.3 — `ArrayReadModel` (dev/test/Playwright)
- **OBJ**: Implementazione in-memory che restituisce la mock data del prototipo, per Playwright e demo.
- **DET**:
  - `src/Adapters/ArrayReadModel.php` carica `resources/fixtures/runs.json` (port di `data.jsx`).
  - Selezionabile via `config('flow-admin.adapter') === 'array'` o env `FLOW_ADMIN_ADAPTER=array`.
  - Generatore deterministico (seed 42) per fixtures riproducibili.
- **GR**:
  - Unit test: 120 runs generati, distribuzione status compatibile con il prototipo.
  - Playwright usa questo driver in CI per test E2E senza dipendere da DB seed.
- **EXIT**: PR sub mergeable.

#### 4.4 — `DashboardActionAuthorizer` integration
- **OBJ**: Wrapper sicuro per le mutazioni.
- **DET**:
  - Risolto via container; binding default = `DenyAllAuthorizer` del package.
  - Helper `src/Support/Authorize.php::action(string $action, callable $exec)` centralizza check + log.
  - Eccezioni `AuthorizationException` mappate a 403 con view dedicata (no leak).
- **GR**: Feature test che 403 sia restituito quando `DenyAllAuthorizer` attivo; 200 con `AllowAllAuthorizer` (dev-only).
- **EXIT**: PR sub mergeable.

---

### Macro 5 — Pages Overview & Runs (`task/pages-overview-runs`)

#### 5.1 — Overview page (route `/flow`)
- **OBJ**: Replica pixel-perfect di `page-overview.jsx`.
- **DET**:
  - Controller `OverviewController@index`: chiama `kpis()`, `listRuns(perPage:8)`, `pendingApprovals()`, `listRuns(filter:'failed', perPage:5)`, `getHourly()` (helper su read model o derivato).
  - View `pages/overview.blade.php` + `partials/{kpi-tile,throughput-chart,recent-runs,pending-approvals,recent-failures}.blade.php`.
  - Sparkline e throughput chart inline SVG (no librerie, replica della funzione `Sparkline` JSX).
- **GR**:
  - Feature test: 200 + tutti gli elementi DOM principali presenti.
  - Playwright `tests/e2e/overview.spec.js`:
    - 4 KPI tiles renderizzate con valori deterministici (driver array)
    - Throughput chart con 24 colonne
    - "Recent runs" tabella con ≥ 1 row
    - Click su row "Recent runs" → naviga a `/flow/runs/<id>`
    - "View all" → `/flow/runs`
    - Snapshot visual full-page (tolerance 0.05)
- **EXIT**: PR sub mergeable.

#### 5.2 — Runs list (route `/flow/runs`)
- **OBJ**: Replica `page-runs.jsx`.
- **DET**:
  - Controller `RunsController@index`: filtri da query string (`status`, `flow`, `q`, `page`).
  - Filter chips re-render server-side (link con query string).
  - Paginazione con `LengthAwarePaginator`.
  - View `pages/runs.blade.php`.
- **GR**:
  - Feature test: filtri funzionano (es. `?status=running` → solo running).
  - Playwright:
    - Apertura `/flow/runs` mostra ≥ 25 row
    - Click chip "running" → URL `?status=running`, riga count ≥ 1
    - Cambio select flow → query string aggiornata
    - Search input → debounce 300ms (Alpine), aggiorna risultati
    - Pagination Next → `?page=2`
    - Click row → `/flow/runs/<id>`
    - Snapshot visual
- **EXIT**: PR sub mergeable.

---

### Macro 6 — Run Detail Page (`task/pages-run-detail`)

#### 6.1 — Skeleton run detail
- **OBJ**: Route `/flow/runs/{id}` con header, steps panel, detail pane (tab Details), JSON drawer.
- **DET**:
  - Controller `RunDetailController@show($id)` chiama `findRun($id)` → `RunDetailViewModel`.
  - View `pages/run-detail.blade.php` con grid 1.4fr.
  - Steps timeline (default), tab "Details" attivo.
  - Drawer JSON apribile (Alpine `x-show`).
- **GR**:
  - Feature test: 200, header con flow name e status badge.
  - Playwright `tests/e2e/run-detail.spec.js`:
    - Apertura via `/flow/runs/<id>` da fixture array
    - Step 1 selezionato di default
    - Click step 2 → tab Details mostra handler classname
    - Drawer JSON → click "JSON" → drawer visibile, contenuto pretty-printed
    - Snapshot visual
- **EXIT**: PR sub mergeable.

#### 6.2 — Step viz toggle (Timeline / Gantt / DAG)
- **OBJ**: Cambio runtime di `data-step-viz` su Alpine + persistenza cookie.
- **DET**:
  - Tweak panel inferiore (riproduzione di `tweaks-panel.jsx`) o select inline nel header del card "Steps".
  - Cookie `flow_admin_step_viz` (preferito persistente).
- **GR**: Playwright: cambio viz → attribute `data-step-viz` cambia su body; reload → preferenza persistita.
- **EXIT**: PR sub mergeable.

#### 6.3 — Tab Input/Output/Audit
- **OBJ**: Tab interattivi con codice JSON evidenziato e timeline audit.
- **DET**:
  - Helper Blade `@jsonHighlight($payload)` per syntax highlight server-side (port di `jsonHighlight` JS).
  - Audit list in DOM da view-model (no client fetch — già preloaded).
- **GR**: Playwright: click su ogni tab → contenuto cambia, classi `.json-key/.json-string/.json-num/.json-bool` presenti.
- **EXIT**: PR sub mergeable.

#### 6.4 — Modali Replay & Cancel
- **OBJ**: Conferma azioni distruttive con interazione `DashboardActionAuthorizer`.
- **DET**:
  - Form POST `/flow/runs/{id}/replay`, `/flow/runs/{id}/cancel` con CSRF.
  - Controller esegue azione tramite `Flow::replay()` / cancellation primitive del package, con authorizer check.
  - Toast on success (Alpine).
- **GR**:
  - Feature test: 403 con DenyAllAuthorizer; 200 con AllowAllAuthorizer in dev.
  - Playwright: Replay → modal apre, form valido → toast "Replay queued".
- **EXIT**: PR sub mergeable.

---

### Macro 7 — Pages Approvals / Outbox / Definitions / Settings (`task/pages-misc`)

#### 7.1 — Approvals (route `/flow/approvals`)
- **DET**: lista `pendingApprovals()`; modali Approve (token input) + Reject (motivo); POST `/flow/approvals/{id}/approve|reject` → `Flow::resume|reject`.
- **GR**: Playwright: approve modal valida token non vuoto; reject richiede motivo; conferma → toast + lista aggiornata.

#### 7.2 — Outbox (route `/flow/outbox`)
- **DET**: tabella outbox con filtri status; bottone Retry (POST `/flow/outbox/{id}/retry`) protetto da authorizer.
- **GR**: Playwright: filtri chip funzionanti; click Retry → optimistic UI + toast.

#### 7.3 — Definitions (route `/flow/definitions`)
- **DET**: list flow definitions registrate (helper su read model `definitions()` da aggiungere); barra success/failed/running.
- **GR**: Playwright: tabella con N rows = N definitions, success rate % corretto.

#### 7.4 — Settings (route `/flow/settings`)
- **DET**: read-only view di `config('flow-admin.*')` + `config('laravel-flow.*')` (no secret).
- **GR**: Playwright: pagina renderizza 4 card; nessun secret visibile (regex test su markup).

---

### Macro 8 — ⌘K Palette + Auto-refresh + Toasts (`task/cmdk-search`)

#### 8.1 — Command Palette (Alpine)
- **DET**:
  - Trigger: bottone topbar + scorciatoia tastiera ⌘K / Ctrl+K.
  - Lista navigation + recent runs + fuzzy search via endpoint JSON `/flow/api/search?q=...` (debounce 200ms).
  - Selezione tastiera ↑↓ + Enter + Esc.
- **GR**: Playwright:
  - Ctrl+K apre palette
  - Type "ord" → almeno 1 risultato
  - Enter → navigazione corretta
  - Esc → chiude

#### 8.2 — Auto-refresh polling
- **DET**:
  - Componente Alpine `livePoll` con `setInterval` configurabile (default 4000ms da config).
  - Bottone Pause/Resume in topbar.
  - Endpoint JSON `/flow/api/live` ritorna delta (KPIs + count).
- **GR**: Playwright: dopo Pause, count non cambia; dopo Resume, lastTick incrementa.

#### 8.3 — Toast bus globale
- **DET**: Alpine store `flowToasts` + componente partial.
- **GR**: Unit Vitest opzionale; Playwright: toast appare e svanisce dopo 3.6s.

---

### Macro 9 — Hardening, README, release (`task/hardening-release`)

#### 9.1 — Full Playwright suite & coverage map
- **DET**: ogni rotta + ogni endpoint API → almeno 1 scenario Playwright. Tabella di copertura in `tests/e2e/COVERAGE.md`.
- **GR**: CI matrix browser (chromium/firefox/webkit) tutto verde.

#### 9.2 — README WOW (stile AskMyDocs) + AI Vibe Coding Pack + Screenshots
- **OBJ**: README di livello community-ready, ispirato a [`lopadova/AskMyDocs`](https://github.com/lopadova/AskMyDocs/blob/main/README.md), che vende il package, mostra la UI **da subito** con uno screenshot hero, e include esplicitamente il **"AI Vibe Coding Pack"** che spediamo nel repo (`.claude/{rules,skills,agents,commands,instructions}/`) come bonus per chi adotta il package.

- **Ordine sezioni README (canonico, NON cambiare senza aggiornare il piano):**
  1. Titolo `# 🪄 Laravel Flow Admin` + tagline.
  2. Badge open-source / community (composer downloads, license Apache-2.0, CI status, PHP `^8.3`, Laravel `^13`, latest tag, GitHub stars, GitHub Discussions, "good first issue" count).
  3. **Hero screenshot dashboard** subito sotto i badge:
     ```markdown
     ![Laravel Flow Admin — Dashboard overview](resources/screenshoots/laravel-flow-admin-dashboard.png)
     ```
     (Il file `resources/screenshoots/laravel-flow-admin-dashboard.png` è già presente nel repo, salvato dall'utente — è la dashboard finale renderizzata in dark mode.)
  4. **TOC** — Table of Contents con ancore a tutte le sezioni successive.
  5. Why this exists / What it does (3-4 paragrafi).
  6. **📸 Screenshots** (la galleria — vedi sotto).
  7. Quick install / Quick start.
  8. Configuration.
  9. Usage examples (custom authorizer, switch adapter, consume read model).
  10. **🤖 AI Vibe Coding Pack included** (vedi sotto).
  11. Comparison vs alternatives.
  12. Roadmap (v0.1 / v0.2 / v0.3 / v1.0).
  13. Test & quality counts.
  14. Contributing → `CONTRIBUTING.md`.
  15. Security → `SECURITY.md`.
  16. ⭐ Community / Sponsor.
  17. License — Apache-2.0.

- **📸 Sezione Screenshots (DET)**:
  - Tutti i file in `resources/screenshoots/` vengono inclusi. Manteniamo nome cartella `screenshoots/` per non rompere link già condivisi; in `LESSON.md` annotare il typo.
  - Layout: griglia 2 colonne markdown table per non sforare la larghezza GitHub. Caption breve sotto ogni screenshot. Esempio:
    ```markdown
    ## 📸 Screenshots

    | Overview & KPIs | Runs list with filters |
    | --- | --- |
    | ![Overview](resources/screenshoots/laravel-flow-admin-dashboard.png) | ![Runs](resources/screenshoots/laravel-flow-admin-runs.png) |
    | _Live KPIs, throughput chart, recent runs/approvals/failures._ | _25-row dense table, status chips, fuzzy search, server-side pagination._ |

    | Run detail (timeline + tabs) | Approvals queue |
    | --- | --- |
    | ![Run detail](resources/screenshoots/laravel-flow-admin-run-detail.png) | ![Approvals](resources/screenshoots/laravel-flow-admin-approvals.png) |
    | _Steps timeline/Gantt/DAG, Details/Input/Output/Audit tabs, JSON drawer._ | _Approve & resume / reject & terminate flow with TTL'd token + reason._ |

    | Webhook outbox | Flow definitions |
    | --- | --- |
    | ![Outbox](resources/screenshoots/laravel-flow-admin-webhook-outbox.png) | ![Definitions](resources/screenshoots/laravel-flow-admin-webhook-flow-definitions.png) |
    | _Pending / delivered / dead-letter with attempt history and retry._ | _Registered flows with success-rate progress bar and activity sparkline._ |

    | Configuration / Settings |  |
    | --- | --- |
    | ![Configuration](resources/screenshoots/laravel-flow-admin-configuration.png) |  |
    | _Read-only authorizer / retention / webhook signing / queue snapshot — never exposes secrets._ |  |
    ```
  - Lazy load: usare `<picture>` con `loading="lazy"` solo se README rendering supporta HTML (GitHub sì); fallback ai `![]()` markdown standard se l'HTML non si applica.
  - I 7 file sono attualmente: `laravel-flow-admin-dashboard.png`, `laravel-flow-admin-runs.png`, `laravel-flow-admin-run-detail.png`, `laravel-flow-admin-approvals.png`, `laravel-flow-admin-webhook-outbox.png`, `laravel-flow-admin-webhook-flow-definitions.png`, `laravel-flow-admin-configuration.png`. Se in Macro 9 la UI ha divergenze, ri-generare gli screenshot via Playwright `--update-snapshots` e tenere lo stesso file-name pattern per non rompere i link.

- **Restanti DET**:
  - Quick install (`composer require padosoft/laravel-flow-admin`), publish tag, route mount, env defaults.
  - Esempi codice: come bindare un custom `DashboardActionAuthorizer`, come switchare adapter (`eloquent` vs `array`), come consumare il read model in un'app host.
  - **Sezione "🤖 AI Vibe Coding Pack included"**: descrive il contenuto di `.claude/` (rules per Laravel 13/admin/Playwright, skills per Copilot PR loop / pre-push self-review / playwright enterprise / admin interface / test-count sync, agents per admin-interface-architect e playwright-enterprise-tester, commands), spiega che è gratis quando installi il package via Composer (anche se in dist `.claude/` è `export-ignore`d — i contributors lo trovano clonando il repo). Include il "come copiarlo nella tua app host" e link al canonical statement della Copilot+CI loop in `.claude/skills/copilot-pr-review-loop/SKILL.md`. Linka chiaramente la roadmap di adozione.
  - Sezione **"Comparison vs alternatives"** vs Laravel Horizon (focus jobs vs flows), Laravel Pulse (focus app metrics vs workflow runs), Symfony Workflow + dashboard ad-hoc, Temporal UI. Capability cells in formato `✅ YES — …`, `⚠️ PARTIAL — …`, `❌ NO — …`.
  - Sezione **Test & quality counts** sincronizzata con output reale di `composer test` e `npm run test:e2e --reporter=line` tramite skill `test-count-readme-sync`.
  - Sezione **Roadmap** (v0.1 / v0.2 / v0.3 / v1.0 con checkbox).
  - Sezioni Contributing → `CONTRIBUTING.md`, Security → `SECURITY.md`, License → Apache-2.0.
  - Sezione **"⭐ Community"** con call-to-action: GitHub Discussions, issue templates, Twitter/X handle Padosoft, link al companion package `padosoft/laravel-flow`.
- **GR**:
  - Markdown link check superato (no link rotti — incluso `resources/screenshoots/*.png`).
  - Tutti i 7 PNG in `resources/screenshoots/` referenziati almeno una volta nel README; nessun PNG orfano (script di check in `scripts/check-readme-screenshots.mjs` da aggiungere in Macro 9).
  - Hero screenshot deve essere `resources/screenshoots/laravel-flow-admin-dashboard.png`, posizionato fra badge e TOC (assert tramite test markdown).
  - Nessun `TODO`/`FIXME`/`XXX` residuo nel README.
  - Anchor link a heading con emoji testati (`grep -n '#-' README.md` → 0 match — vedi LESSON.md su slug emoji).
  - Counts e screenshot ri-generati come ultima azione prima del tag v0.1.0; se la UI è ancora pixel-perfect rispetto agli screenshot iniziali, riusare i file esistenti per stabilità del README.
  - `resources/screenshoots/` NON è in `.gitattributes` `export-ignore`: i PNG vanno spediti nel dist Composer perché il README pubblicato su Packagist li rendera.

#### 9.3 — CHANGELOG / UPGRADE / CONTRIBUTING / SECURITY / CODE_OF_CONDUCT
- **DET**: replica struttura `padosoft-laravel-flow`.
- **GR**: validati a mano + linter markdown.

#### 9.4 — Verifica install in fresh Laravel 13 app
- **DET**:
  - Script `scripts/smoke-install.ps1` che crea `composer create-project laravel/laravel test-app`, aggiunge path repo locale, installa il package, esegue migrations, apre `/flow`.
- **GR**: Smoke install verde sia in locale sia in CI (job dedicato `install-smoke`).

#### 9.5 — Tag v0.1.0 + GitHub release
- **DET**:
  - Bumpare `composer.json` + `CHANGELOG.md`.
  - `git tag v0.1.0` su `main` post-merge macro.
  - `gh release create v0.1.0 --notes-file CHANGELOG.md` (estratto sezione corrispondente).
- **GR**: Release visibile su GitHub; `composer require padosoft/laravel-flow-admin:^0.1` risolvibile (post-publish Packagist).

---

### Macro 10 — Harvest LESSON.md → rules/skills (`task/lessons-harvest`)

#### 10.1 — Audit LESSON.md
- **DET**: rileggere ogni voce in `docs/LESSON.md`; categorizzare in (a) rule, (b) skill, (c) checklist pre-push, (d) docs.
- **GR**: tabella export in `docs/LESSON_HARVEST_2026-MM-DD.md`.

#### 10.2 — Aggiornare `.claude/rules/*` e `.claude/skills/*`
- **DET**: ogni learning categorizzato (a)/(b)/(c) propagato. Skill `pre-push-self-review` aggiornata con nuovi footgun.
- **GR**: `grep` di esistenza per ciascun learning citato; PR diff review che prova la propagazione.

#### 10.3 — Patch release v0.1.1
- **DET**: include solo doc/skill changes; tag + release.
- **GR**: CI verde.

---

## 4. Definition of Done globale (per l'intero progetto)

Il progetto si dichiara "v0.1.0 done" se e solo se:

1. ✅ Tutte e 10 le macro PR sono mergeate su `main` con CI verde.
2. ✅ `composer test` su `main` riporta **0 failures**, count assertions sincronizzato con README.
3. ✅ `npm run build` produce manifest pulito.
4. ✅ Playwright matrix (chromium + firefox + webkit) tutto verde su CI.
5. ✅ `composer require padosoft/laravel-flow-admin:^0.1.0` su Laravel 13 app vuota:
   - install OK, no warning autoload
   - `php artisan vendor:publish --tag=flow-admin-config` produce `config/flow-admin.php`
   - GET `/flow` (con `AllowAllAuthorizer` in dev) ritorna 200 e renderizza UI
6. ✅ README ha hero, screenshots, badges, install, comparison; nessun TODO.
7. ✅ Tag `v0.1.0` + GitHub release notes.
8. ✅ `docs/LESSON.md` harvested in `.claude/rules` e `.claude/skills`.
9. ✅ `docs/PROGRESS.md` chiuso ("status: shipped v0.1.0; next: maintenance").

---

## 5. Convenzioni operative riassunte (cheat-sheet)

```text
git checkout -b task/<macro-slug>          # macro
git checkout -b subtask/<macro>-N-<name>   # subtask (parte da macro)

# Pre-push gate (sempre)
composer validate --strict --no-check-publish
composer format:test
composer analyse
composer test
npm run lint
npm run build
npm run test:e2e

# Apertura PR
gh pr create --base task/<macro> --head subtask/... \
  --title "<scope>: <subject>" \
  --body-file .github/PULL_REQUEST_TEMPLATE.md \
  --reviewer copilot

# Loop Copilot+CI (vedi .claude/skills/copilot-pr-review-loop/SKILL.md)
# - attendi 60-180s, leggi review/CI, fix, push, ripeti

# Merge solo a Copilot=APPROVED + CI=success
gh pr merge <N> --squash
```

---

## 6. Riferimenti

- Design source: `.design-source/` (estratto dal handoff Claude Design `eZVMtDE08LRjCs4EOsNgzg`).
- Package backend: [`padosoft/laravel-flow`](https://github.com/padosoft/laravel-flow) (`v1.0`).
- Reference repo (rules/skills, public): [`padosoft/laravel-flow`](https://github.com/padosoft/laravel-flow) — `.claude/` folder is the source of the imported rules and skills baseline.
- Reference repo (admin app pattern, internal): the `padosoft/product-image-discovery-admin` workspace contributors maintain — see internal docs in that repo for the per-task gate cadence we mirror here. Not linkable from a public package.
- README ispirazionale: [`lopadova/AskMyDocs/README.md`](https://github.com/lopadova/AskMyDocs/blob/main/README.md).
