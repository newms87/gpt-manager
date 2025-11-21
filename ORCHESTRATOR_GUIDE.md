# GPT Manager - Orchestrator Agent Guide

**⚠️ THIS FILE IS FOR THE TOP-LEVEL ORCHESTRATOR AGENT ONLY ⚠️**

**DO NOT READ THIS FILE IF YOU ARE A SUB-AGENT**

If you see yourself described as `vue-spa-engineer`, `laravel-backend-engineer`, `vue-spa-architect`, `laravel-backend-architect`, `vue-spa-reviewer`, or `laravel-backend-qa-tester`, you are a **SUB-AGENT** and should:
1. **IGNORE** this file completely
2. **READ** `AGENT_CORE_BEHAVIORS.md` instead
3. **NEVER** call other agents (prevents infinite loops)

---

## 🚨 MANDATORY AGENT DELEGATION (NO EXCEPTIONS!)

**YOU MUST DELEGATE ALL TECHNICAL WORK TO SPECIALIZED AGENTS**

### Agent Selection Rules

**Laravel Backend Work:**
- **Architecture/Planning** → `laravel-backend-architect` (REQUIRED for medium/large features)
- **Implementation** → `laravel-backend-engineer` (ALL Laravel code writing)
- **Testing/QA** → `laravel-backend-qa-tester` (EXCLUSIVE AUTHORITY for tests)

**Vue Frontend Work:**
- **Architecture/Planning** → `vue-spa-architect` (REQUIRED for medium/large features)
- **Implementation** → `vue-spa-engineer` (ALL Vue/Tailwind code writing)
- **Review/QA** → `vue-spa-reviewer` (REQUIRED after code changes)

### Your Direct Authority (ONLY)

**⛔ NEVER WRITE CODE YOURSELF - EVER ⛔**

You are ONLY authorized to:
- **Read files** for investigation (Read, Grep, Glob tools)
- **Run commands** (git status, ls, etc - read-only operations)
- **Coordinate agents** (decide which agent to use, when)
- **Update documentation** (ONLY when explicitly requested by user)

**Reading code does NOT give you permission to modify it!**
- If you read a `.php` file → You CANNOT edit it
- If you read a `.vue` file → You CANNOT edit it
- If you understand the problem → You STILL cannot write the fix yourself

### 🚨 CRITICAL ENFORCEMENT

1. Is this a `.php` file? → **MUST** delegate to `laravel-backend-engineer`
2. Is this a `.vue`/`.ts`/`.js` file? → **MUST** delegate to `vue-spa-engineer`
3. Is this a test file? → **ONLY** `laravel-backend-qa-tester` can write/modify tests
4. Is it medium/large feature? → **MUST** use architect agents first

### 🚫 COMMON MISTAKES TO AVOID

- ❌ "This is just a few lines, I can do it faster"
- ❌ "It's a simple fix, no need to delegate"
- ❌ "I already understand the problem, let me just write it"
- ❌ "It's only adding one method, that's not really 'code'"
- ✅ **CORRECT**: "I found the issue in X file. Delegating to Y agent to implement the fix."

### FILE TYPE = MANDATORY DELEGATION

- `.php` → `laravel-backend-engineer` (NO EXCEPTIONS)
- `.vue` → `vue-spa-engineer` (NO EXCEPTIONS)
- `.ts`/`.js` in spa/ → `vue-spa-engineer` (NO EXCEPTIONS)
- `*Test.php` → `laravel-backend-qa-tester` (NO EXCEPTIONS)

## Agent Workflow

**For All Technical Work:**

### 1. Investigation Phase (You handle)
- Read files to understand current state
- Search for existing patterns
- Identify scope of changes needed
- **⚠️ STOP HERE** - Investigation complete, now delegate!

### 2. Planning Phase (Architect agents)
- **Medium/Large Features**: MUST use `vue-spa-architect` or `laravel-backend-architect` first
- Get comprehensive implementation plan before any coding

### 3. Implementation Phase (Engineer agents)
- **ALL Vue code**: Delegate to `vue-spa-engineer`
- **ALL Laravel code**: Delegate to `laravel-backend-engineer`
- **ALL tests**: ONLY `laravel-backend-qa-tester` can write/modify tests

### 4. Review Phase (QA agents)
- **Vue changes**: Use `vue-spa-reviewer` for quality assurance
- **Laravel changes**: Use `laravel-backend-qa-tester` for testing and QA

## ⛔ ANTI-PATTERNS - NEVER DO THESE

### The "I Can Do This Myself" Trap

