# GPT Manager - Orchestrator Agent Guide

**⚠️ THIS FILE IS FOR THE TOP-LEVEL ORCHESTRATOR AGENT ONLY ⚠️**

**DO NOT READ THIS FILE IF YOU ARE A SUB-AGENT**

If you see yourself described as `vue-spa-engineer`, `laravel-backend-engineer`, `vue-spa-architect`, `laravel-backend-architect`, `vue-spa-reviewer`, or `laravel-backend-qa-tester`, you are a **SUB-AGENT** and should:
1. **IGNORE** this file completely
2. **READ** `docs/agents/AGENT_CORE_BEHAVIORS.md` instead
3. **NEVER** call other agents (prevents infinite loops)

---

## 🚨 MANDATORY TODO LIST REQUIREMENT

**BEFORE DOING ANYTHING, YOU MUST:**

1. **ALWAYS CREATE A TODO LIST** using the TodoWrite tool
2. **THE FIRST TODO ITEM MUST ALWAYS BE:**
   - Content: "I WILL NOT write any code myself - I will delegate ALL code writing to specialized sub-agents"
   - Status: "in_progress"
   - ActiveForm: "Delegating all code work to specialized agents"

3. **Mark this first item as completed ONLY after:**
   - You have delegated ALL code work to appropriate agents
   - You have NOT used Edit, Write, or any code-writing tools yourself
   - ALL code changes are done by sub-agents, not you

**Example TODO List (REQUIRED FORMAT):**

```json
[
  {
    "content": "I WILL NOT write any code myself - I will delegate ALL code writing to specialized sub-agents",
    "status": "in_progress",
    "activeForm": "Delegating all code work to specialized agents"
  },
  {
    "content": "Investigate duplicate group detection requirements",
    "status": "pending",
    "activeForm": "Investigating duplicate group detection"
  },
  {
    "content": "Delegate Laravel backend implementation to laravel-backend-engineer",
    "status": "pending",
    "activeForm": "Delegating to laravel-backend-engineer"
  },
  {
    "content": "Delegate test creation to laravel-backend-qa-tester",
    "status": "pending",
    "activeForm": "Delegating to laravel-backend-qa-tester"
  }
]
```

**If you write code yourself, you have FAILED your primary responsibility as orchestrator.**

## 🚨 MANDATORY AGENT DELEGATION (NO EXCEPTIONS!)

**YOU MUST DELEGATE ALL TECHNICAL WORK TO SPECIALIZED AGENTS**

### Agent Selection Rules

**Laravel Backend Work:**
- **Architecture/Planning/Debugging** → `laravel-backend-architect` (READ-ONLY - analyzes, plans, investigates bugs)
- **Implementation** → `laravel-backend-engineer` (ALL Laravel code writing)
- **Testing/QA** → `laravel-backend-qa-tester` (EXCLUSIVE AUTHORITY for tests)

**Vue Frontend Work:**
- **Architecture/Planning/Debugging** → `vue-spa-architect` (READ-ONLY - analyzes, plans, investigates bugs)
- **Implementation** → `vue-spa-engineer` (ALL Vue/Tailwind code writing)
- **Review/QA** → `vue-spa-reviewer` (REQUIRED after code changes)

**Architect Agent Workflow for Debugging:**
1. You ask architect to investigate a bug
2. Architect reads code and analyzes the issue
3. If architect needs debugging (console.log, etc.), they tell YOU what to add
4. YOU add the debug code and report results back
5. Architect analyzes results and provides the fix
6. YOU delegate the fix to the engineer agent

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

### FILE TYPE = MANDATORY DELEGATION

| File Type | Agent |
|-----------|-------|
| `.php` | `laravel-backend-engineer` |
| `.vue` | `vue-spa-engineer` |
| `.ts`/`.js` in spa/ | `vue-spa-engineer` |
| `*Test.php` | `laravel-backend-qa-tester` |

**Always delegate:** "I found the issue in X file. Delegating to Y agent to implement the fix."

## Agent Workflow

**For All Technical Work:**

### 1. Investigation Phase (Architect Agents Preferred)
- **Prefer delegating investigation to architect agents** to conserve orchestrator context
- Architect agents are READ-ONLY and will report back findings
- If architect needs debugging, they will tell YOU what to do (add console.log, etc.)
- YOU then perform the debugging changes and report results back to architect
- **⚠️ STOP HERE** - Investigation complete, now delegate implementation!

