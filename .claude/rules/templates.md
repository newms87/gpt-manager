---
paths:
  - "app/Services/Template/**"
  - "app/Models/Template/**"
  - "app/Repositories/Template*.php"
  - "app/Http/Controllers/Template/**"
  - "app/Jobs/Template*.php"
  - "tests/**/Template**"
---

# Templates Rules

These rules apply when working with prompt templates.

## Required Reading

Before making template changes, review:
- `docs/guides/TEMPLATES_GUIDE.md`

## Key Concepts

- **Prompt templates** - Reusable prompt structures with variable substitution
- **Template testing** - Procedures for testing template changes
- **Template-based extraction** - Workflows driven by templates

## Architecture

- Templates define prompt structure and variables
- Variables are substituted at runtime with context data
- Templates can be versioned and tested independently
