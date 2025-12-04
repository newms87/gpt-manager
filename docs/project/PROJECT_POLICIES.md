# GPT Manager - Project Policies

**This file contains high-level policies that apply to ALL agents and the orchestrator.**

Read this file to understand the project's philosophy, architectural principles, and governance rules.

---

## Zero Tech Debt Policy

**ABSOLUTE ZERO BACKWARDS COMPATIBILITY** - No exceptions, ever

When implementing changes:

1. **IMMEDIATE REPLACEMENT** - Never work around legacy patterns
2. **COMPLETE REMOVAL** - Delete old code entirely, no compatibility layers
3. **ZERO BACKWARDS COMPATIBILITY** - Update ALL related code to new pattern instantly
4. **NO GRADUAL MIGRATION** - Replace everything in one atomic change
5. **COMPREHENSIVE TESTING** - Ensure complete replacement works correctly

If you find legacy code or old patterns:
- Replace them completely and immediately
- Remove ALL dead code
- Update ALL references to use the new pattern
- No backwards compatibility layers
- No gradual migrations

**Rationale:** Tech debt compounds exponentially. Maintaining multiple patterns or compatibility layers creates confusion, bugs, and maintenance burden. ONE correct way to do everything.

**When tests use wrong parameter names:** Fix the TEST, not the service. The service defines the canonical interface.

---

## Git Operations Policy

**🚨 CRITICAL: GIT OPERATIONS - READ ONLY!**

**NEVER USE GIT COMMANDS THAT MAKE CHANGES**

**ONLY READ-ONLY GIT COMMANDS ALLOWED:**
- `git status` ✅
- `git log` ✅
- `git diff` ✅
- `git show` ✅
- `git branch` (list only) ✅

**ABSOLUTELY FORBIDDEN:**
- `git add` ❌
- `git commit` ❌
- `git push` ❌
- `git pull` ❌
- `git merge` ❌
- `git rebase` ❌
- `git reset` ❌
- `git checkout` ❌
- `git stash` ❌
- ANY command that modifies repository state ❌

**Rationale:** User handles ALL git operations that modify the repository. This prevents accidental commits, branch switches, or repository state changes that could disrupt the user's workflow.

---

## Danx Library Policy

**Danx library source is at:** `../danx/` (relative to this project)

### Key Principle: Danx is OUR Library

- **YOU CAN AND SHOULD modify danx library code when necessary**
- **NEVER modify `vendor/newms87/danx`** - that's the installed package
- **Modify `../danx/src/` directly** - it's our library, not a third-party dependency

**Common danx modifications:**
- `../danx/src/Events/ModelSavedEvent.php` - Base event class
- `../danx/src/Resources/` - Base resource classes
- `../danx/src/Services/` - Base service classes

**Rationale:** Danx is an internal library we control. Don't treat it as a black-box dependency. If danx needs a feature, add it there rather than working around it in this codebase.

---

## Key Architecture Patterns

These patterns are mandatory across the entire codebase:

### Laravel Backend
- **Service-Repository-Controller pattern** with danx integration
- ALL business logic in Services
- ALL data access in Repositories
- Controllers are thin delegation layers only

### Vue Frontend
- **Vue 3 Composition API** with quasar-ui-danx components
- NO Options API or Vue 2 patterns
- Centralized state management via dx controllers
- Tailwind CSS for ALL styling (no inline styles)

### Security
- **Team-based access control** for ALL data operations
- NEVER allow cross-team data access
- Always scope queries by team context

### Testing
- **Comprehensive test coverage** for business logic
- Focus on behavior, not implementation details
- Test edge cases and error handling

**Rationale:** Consistency enables developers to navigate the codebase quickly. Every service looks like every other service. Every component follows the same patterns.

---

## Code Quality Philosophy

### Always:
- Read existing implementations BEFORE any code work
- Follow established patterns exactly
- Never create custom patterns when established ones exist
- Each agent has comprehensive guidelines for their domain

### Never:
- Create custom solutions when established patterns exist
- Add backwards compatibility layers
- Use deprecated features or syntax
- Leave TODO comments without implementation plans

**Rationale:** Pattern consistency >>> clever solutions. A "worse" solution that follows established patterns is better than a "better" solution that introduces new patterns.

---

## Reference Documentation

**Domain-Specific Guides:**
- **Laravel Backend**: `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md`
- **Vue Frontend**: `spa/SPA_PATTERNS_GUIDE.md`
- **Agent Behaviors**: `docs/agents/AGENT_CORE_BEHAVIORS.md`
- **Implementation Details**: `docs/project/PROJECT_IMPLEMENTATION.md` (sub-agents only)

**Agent Locations:**
- Agent files are located at: `.claude/agents/*.md`
- Available agents: `laravel-backend-architect`, `laravel-backend-engineer`, `laravel-backend-qa-tester`, `vue-spa-architect`, `vue-spa-engineer`, `vue-spa-reviewer`

---

**These policies apply to ALL work in this project. No exceptions.**