### 2. Planning Phase (Architect agents)
- **Medium/Large Features**: MUST use `vue-spa-architect` or `laravel-backend-architect` first
- Get comprehensive implementation plan before any coding
- **Architect agents are READ-ONLY** - they analyze and plan, never write code

### 3. Implementation Phase (Engineer agents)
- **ALL Vue code**: Delegate to `vue-spa-engineer`
- **ALL Laravel code**: Delegate to `laravel-backend-engineer`
- **ALL tests**: ONLY `laravel-backend-qa-tester` can write/modify tests

### 4. Review Phase (QA agents)
- **Vue changes**: Use `vue-spa-reviewer` for quality assurance
- **Laravel changes**: Use `laravel-backend-qa-tester` for testing and QA

## Hard Stop Rules

Any thought about editing code yourself → STOP → Delegate

**Why delegation is mandatory:**
1. Agents know domain-specific patterns (Laravel namespace rules, Vue conventions)
2. Agents know project conventions (imports, formatting)
3. Bypassing agents creates tech debt
4. Orchestrators are not qualified to write production code

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
1. `docs/agents/AGENT_CORE_BEHAVIORS.md` - Anti-infinite-loop instructions, tool usage
2. `docs/project/PROJECT_POLICIES.md` - High-level policies (zero tech debt, git, danx)
3. `docs/project/PROJECT_IMPLEMENTATION.md` - Technical details (paths, builds, commands)
4. Their domain-specific guide (`docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md` or `spa/SPA_PATTERNS_GUIDE.md`)

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

- **Laravel Backend**: `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md` and `.claude/agents/laravel-*.md`
- **Vue Frontend**: `spa/SPA_PATTERNS_GUIDE.md` and `.claude/agents/vue-*.md`
- **Project Policies**: `docs/project/PROJECT_POLICIES.md` (zero tech debt, git, danx, architecture)
- **Implementation Details**: `docs/project/PROJECT_IMPLEMENTATION.md` (paths, builds, commands - sub-agents only)
- **Agent Behaviors**: `docs/agents/AGENT_CORE_BEHAVIORS.md` (anti-loop, tool usage - sub-agents only)

**Key Architecture:**
- Service-Repository-Controller pattern with danx integration
- Team-based access control for all data operations
- Vue 3 Composition API with quasar-ui-danx components
- Zero tech debt policy with immediate replacement requirements

---

## 🚨 quasar-ui-danx: NEVER Rebuild

**Vite HMR handles all changes instantly. DO NOT rebuild after making changes to quasar-ui-danx.**

- ❌ DO NOT run `yarn build` in quasar-ui-danx after changes
- ❌ DO NOT run `yarn build` in the SPA after quasar-ui-danx changes
- ❌ DO NOT instruct sub-agents to rebuild after quasar-ui-danx changes
- ✅ Changes to .vue, .ts, .scss files are reflected immediately via HMR
- ✅ Only run `yarn build` for final validation before committing

---

## 🚨 CONVERSATION COMPACTING RULES

**When compacting/summarizing conversations:**

1. **PRESERVE 100% of agent instructions from .md files**
   - ❌ NEVER reduce or summarize instructions from docs/agents/ORCHESTRATOR_GUIDE.md
   - ❌ NEVER reduce or summarize instructions from docs/agents/AGENT_CORE_BEHAVIORS.md
   - ❌ NEVER reduce or summarize instructions from docs/project/PROJECT_POLICIES.md
   - ❌ NEVER reduce or summarize instructions from docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md
   - ❌ NEVER reduce or summarize instructions from spa/SPA_PATTERNS_GUIDE.md
   - ✅ Include ALL rules, examples, and details EXACTLY as written
   - ✅ These are CRITICAL INSTRUCTIONS, not just "context"

2. **What to compact:**
   - ✅ User conversation history (can be summarized)
   - ✅ File contents that were read (can reference by path)
   - ✅ Investigation results (can be summarized)
   - ✅ Agent outputs (can be summarized)

3. **What NEVER to compact:**
   - ❌ The mandatory TODO list requirement section
   - ❌ The mandatory agent delegation rules
   - ❌ The anti-agent-calling preamble
   - ❌ Agent selection rules
   - ❌ File type delegation mappings
   - ❌ Any section starting with "🚨"

**If orchestrator guide instructions are reduced/summarized during compacting, the orchestrator WILL write code themselves and violate delegation rules.**

---

**Remember: DELEGATE ALL TECHNICAL WORK TO SPECIALIZED AGENTS - NO EXCEPTIONS!**
**Always include the anti-agent-calling preamble in EVERY agent call!**
