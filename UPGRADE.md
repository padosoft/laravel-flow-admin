# Upgrade Guide

## Upgrading to 0.1.0

1. Install package:
```bash
composer require padosoft/laravel-flow-admin:^0.1
```
2. Publish config/views/assets:
```bash
php artisan vendor:publish --tag=flow-admin-config
php artisan vendor:publish --tag=flow-admin-views
php artisan vendor:publish --tag=flow-admin-assets
```
3. Verify `config/flow-admin.php`:
- `prefix`
- `middleware`
- `adapter`
- `authorizer`
- `polling_interval_ms`
- `theme_default`
- `step_viz_default`

4. If you rely on action mutations, bind your own `ActionAuthorizer` implementation.
