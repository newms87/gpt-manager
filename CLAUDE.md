# GPT Manager - Project Overview

**Welcome to GPT Manager!**

This file provides a high-level overview of the project structure and documentation.

## Documentation Structure

**For different roles, read different files:**

### 🎯 If you are the ORCHESTRATOR agent:
- **START HERE**: Read `ORCHESTRATOR_GUIDE.md` - Contains all delegation rules
- Then familiarize yourself with `PROJECT_POLICIES.md` for project-wide policies

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
- Orchestrator agents: Read `ORCHESTRATOR_GUIDE.md` first
- Sub-agents: Read `AGENT_CORE_BEHAVIORS.md` first
- Everyone: Familiarize yourself with `PROJECT_POLICIES.md`
