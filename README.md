# Laravel Flow Admin

Professional Blade + Alpine admin panel for `padosoft/laravel-flow`.

[![CI](https://github.com/padosoft/laravel-flow-admin/actions/workflows/ci.yml/badge.svg)](https://github.com/padosoft/laravel-flow-admin/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-%5E8.3-777bb4)
![Laravel](https://img.shields.io/badge/Laravel-%5E13.0-ff2d20)
![License](https://img.shields.io/badge/License-Apache--2.0-blue)

![Laravel Flow Admin — Dashboard overview](resources/screenshoots/laravel-flow-admin-dashboard.png)

## Table of Contents
- [Why](#why)
- [Screenshots](#screenshots)
- [Install](#install)
- [Configuration](#configuration)
- [Usage](#usage)
- [AI Vibe Coding Pack](#ai-vibe-coding-pack)
- [Comparison](#comparison)
- [Roadmap](#roadmap)
- [Quality](#quality)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

## Why
`padosoft/laravel-flow` is intentionally headless. This package gives operators a production-style control plane for runs, approvals, outbox and configuration without coupling to internal engine namespaces.

## Screenshots

| Overview & KPIs | Runs list |
| --- | --- |
| ![Overview](resources/screenshoots/laravel-flow-admin-dashboard.png) | ![Runs](resources/screenshoots/laravel-flow-admin-runs.png) |

| Run detail | Approvals |
| --- | --- |
| ![Run detail](resources/screenshoots/laravel-flow-admin-run-detail.png) | ![Approvals](resources/screenshoots/laravel-flow-admin-approvals.png) |

| Webhook outbox | Definitions |
| --- | --- |
| ![Outbox](resources/screenshoots/laravel-flow-admin-webhook-outbox.png) | ![Definitions](resources/screenshoots/laravel-flow-admin-webhook-flow-definitions.png) |

| Configuration |  |
| --- | --- |
| ![Configuration](resources/screenshoots/laravel-flow-admin-configuration.png) |  |

## Install
```bash
composer require padosoft/laravel-flow-admin
php artisan vendor:publish --tag=flow-admin-config
php artisan vendor:publish --tag=flow-admin-views
php artisan vendor:publish --tag=flow-admin-assets
```

## Configuration
Main keys in `config/flow-admin.php`:
- `prefix`
- `middleware`
- `adapter` (`eloquent` or `array`)
- `authorizer`
- `polling_interval_ms`
- `theme_default`
- `step_viz_default`

## Usage
Bind your own authorizer in host app:
```php
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use App\Flow\AdminAuthorizer;

$this->app->bind(ActionAuthorizer::class, AdminAuthorizer::class);
```

Switch adapter for demos/e2e:
```env
FLOW_ADMIN_ADAPTER=array
```

## AI Vibe Coding Pack
This repository ships a contributor pack under `.claude/`:
- rules for Laravel 13 + admin panel patterns
- skills for Copilot review loop and pre-push checks
- command/runbooks used during implementation

## Comparison
- `Laravel Horizon`: ⚠️ PARTIAL — queue/job ops, not workflow run lifecycle.
- `Laravel Pulse`: ⚠️ PARTIAL — app metrics, not run-level flow operations.
- `Custom dashboard`: ✅ YES — possible, but slower to bootstrap and standardize.
- `Temporal UI`: ⚠️ PARTIAL — great for Temporal, not a drop-in for Laravel Flow.

## Roadmap
- [x] v0.1 core pages + adapters + shell
- [x] v0.1 macro8 runtime (palette, polling, toasts)
- [ ] v0.1 release hardening and public release workflow
- [ ] v0.1.1 lessons harvest into reusable rules/skills

## Quality
Current local gate status:
- `composer test`: 101 tests / 584 assertions
- `npm run test:e2e`: 18 passed / 3 visual-gated skipped

## Contributing
See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security
See [SECURITY.md](SECURITY.md).

## License
Apache-2.0. See [LICENSE](LICENSE).
