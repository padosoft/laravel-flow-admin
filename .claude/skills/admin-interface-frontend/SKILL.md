---
name: admin-interface-frontend
description: Use to implement the frontend slice of a complex Laravel admin page using Blade + Vite + Alpine.js (no SPA framework). Covers Blade partials/components, Alpine stores for client state, design-token CSS hygiene, status badges, JSON syntax-highlight, sparklines, charts, drawer, modal, ⌘K palette interactions. Trigger when adding/modifying templates under `resources/views/`, CSS under `resources/css/`, or JS under `resources/js/`, and when implementing a Macro 3/5/6/7/8 UI slice per `docs/IMPLEMENTATION_PLAN.md`.
---

# Admin Interface Frontend

Skill per la parte frontend di una pagina admin complessa.

## Moduli tipici

- entrypoint della pagina
- api client
- gestione filtri
- rendering tabella
- rendering KPI/charts
- gestione stati empty/loading/error

## Regole

- niente URL hardcoded nel JS se la view puo' passarle in `data-*`
- loading e disabled state obbligatori sulle azioni asincrone
- event delegation per liste o tabelle dinamiche
- cleanup di grafici, modal o istanze prima del re-render
