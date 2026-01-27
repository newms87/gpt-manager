# Agent Behaviors

**This guide applies to specialized agents invoked via the Task tool.**

## Agent Architecture

Main Claude Code writes ALL code directly. Specialized agents are READ-ONLY for investigation and planning:

| Agent | Purpose | Can Edit? |
|-------|---------|-----------|
| `laravel-backend-architect` | Explore backend, investigate bugs, plan architecture | No |
| `vue-spa-architect` | Explore components, trace data flow, plan architecture | No |
| `code-reviewer` | Create refactoring plans, analyze code quality | No |
| `test-reviewer` | Audit test coverage, review test quality | No |

---

## Core Engineering Principles

`SOLID / DRY / Zero-Debt / One-Way / Read-First / 100%-Tests`

1. **Zero Tech Debt** - No legacy code, no backwards compatibility, no dead code
2. **SOLID Principles** - Single responsibility, small files, small methods
3. **DRY Principles** - Don't Repeat Yourself, refactor duplication immediately
4. **One Way** - ONE correct way to do everything. Fix at source, not caller
5. **Read First** - Always read existing implementations before analyzing
6. **100% Tests** - All features and bug fixes require comprehensive tests

---

## Anti-Infinite-Loop Rules

**CRITICAL: Agents must NEVER call other agents or use the Task tool.**

You are already the specialized agent. You have:
- Full authority in your domain
- All the tools you need
- No need for further delegation

If your task is out of scope, report back with:
- Why it's out of scope
- Which agent type would be appropriate
- What files are involved

---

## Tool Usage

### Always Use Specialized Tools

| Task | Use | NOT |
|------|-----|-----|
| Read files | `Read` tool | `cat`, `head`, `tail` |
| Search files | `Glob` tool | `find`, `ls` |
| Search content | `Grep` tool | `grep`, `rg` |
| Communication | Direct text output | `echo`, `printf` |

### Relative Paths Only

**Absolute paths are FORBIDDEN:**
- `./vendor/bin/sail test` (correct)
- `/home/user/project/vendor/bin/sail test` (WRONG)

If a command fails, use `pwd` to check directory. Never switch to absolute paths.

---

## Debugging

**Prefer debug commands over tinker:**

| Task | Command |
|------|---------|
| Extract Data Tasks | `./vendor/bin/sail artisan debug:extract-data-task-run {id}` |
| General Task Runs | `./vendor/bin/sail artisan debug:task-run {id}` |
| Audit/API Logs | `./vendor/bin/sail artisan audit:debug` |

Always run `--help` first to see available options.

---

## Required Reading

Before starting work:

1. `docs/project/PROJECT_POLICIES.md` - Zero tech debt, git rules, architecture
2. `docs/project/PROJECT_IMPLEMENTATION.md` - Paths, commands, technical details
3. Domain-specific guide:
   - Laravel: `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md`
   - Vue: `spa/SPA_PATTERNS_GUIDE.md`

---

## Missing Relationship Data

**CRITICAL**: When a relationship isn't in the API response, the issue is almost ALWAYS the frontend API call, NOT eager loading.

ActionResource has two types of fields:
- **Scalar fields** - Always included (id, name, etc.)
- **Callable fields** - Only included when explicitly requested

**The fix is in the frontend call:**
```typescript
// Request the callable field explicitly
await routes.details({ id }, { schema_definition: true });
```

Eager loading only affects performance (N+1 queries), not what data is returned.

---

## Reverting Changes

**NEVER use `git checkout` or `git revert`**

Files may contain user changes mixed with yours. Git blindly reverts EVERYTHING.

Correct process:
1. Read the file
2. Identify YOUR specific changes
3. Edit to remove ONLY your changes
4. Preserve all user changes

---

## Reporting Back

When you complete your work, provide:

1. **Summary** - Brief description of findings/analysis
2. **Files Analyzed** - List all files you examined
3. **Recommendations** - What the main agent should implement
4. **Next Steps** - Any follow-up work needed

Be concise but complete. Focus on actionable information.
