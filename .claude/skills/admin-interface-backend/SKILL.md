---
name: admin-interface-backend
description: Use to implement the backend slice of a complex Laravel admin page (controller, view-model factories, query layer with eager loading, FormRequest validation, action handlers gated by authorizer, pagination/sorting/filtering DTOs). Trigger when adding/modifying any controller under `src/Http/Controllers/` or `routes/flow-admin.php`, when wiring a new admin route, or when working on a Macro 5/6/7 backend slice per `docs/IMPLEMENTATION_PLAN.md`.
---

# Admin Interface Backend

Skill per implementare il backend di una pagina admin Laravel complessa.

## Obiettivo

Preparare:

- Request/validator
- DTO o filter object
- service di query o aggregation
- controller thin
- route
- eventuale export

## Sequenza

1. definire filtri e vincoli
2. progettare il contratto JSON o view data
3. creare service di lettura
4. aggiungere controller e route
5. scrivere test del service e feature test minimi

## Regole

- niente query complesse in controller
- struttura di output stabile per il frontend
- validazione esplicita per limiti, range date, filtri multipli
