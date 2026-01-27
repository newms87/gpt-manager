# GPT Manager - Project Policies

**This file contains high-level policies that apply to ALL work in this project.**

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

**GIT OPERATIONS ARE READ-ONLY**

**Only read-only git commands allowed:**
- `git status`
- `git log`
- `git diff`
- `git show`
- `git branch` (list only)

**Absolutely forbidden without explicit user request:**
- `git add`, `git commit`, `git push`
- `git pull`, `git merge`, `git rebase`
- `git reset`, `git checkout`, `git stash`
- ANY command that modifies repository state

**Rationale:** User handles ALL git operations that modify the repository. This prevents accidental commits or repository state changes.

---

## Danx Library Policy

**Danx library source is at:** `../danx/` (relative to this project)

### Key Principle: Danx is OUR Library

- **YOU CAN AND SHOULD modify danx library code when necessary**
- **NEVER modify `vendor/newms87/danx`** - that's the installed package
- **Modify `../danx/src/` directly** - it's our library, not a third-party dependency

**Rationale:** Danx is an internal library we control. Don't treat it as a black-box dependency. If danx needs a feature, add it there rather than working around it.

---

## Key Architecture Patterns

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
- **100% test coverage** for business logic
- Bug fixes use TDD (write failing test first)
- Focus on behavior, not implementation details

**Rationale:** Consistency enables developers to navigate the codebase quickly.

---

## Code Quality Philosophy

### Always:
- Read existing implementations BEFORE any code work
- Follow established patterns exactly
- Never create custom patterns when established ones exist

### Never:
- Create custom solutions when established patterns exist
- Add backwards compatibility layers
- Use deprecated features or syntax
- Leave TODO comments without implementation plans

**Rationale:** Pattern consistency >>> clever solutions.

---

## Reference Documentation

**Patterns & Standards:**
- Laravel: `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md`
- Vue: `spa/SPA_PATTERNS_GUIDE.md`

**Agent Behaviors:**
- `docs/agents/AGENT_BEHAVIORS.md`

**Implementation Details:**
- `docs/project/PROJECT_IMPLEMENTATION.md`

**Available Agents:**
- `laravel-backend-architect` - Backend exploration and planning (READ-ONLY)
- `vue-spa-architect` - Frontend exploration and planning (READ-ONLY)
- `code-reviewer` - Refactoring plans (READ-ONLY)
- `test-reviewer` - Test coverage auditing (READ-ONLY)

---

**These policies apply to ALL work in this project. No exceptions.**
