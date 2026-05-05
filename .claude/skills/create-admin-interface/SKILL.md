---
name: create-admin-interface
description: Orchestrator skill for creating or refactoring complex Laravel admin pages with KPI tiles, filters, tables, drawers, modals, charts, exports. Coordinates the audit → backend → frontend → test phases by dispatching to `admin-interface-component-audit`, `admin-interface-backend`, `admin-interface-frontend`, and `playwright-enterprise-tester`. Trigger when user asks to "build an admin page", "add a dashboard view", "refactor the admin shell", or when starting any Macro 5/6/7 page implementation in `docs/IMPLEMENTATION_PLAN.md`.
---

# Create Admin Interface

Orchestratore per creare o rifattorizzare una pagina admin Laravel complessa.

Target: Laravel 13.x, PHP 8.3+.

## Fasi

1. audit componenti e servizi esistenti
2. definizione del contratto backend/frontend
3. implementazione backend
4. implementazione frontend
5. test, hardening e review prestazionale

## Quando usarla

- pagine con filtri, KPI, tabelle, export, grafici, modal
- refactor di interfacce admin disomogenee
