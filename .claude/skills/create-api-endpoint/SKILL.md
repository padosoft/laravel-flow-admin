---
name: create-api-endpoint
description: Use when creating a JSON endpoint under `/flow/api/*` (e.g. `/flow/api/search`, `/flow/api/live`) backing the admin UI. Covers route registration, FormRequest validation, JSON resource shape, authorizer gating, CSRF or session-based auth (no Sanctum tokens for browser-held UI), pagination contract, and Feature test coverage. Trigger when adding/modifying any AJAX or polling endpoint in `routes/flow-admin.php`.
---

# Create API Endpoint

Skill per creare endpoint API moderni in Laravel 13.

## Pipeline consigliata

1. route
2. controller sottile
3. FormRequest
4. DTO
5. service/action
6. JsonResource o ResourceCollection
7. feature test HTTP

## Regole

- validazione in `FormRequest`
- nessuna logica di business nel controller
- usare `JsonResource` per shaping stabile della response
- status code coerenti
- error handling prevedibile

## Quando usare Resource

- singolo record: `JsonResource`
- lista paginata o collezione: `ResourceCollection` o resource collection dedicata
