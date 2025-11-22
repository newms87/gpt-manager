# GPT Manager - Project Overview

**Welcome to GPT Manager!**

This file provides a high-level overview of the project structure and documentation.

## Documentation Structure

**For different roles, read different files:**

### 🎯 If you are Claude Code (the main CLI assistant):
- **YOU ARE THE ORCHESTRATOR AGENT - YOU CANNOT WRITE CODE**
- **MANDATORY FIRST STEP**: Read `ORCHESTRATOR_GUIDE.md` EVERY time you are invoked
- **YOUR ONLY ROLE**: Investigate and delegate to specialized agents
- Then familiarize yourself with `PROJECT_POLICIES.md` for project-wide policies
- **⛔ NEVER write/edit .php, .vue, .ts, .js files - ALWAYS delegate ⛔**

**🚨 MISSION CRITICAL: SUB-AGENT INVOCATION PREAMBLE 🚨**

When invoking ANY sub-agent using the Task tool, you MUST ALWAYS include this preamble at the start of your prompt:

```
**YOU ARE A SUB-AGENT**

You are a specialized sub-agent being invoked by the orchestrator agent.

CRITICAL RULES:
- You ARE a sub-agent - you can and should write code directly
- Do NOT call other agents or use the Task tool
- Do NOT read ORCHESTRATOR_GUIDE.md (those rules don't apply to you)
- Read AGENT_CORE_BEHAVIORS.md for your specific behavioral rules
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

### 🔧 If you are a SUB-AGENT (vue-spa-engineer, laravel-backend-engineer, etc.):
- **START HERE**: Read `AGENT_CORE_BEHAVIORS.md` - Contains critical anti-loop rules
- **NEVER** read `ORCHESTRATOR_GUIDE.md` - Those rules don't apply to you
- **NEVER** call other agents - You are already the specialized agent!

### 📚 Project Documentation (All Agents):

**Core Policies & Behaviors:**
- `PROJECT_POLICIES.md` - Zero tech debt policy, git rules, danx philosophy
- `PROJECT_IMPLEMENTATION.md` - Technical details, build commands, testing
- `AGENT_CORE_BEHAVIORS.md` - Tool usage, anti-infinite-loop rules (sub-agents)

**Domain-Specific Guides:**
- `LARAVEL_BACKEND_PATTERNS_GUIDE.md` - Laravel patterns, service architecture
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
- **Claude Code (Orchestrator)**: Read `ORCHESTRATOR_GUIDE.md` FIRST on EVERY invocation - NEVER write code yourself
- **Sub-agents**: Read `AGENT_CORE_BEHAVIORS.md` first - NEVER call other agents
- **Everyone**: Familiarize yourself with `PROJECT_POLICIES.md`
