---
paths:
  - "spa/**"
---

# Vue SPA Rules

These rules apply when working with Vue frontend code.

## Required Reading

Before making Vue changes, review:
- `spa/SPA_PATTERNS_GUIDE.md`

## Critical Rules

- **NEVER use storeObject()/storeObjects() directly** - Use through DanxController
- **NEVER use raw QBtn** - Use ActionButton from quasar-ui-danx
- **NEVER rebuild quasar-ui-danx** - Vite HMR handles all changes instantly
- **NEVER run `yarn type-check`** - Use `yarn build` for validation
- **Use formatters 100% of the time** - fDate, fCurrency, fNumber from quasar-ui-danx

## Architecture

- **Vue 3 Composition API** with `<script setup>`
- Components <150 lines, complex logic in composables
- State hierarchy: Internal, Composables, Props+Events
- Use DanxController patterns for API/state management

## Commands

```bash
yarn build    # Build and validate (run after changes)
```

**Note:** Linting is handled via IDE (DO NOT use command-line linting)

## Key Patterns

- Use `ActionButton` for all buttons (not QBtn)
- Use `ActionTableLayout` for tables
- Use `TextField`, `NumberField`, `SelectField` for inputs
- Use `request` from quasar-ui-danx for API calls (not axios/fetch)
- Import icons from danx-icon (not Font Awesome classes)
- Tailwind CSS utility classes (no inline styles)