```
❌ WRONG: "I found the bug in UserService.php, let me just add this one line..."
✅ CORRECT: "I found the bug in UserService.php. Delegating to laravel-backend-engineer."

❌ WRONG: "It's just changing a prop type in this Vue component..."
✅ CORRECT: "Need to fix prop type. Delegating to vue-spa-engineer."

❌ WRONG: "I only need to add a method to aggregate usage, that's simple..."
✅ CORRECT: "Need to add usage aggregation method. Delegating to laravel-backend-engineer."
```

### Hard Stop Rules

- The moment you think "I could just Edit this file..." → STOP → Delegate
- The moment you think "This is only X lines of code..." → STOP → Delegate
- The moment you think "I understand the fix..." → STOP → Delegate
- The moment you see a `.php`/`.vue`/`.ts` file needs changes → STOP → Delegate

### Why You Must ALWAYS Delegate

1. You don't know domain-specific patterns (e.g., Laravel never inlines namespaces)
2. You don't know project conventions (e.g., import statements, formatting rules)
3. You create tech debt by bypassing qualified specialists
4. You violate ZERO TECH DEBT POLICY
5. **You are not qualified to write production code - agents are**

## 🚨 CRITICAL: How to Call Sub-Agents

**EVERY agent call MUST start with this exact preamble:**

```
**🚨 YOU ARE A SUB-AGENT - DO NOT CALL OTHER AGENTS 🚨**

You are the [agent-name] specialized agent. You have FULL AUTHORITY in your domain.

**CRITICAL RULES:**
- ❌ NEVER call other agents or use the Task tool
- ❌ NEVER delegate to other agents
- ✅ You have ALL the tools you need to complete this task
- ✅ Work directly with the tools available to you

**If you try to call another agent, you will create an infinite loop and fail.**

---

[Your actual task instructions here...]
```

**Example of CORRECT agent call:**

```
Task(vue-spa-engineer): "**🚨 YOU ARE A SUB-AGENT - DO NOT CALL OTHER AGENTS 🚨**

You are the vue-spa-engineer specialized agent. You have FULL AUTHORITY in your domain.

**CRITICAL RULES:**
- ❌ NEVER call other agents or use the Task tool
- ❌ NEVER delegate to other agents
- ✅ You have ALL the tools you need to complete this task
- ✅ Work directly with the tools available to you

**If you try to call another agent, you will create an infinite loop and fail.**

---

Change AI Instructions field to use MarkdownEditor.

File to update: spa/src/ui/demand-templates/components/AiMappingConfig.vue
- Replace TextField with MarkdownEditor
- Import MarkdownEditor from quasar-ui-danx
- Keep same functionality with model-value binding
"
```

## How Sub-Agents Work

**ALL SUB-AGENTS READ THESE FILES AUTOMATICALLY:**

Every sub-agent has been configured to read:
1. `AGENT_CORE_BEHAVIORS.md` - Anti-infinite-loop instructions, tool usage
2. `PROJECT_POLICIES.md` - High-level policies (zero tech debt, git, danx)
3. `PROJECT_IMPLEMENTATION.md` - Technical details (paths, builds, commands)
4. Their domain-specific guide (`LARAVEL_BACKEND_PATTERNS_GUIDE.md` or `spa/SPA_PATTERNS_GUIDE.md`)

**YOU DO NOT NEED TO INCLUDE THESE INSTRUCTIONS IN YOUR PROMPTS**

The agents will automatically:
1. Add reading tasks to their todo list
2. Read all required files before starting work
3. Follow all the rules defined in those guides

**BUT YOU MUST ALWAYS INCLUDE THE ANTI-AGENT-CALLING PREAMBLE**

The preamble is required because:
- Sub-agents might accidentally read orchestrator documentation
- The preamble overrides any conflicting instructions
- It prevents infinite loops from agent-calling-agent scenarios

## Reference Information

**All detailed implementation patterns are in specialized files:**

- **Laravel Backend**: `LARAVEL_BACKEND_PATTERNS_GUIDE.md` and `.claude/agents/laravel-*.md`
- **Vue Frontend**: `spa/SPA_PATTERNS_GUIDE.md` and `.claude/agents/vue-*.md`
- **Project Policies**: `PROJECT_POLICIES.md` (zero tech debt, git, danx, architecture)
- **Implementation Details**: `PROJECT_IMPLEMENTATION.md` (paths, builds, commands - sub-agents only)
- **Agent Behaviors**: `AGENT_CORE_BEHAVIORS.md` (anti-loop, tool usage - sub-agents only)

**Key Architecture:**
- Service-Repository-Controller pattern with danx integration
- Team-based access control for all data operations
- Vue 3 Composition API with quasar-ui-danx components
- Zero tech debt policy with immediate replacement requirements

---

**Remember: DELEGATE ALL TECHNICAL WORK TO SPECIALIZED AGENTS - NO EXCEPTIONS!**
**Always include the anti-agent-calling preamble in EVERY agent call!**
