---
paths:
  - "app/**"
  - "database/**"
  - "tests/**"
  - "routes/**"
  - "config/**"
---

# Laravel Backend Rules

These rules apply when working with Laravel backend code.

## Required Reading

Before making Laravel changes, review:
- `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md`
- `docs/guides/PHP_CODE_STYLE_GUIDE.md`

## Architecture

- **Service-Repository-Controller pattern** with danx integration
- ALL business logic in Services
- ALL data access in Repositories
- Controllers are thin delegation layers
- **Team-based access control** for all data operations

## Commands

```bash
./vendor/bin/sail test                      # Run all tests
./vendor/bin/sail test --filter=TestName    # Run specific test
./vendor/bin/sail artisan queue:restart     # After job code changes
```

**Note:** Pint formatting runs automatically via Claude Code hook.

## Debug Commands

```bash
./vendor/bin/sail artisan debug:task-run {id}           # Debug task runs
./vendor/bin/sail artisan debug:extract-data-task-run {id}  # Debug extract data
./vendor/bin/sail artisan audit:debug                   # Debug audit logs
```

Always run `--help` first to see available options.

## Key Patterns

- Use `ActionController` base for all API controllers
- Use `ActionRepository` for data access
- Use `ActionResource` for API responses
- Team scope all queries via `teamQuery()` method
- Follow namespace conventions in the patterns guide
