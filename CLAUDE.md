# GPT Manager

AI-powered team object management with workflow automation, data extraction, and intelligent classification.

## Quick Reference

- **Local URL**: http://localhost:5173/
- **Dashboard**: http://localhost:5173/dashboard

## Build & Test Commands

```bash
./vendor/bin/sail test                      # Run all tests
./vendor/bin/sail test --filter=TestName    # Run specific test
./vendor/bin/sail artisan queue:restart     # After job/queue code changes
yarn build                                   # Build Vue SPA (validation)
```

**Note:** Pint formatting runs automatically via Claude Code hook after PHP file edits.

## Core Principles

`SOLID / DRY / Zero-Debt / One-Way / Read-First / 100%-Tests`

| Principle | Description |
|-----------|-------------|
| **Zero Tech Debt** | No legacy, backwards compat, dead, deprecated, or obsolete code |
| **SOLID** | Single responsibility, small files, small methods |
| **DRY** | Don't repeat yourself, always refactor duplication |
| **One Way** | ONE correct way to do everything. Fix at source, not caller |
| **Read First** | Read existing implementations before writing |
| **100% Tests** | All features and bug fixes require comprehensive tests |

## Testing Requirements

- **100% test coverage** required for all features and bug fixes
- **Bug fixes**: TDD - Write failing test, fix, verify
- **Features**: Implement, then write comprehensive tests
- See `.claude/rules/tdd.md` for detailed TDD workflow

## Critical Gotchas

- **NEVER rebuild quasar-ui-danx** - Vite HMR handles all changes instantly
- **Use relative paths ONLY** - No `/home/...` paths in commands
- **Queue restart after job changes** - `./vendor/bin/sail artisan queue:restart`
- **Use debug commands, not tinker** - See `.claude/rules/debugging.md`

## Agent Architecture

Main Claude Code writes ALL code directly. Specialized agents are READ-ONLY for investigation/planning:

| Agent | Purpose | Can Edit? |
|-------|---------|-----------|
| `laravel-backend-architect` | Explore backend, investigate bugs, plan architecture | No |
| `vue-spa-architect` | Explore components, trace data flow, plan architecture | No |
| `code-reviewer` | Create refactoring plans, analyze code quality | No |
| `test-reviewer` | Audit test coverage, review test quality | No |

## Key Documentation

**Patterns & Standards:**
- Laravel: `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md`
- Vue: `spa/SPA_PATTERNS_GUIDE.md`
- PHP Style: `docs/guides/PHP_CODE_STYLE_GUIDE.md`

**Project Rules:**
- Policies: `docs/project/PROJECT_POLICIES.md`
- Implementation: `docs/project/PROJECT_IMPLEMENTATION.md`
- Agent Behaviors: `docs/agents/AGENT_BEHAVIORS.md`

**Domain Guides:**
- Extract Data: `docs/guides/EXTRACT_DATA_GUIDE.md`
- Templates: `docs/guides/TEMPLATES_GUIDE.md`
- Task State Machine: `docs/guides/TASK_STATE_MACHINE_GUIDE.md`
- Artisan Commands: `docs/guides/ARTISAN_COMMANDS.md`

## Project Structure

```
app/           - Laravel backend (PHP 8.3+)
spa/src/       - Vue 3 frontend (TypeScript, Quasar, Tailwind)
tests/         - PHPUnit tests
database/      - Migrations, seeders, factories
```

## Key Architecture

- **Backend**: Service-Repository-Controller pattern with danx integration
- **Frontend**: Vue 3 Composition API with quasar-ui-danx components
- **Security**: Team-based access control for all data operations
- **Styling**: Tailwind CSS utility classes

## Dependencies

- `quasar-ui-danx` - Shared UI component library
- Laravel Sail - Docker development environment
- Tailwind CSS - Utility-first styling
