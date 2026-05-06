# Contributing

## Requirements
- PHP 8.3+
- Node 20+
- Composer
- npm

## Branch model
- Macro branch: `task/<macro-slug>`
- Subtask branch: `subtask/<macro-slug>-<n>-<short-name>`

## Local gates
```bash
composer validate --strict --no-check-publish
composer format:test
composer analyse
composer test
npm run lint
npm run build
npm run test:e2e
```

## PR loop
1. Open PR to macro branch.
2. Request Copilot review.
3. Fix all must-fix comments.
4. Merge only with green CI and resolved review.
