# Core Engineering Principles

`SOLID / DRY / Zero-Debt / One-Way / Read-First / 100%-Tests`

## The Principles

1. **Zero Tech Debt** - No legacy code, no backwards compatibility, no dead code, no deprecated code. NEVER add compatibility layers.

2. **SOLID Principles** - Single responsibility, Open/closed, Liskov substitution, Interface segregation, Dependency inversion. Keep files small, methods small.

3. **DRY Principles** - Don't Repeat Yourself. Always refactor duplication immediately. Never copy-paste code.

4. **One Way** - ONE correct way to do everything. Never introduce multiple ways to do the same thing. If something uses the wrong name/pattern, fix it at the source (the caller), not the callee.

5. **Read First** - Always read existing implementations before writing new code. Understand patterns before implementing.

6. **100% Test Coverage** - All features and bug fixes require comprehensive tests. Bug fixes use TDD (write failing test first).

## Zero Backwards Compatibility

**NEVER introduce backwards compatibility code. This is a CRITICAL violation.**

### Forbidden Patterns

- `$param = $params['old_name'] ?? $params['new_name'] ?? null;` (supporting multiple names)
- Comments containing "backwards compatibility", "legacy support", "deprecated"
- Code that handles "old format" or "new format" simultaneously
- Fallback logic for old parameter names, old data structures, or old APIs

### The Rule

ONE correct way to do everything. If something uses the wrong name, fix it at the source. Never add compatibility layers.
