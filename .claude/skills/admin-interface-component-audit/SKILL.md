---
name: admin-interface-component-audit
description: Use BEFORE building or refactoring any admin page to inventory existing Blade components, partials, design tokens, and reusable Alpine stores so the new work composes existing primitives instead of duplicating them. Trigger at the start of every Macro 3/5/6/7 UI slice, before adding a new Blade component, or when Copilot flags duplication during review.
---

# Component Audit

Prima di creare una nuova interfaccia admin:

1. elenca componenti UI gia' presenti
2. elenca servizi o helper gia' esistenti
3. per ogni elemento decidi:
   - REUSE
   - EXTEND
   - CREATE-DOMAIN
   - CREATE-GLOBAL

## Regola principale

Default a REUSE. Creare nuovo codice solo se il riuso peggiora chiarezza o correttezza.

## Output

Tabella con elemento, decisione, path esistente e motivo.
