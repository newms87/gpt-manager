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

## 🚨 CRITICAL: Preventing Infinite Agent Loops

**WHEN CALLING ANY AGENT, YOU MUST ALWAYS INCLUDE THIS IN YOUR PROMPT:**

```
CRITICAL: You are already running as a specialized agent. DO NOT call any other agents or use the Task tool. You have direct access to all tools you need (Read, Write, Edit, Bash, etc.). Make all changes directly using these tools.
```

**Why This Is Required:**
- Agents calling agents creates infinite loops
- Each agent already has full access to all necessary tools
- Agents should work directly with files, not delegate further

**Example of CORRECT agent call:**
```
Task(vue-spa-engineer): "Change AI Instructions field to use MarkdownEditor.

CRITICAL: You are already running as a specialized agent. DO NOT call any other agents or use the Task tool. Make all changes directly using Read, Write, Edit tools.

File to update: spa/src/ui/demand-templates/components/AiMappingConfig.vue
- Replace TextField with MarkdownEditor
- Import MarkdownEditor from quasar-ui-danx
..."
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

