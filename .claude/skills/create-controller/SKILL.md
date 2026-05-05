---
name: create-controller
description: Use to introduce a new Laravel controller in this package following the FormRequest → DTO → Service → ViewModel → Blade pipeline. Covers route registration in `routes/flow-admin.php`, view namespace usage (`flow-admin::`), CSRF on POST forms, authorizer gating for mutations, and Feature test coverage via Testbench. Trigger when adding/modifying any class under `src/Http/Controllers/`.
---

# Create Controller

Pattern base per introdurre un controller Laravel.

Target: Laravel 13.x, PHP 8.3+.

## Checklist

- scegli se e' web, api o admin
- sposta validazione in Form Request se non e' banale
- se il caso d'uso ha payload ricco, costruisci un DTO o command object esplicito
- delega il lavoro a un service/action
- restituisci view, resource o redirect coerente
- scrivi almeno un feature test per il comportamento principale
