# GPT Manager - Agent Delegation Guide

## 🚨 MANDATORY AGENT DELEGATION (NO EXCEPTIONS!)

**YOU MUST DELEGATE ALL TECHNICAL WORK TO SPECIALIZED AGENTS**

### Agent Selection Rules:

**Laravel Backend Work:**
- **Architecture/Planning** → `laravel-backend-architect` (REQUIRED for medium/large features)
- **Implementation** → `laravel-backend-engineer` (ALL Laravel code writing)
- **Testing/QA** → `laravel-backend-qa-tester` (EXCLUSIVE AUTHORITY for tests)

**Vue Frontend Work:**
- **Architecture/Planning** → `vue-spa-architect` (REQUIRED for medium/large features) 
- **Implementation** → `vue-spa-engineer` (ALL Vue/Tailwind code writing)
- **Review/QA** → `vue-spa-reviewer` (REQUIRED after code changes)

### Your Direct Authority (ONLY):
- File reads/searches for investigation
- Simple configuration updates
- Command-line operations
- Documentation updates (when explicitly requested)
- Agent coordination and delegation decisions

**🚨 CRITICAL ENFORCEMENT:**
1. Is this Laravel code? → **MUST** delegate to Laravel agents
2. Is this Vue code? → **MUST** delegate to Vue agents  
3. Is this a test? → **ONLY** `laravel-backend-qa-tester` can write tests
4. Is it medium/large feature? → **MUST** use architect agents first

## Core Project Principles

**ZERO TECH DEBT POLICY:**
- **ABSOLUTE ZERO BACKWARDS COMPATIBILITY** - No exceptions, ever
- **IMMEDIATE REPLACEMENT ONLY** - Replace old code completely
- Always use current patterns exclusively
- Remove dead code immediately
- ONE correct way to do everything

**CRITICAL RULES:**
- Always read existing implementations BEFORE any code work
- Never create custom patterns when established ones exist
- Always delegate technical work to specialized agents
- Each agent has comprehensive guidelines for their domain

**🚨 ABSOLUTE REQUIREMENT: RELATIVE PATHS ONLY! 🚨**
- **NEVER EVER USE ABSOLUTE PATHS** - They will NEVER work in any command or tool
- **ALWAYS USE RELATIVE PATHS** - All file paths must be relative to current working directory
- **Examples of CORRECT paths:**
  - `spa/src/components/MyComponent.vue` ✅
  - `app/Services/MyService.php` ✅
  - `config/google-docs.php` ✅
  - `./vendor/bin/sail test` ✅
- **Examples of INCORRECT paths:**
  - `/home/user/web/project/spa/src/components/MyComponent.vue` ❌
  - `/home/newms/web/gpt-manager/app/Services/MyService.php` ❌
  - `/var/www/config/google-docs.php` ❌
- **This applies to ALL tools and ALL agents** - No exceptions!

**MANDATORY BUILD COMMANDS:**
- **ONLY use `yarn build`** for Vue frontend builds - NEVER `npm run dev` or `npm run type-check`
- **ONLY use `./vendor/bin/sail test`** for Laravel backend testing
- Follow these commands exactly - NO EXCEPTIONS

**🚨 CRITICAL: GIT OPERATIONS - READ ONLY!**
- **NEVER USE GIT COMMANDS THAT MAKE CHANGES**
- **ONLY READ-ONLY GIT COMMANDS ALLOWED:**
  - `git status` ✅
  - `git log` ✅
  - `git diff` ✅
  - `git show` ✅
  - `git branch` (list only) ✅
- **ABSOLUTELY FORBIDDEN:**
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
- **User handles ALL git operations that modify the repository**

**DANX LIBRARY:**
- **Danx library source is at:** `../danx/` (relative to this project)
- **YOU CAN AND SHOULD modify danx library code when necessary**
- **NEVER modify `vendor/newms87/danx`** - that's the installed package
- **Modify `../danx/src/` directly** - it's our library, not a third-party dependency
- **Common danx modifications:**
  - `../danx/src/Events/ModelSavedEvent.php` - Base event class
  - `../danx/src/Resources/` - Base resource classes
  - `../danx/src/Services/` - Base service classes

**AGENT DOCUMENTATION LOCATION:**
- **Agent files are located at:** `@.claude/agents/*.md`
- **ALWAYS CHECK YOUR CWD** - You may be in `/spa/` directory, agents are in parent `/`
- **Available agents:** laravel-backend-architect.md, laravel-backend-engineer.md, laravel-backend-qa-tester.md, vue-spa-architect.md, vue-spa-engineer.md, vue-spa-reviewer.md

## Agent Workflow

**For All Technical Work:**

1. **Investigation Phase** (You handle):
   - Read files to understand current state
   - Search for existing patterns
   - Identify scope of changes needed

2. **Planning Phase** (Architect agents):
   - **Medium/Large Features**: MUST use `vue-spa-architect` or `laravel-backend-architect` first
   - Get comprehensive implementation plan before any coding

3. **Implementation Phase** (Engineer agents):
   - **ALL Vue code**: Delegate to `vue-spa-engineer`
   - **ALL Laravel code**: Delegate to `laravel-backend-engineer`
   - **ALL tests**: ONLY `laravel-backend-qa-tester` can write/modify tests

4. **Review Phase** (QA agents):
   - **Vue changes**: Use `vue-spa-reviewer` for quality assurance
   - **Laravel changes**: Use `laravel-backend-qa-tester` for testing and QA

## 🚨 CRITICAL: Agent Behavior and Instructions

**ALL AGENTS NOW READ `AGENT_CORE_BEHAVIORS.md` AUTOMATICALLY**

Every agent has been configured to read `AGENT_CORE_BEHAVIORS.md` as their FIRST task, which contains:
- Anti-infinite-loop instructions (NEVER call other agents)
- Git operations restrictions (READ ONLY)
- Zero tech debt policy
- Build commands and tool usage guidelines

**YOU DO NOT NEED TO INCLUDE THESE INSTRUCTIONS IN YOUR PROMPTS ANYMORE**

The agents will automatically:
1. Add "Read AGENT_CORE_BEHAVIORS.md in full" to their todo list
2. Add "Read [domain-specific guide]" to their todo list
3. Read both files before starting work
4. Follow all the rules defined in those guides

**Example of CORRECT agent call with RELATIVE PATHS:**
```
Task(vue-spa-engineer): "Change AI Instructions field to use MarkdownEditor.

File to update: spa/src/ui/demand-templates/components/AiMappingConfig.vue
- Replace TextField with MarkdownEditor
- Import MarkdownEditor from quasar-ui-danx
- Keep same functionality with model-value binding
...

CRITICAL: Use ONLY relative paths like 'spa/src/...' NOT absolute paths like '/home/user/...'
"
```

## Reference Information

**All detailed implementation patterns are now in the specialized agent files:**

- **Laravel Backend**: See `LARAVEL_BACKEND_PATTERNS_GUIDE.md` and Laravel agent files in `.claude/agents/`
- **Vue Frontend**: See Vue agent files in `.claude/agents/` for complete patterns and components
- **Testing**: Only `laravel-backend-qa-tester` agent has testing authority and guidelines

**Key References:**
- Service-Repository-Controller pattern with danx integration
- Team-based access control for all data operations  
- Vue 3 Composition API with quasar-ui-danx components
- Zero tech debt policy with immediate replacement requirements

---

**Remember: DELEGATE ALL TECHNICAL WORK TO SPECIALIZED AGENTS - NO EXCEPTIONS!**

