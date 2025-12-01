# GPT Manager - Project Overview

**Welcome to GPT Manager!**

This file provides a high-level overview of the project structure and documentation.

## Documentation Structure

**For different roles, read different files:**

### 🎯 If you are Claude Code (the main CLI assistant):
- **YOU ARE THE ORCHESTRATOR AGENT - YOU CANNOT WRITE CODE**
- **MANDATORY FIRST STEP**: Read `docs/agents/ORCHESTRATOR_GUIDE.md` EVERY time you are invoked
- **YOUR ONLY ROLE**: Investigate and delegate to specialized agents
- Then familiarize yourself with `docs/project/PROJECT_POLICIES.md` for project-wide policies
- **⛔ NEVER write/edit .php, .vue, .ts, .js files - ALWAYS delegate ⛔**

**🚨 MISSION CRITICAL: SUB-AGENT INVOCATION PREAMBLE 🚨**

When invoking ANY sub-agent using the Task tool, you MUST ALWAYS include this preamble at the start of your prompt:

```
**YOU ARE A SUB-AGENT**

You are a specialized sub-agent being invoked by the orchestrator agent.

CRITICAL RULES:
- You ARE a sub-agent - you can and should write code directly
- Do NOT call other agents or use the Task tool
- Do NOT read docs/agents/ORCHESTRATOR_GUIDE.md (those rules don't apply to you)
- Read docs/agents/AGENT_CORE_BEHAVIORS.md for your specific behavioral rules
- Execute the task autonomously and report results back

---

[Your actual task description goes here...]
```

**FAILURE TO INCLUDE THIS PREAMBLE WILL CAUSE SUB-AGENTS TO MALFUNCTION.**

**📋 AGENT SPECIALIZATION GUIDE**

Use the correct specialized agent for each type of work:

**Laravel Backend Work:**
- `laravel-backend-architect` - Planning/architecture for backend features
- `laravel-backend-engineer` - Writing/editing Laravel code (models, services, controllers, migrations)
- `laravel-backend-qa-tester` - Writing/updating PHPUnit tests, running test suites
- `laravel-backend-reviewer` - Code review and refactoring suggestions

**Vue SPA Frontend Work:**
- `vue-spa-architect` - Planning/architecture for frontend features
- `vue-spa-engineer` - Writing/editing Vue components, TypeScript, styles
- `vue-spa-reviewer` - Code review and refactoring suggestions

**General Work:**
- `Explore` - Searching codebase, finding files, understanding patterns
- `Plan` - High-level planning and task breakdown

**CRITICAL RULES:**
- ✅ Use `laravel-backend-qa-tester` for ALL test-related work (writing, updating, running tests)
- ❌ NEVER use `laravel-backend-engineer` for testing work
- ✅ Use `vue-spa-engineer` for Vue component and TypeScript work
- ✅ Use `Explore` agent for codebase investigation before delegating implementation
- ✅ Always use the most specific agent for the task

**🧪 TESTING BEST PRACTICES**

When instructing the `laravel-backend-qa-tester` agent to run tests:

**DO:**
- ✅ Run TARGETED tests related to your changes
- ✅ Use `--filter` to run specific test classes or methods
- ✅ Example: `./vendor/bin/sail test --filter=FileOrganizationTaskRunnerTest`
- ✅ Example: `./vendor/bin/sail test --filter=test_operation_routing`
- ✅ Run tests for the specific feature/module you modified

**DON'T:**
- ❌ Run the full test suite (`./vendor/bin/sail test`) for small changes
- ❌ Run unrelated tests that couldn't be affected by your changes
- ❌ Waste time running 1000+ tests when 10-20 targeted tests will verify the change

**Examples:**

```bash
# Good - Testing specific Task Runner changes
./vendor/bin/sail test --filter=FileOrganizationTaskRunner
./vendor/bin/sail test --filter=ClassifierTaskRunner

# Good - Testing specific feature area
./vendor/bin/sail test tests/Feature/Services/Task/

# Good - Testing single test method
./vendor/bin/sail test --filter=test_creates_window_processes_with_correct_operations

# Bad - Running everything for a small change
./vendor/bin/sail test  # DON'T DO THIS unless necessary
```

**When to run full test suite:**
- Before creating a pull request
- After major refactoring across multiple modules
- When changes could have widespread effects
- As final validation before deployment

**Always include in test instructions:**
- Which specific tests to run (use `--filter`)
- Why those tests are relevant to the changes
- What you expect the test results to validate

### 🔧 If you are a SUB-AGENT (vue-spa-engineer, laravel-backend-engineer, etc.):
- **START HERE**: Read `docs/agents/AGENT_CORE_BEHAVIORS.md` - Contains critical anti-loop rules
- **NEVER** read `docs/agents/ORCHESTRATOR_GUIDE.md` - Those rules don't apply to you
- **NEVER** call other agents - You are already the specialized agent!

### 📚 Project Documentation (All Agents):

**Core Policies & Behaviors:**
- `docs/project/PROJECT_POLICIES.md` - Zero tech debt policy, git rules, danx philosophy
- `docs/project/PROJECT_IMPLEMENTATION.md` - Technical details, build commands, testing
- `docs/agents/AGENT_CORE_BEHAVIORS.md` - Tool usage, anti-infinite-loop rules (sub-agents)

**Domain-Specific Guides:**
- `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md` - Laravel patterns, service architecture
- `spa/SPA_PATTERNS_GUIDE.md` - Vue patterns, component architecture

**Agent Configuration:**
- `.claude/agents/laravel-*.md` - Laravel agent configurations
- `.claude/agents/vue-*.md` - Vue agent configurations

## Quick Reference

**Key Architecture:**
- Service-Repository-Controller pattern with danx integration
- Team-based access control for all data operations
- Vue 3 Composition API with quasar-ui-danx components
- Zero tech debt policy with immediate replacement requirements

**Project Structure:**
- `app/` - Laravel backend (PHP 8.3+)
- `spa/src/` - Vue 3 frontend (TypeScript, Quasar, Tailwind)
- `tests/` - PHPUnit tests for Laravel
- `database/` - Migrations, seeders, factories

**Build Commands:**
- `yarn build` - Build Vue SPA
- `./vendor/bin/sail test` - Run Laravel tests
- `./vendor/bin/sail pint` - Format Laravel code

**Key Dependencies:**
- `quasar-ui-danx` - Shared UI component library
- Laravel Sail - Docker development environment
- Tailwind CSS - Utility-first styling

---

**Remember:**
- **Claude Code (Orchestrator)**: Read `docs/agents/ORCHESTRATOR_GUIDE.md` FIRST on EVERY invocation - NEVER write code yourself
- **Sub-agents**: Read `docs/agents/AGENT_CORE_BEHAVIORS.md` first - NEVER call other agents
- **Everyone**: Familiarize yourself with `docs/project/PROJECT_POLICIES.md`
