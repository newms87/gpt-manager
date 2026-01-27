# Archived Verbose Instructions

**This file contains verbose instructions that were removed from CLAUDE.md during the configuration refactoring.**

These instructions are archived here for reference but are no longer actively used. The main agent now operates with a simplified, compact configuration.

---

## Sub-Agent Invocation Preamble (ARCHIVED)

Previously, when invoking any sub-agent using the Task tool, this preamble was required:

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

**Why archived:** The orchestrator model has been removed. The main Claude Code agent now writes all code directly.

---

## What "Refactoring" Means Table (ARCHIVED)

| Refactoring IS                                     | Refactoring is NOT                              |
|----------------------------------------------------|-------------------------------------------------|
| Breaking large components into small, focused ones | Removing a few console.logs and calling it done |
| Ensuring EVERY file meets SOLID principles         | Marking SOLID violations as "future work"       |
| Fixing ALL DRY violations immediately              | Listing issues without fixing them              |
| Removing ALL dead code, debug logs, tech debt      | A partial cleanup pass                          |
| Making complex code simple and maintainable        | Cosmetic changes while ignoring architecture    |

**Why archived:** This level of detail is now assumed knowledge. The core principles (SOLID/DRY/Zero-Debt) suffice.

---

## Plan Writing Rules (ARCHIVED)

When writing plans (in plan mode or plan files):

| DO                                         | DON'T                                          |
|--------------------------------------------|------------------------------------------------|
| Describe requirements in natural language  | Write implementation code                      |
| Explain what needs to change               | Show how to implement it line-by-line          |
| List files that need modification          | Include code blocks with full implementations  |
| Describe the solution approach             | Dictate specific code patterns                 |
| Use bullet points and prose                | Use code snippets longer than 1-2 lines        |

**Plans document WHAT and WHY, not HOW.** The engineers who implement will determine the code.

**Exception:** Tiny snippets (1-2 lines max) are acceptable ONLY to clarify a specific point.

**Why archived:** This was for the orchestrator model. Now the main agent writes code directly after planning.

---

## Mandatory Refactoring Todo List Template (ARCHIVED)

```
Standard Refactoring Todo List:
----------------------------------------------------------------------
1. ANALYZE: Read all files in scope, identify line counts
2. SPLIT LARGE FILES: Break apart any file >300 lines (components) or >500 lines (composables)
3. SPLIT LARGE METHODS: Break apart any method >30 lines into smaller focused methods
4. EXTRACT COMPONENTS: Identify distinct UI sections that should be sub-components
5. FIX DRY / SOLID VIOLATIONS: Extract duplicated code into shared utilities
6. REMOVE DEAD / DEBUG CODE: Delete unused imports, exports, functions, variables
7. RUN / FIX TESTS: Ensure all existing tests pass
----------------------------------------------------------------------
```

**How to use:**
1. When user says "refactor", create these tasks using TaskCreate
2. Mark each task in_progress when starting, completed when done
3. Do NOT skip any task - every item must be checked
4. If a task doesn't apply, mark it complete with a note
5. Only consider refactoring DONE when ALL tasks are completed

**Why archived:** The main agent can use TaskCreate dynamically based on context rather than following a rigid template.

---

## Component/File Splitting Guidelines (ARCHIVED)

If a component has multiple distinct sections with their own logic (header, footer, buttons with complex behavior, dialogs, etc.), each MUST become its own sub-component. A 300+ line SFC with 5 responsibilities is a BLOCKING issue.

**Thresholds:**
- Components: >300 lines = split required
- Composables/Services: >500 lines = split required
- Methods: >30 lines = extract to helper methods

**Priority Order:**
1. Component/class splitting - Break apart large files
2. SOLID violations - Every file must have one clear responsibility
3. DRY violations - Extract duplicated code immediately
4. Dead code removal - Delete unused exports, imports, functions
5. Cleanup - Remove debug logs, fix naming

**Why archived:** These are now implicit in the core principles (SOLID/DRY). The main agent applies them as needed.

---

## Why Debug Commands Over Tinker (ARCHIVED)

1. **Debug commands are pre-approved** - they run autonomously without blocking
2. **Tinker requires approval** - it blocks autonomous operation and slows debugging
3. **Debug commands are comprehensive** - they have options for every common scenario
4. **Tinker is ad-hoc exploration** - it means you didn't check the available tools first

**Why archived:** The rule to prefer debug commands remains in `debugging.md`. This explanation is excessive.

---

## Agent Specialization Guide Table (ARCHIVED)

**Laravel Backend Work:**
- `laravel-backend-architect` - Planning/architecture for backend features
- `laravel-backend-engineer` - Writing/editing Laravel code
- `laravel-backend-qa-tester` - Writing/updating PHPUnit tests

**Vue SPA Frontend Work:**
- `vue-spa-architect` - Planning/architecture for frontend features
- `vue-spa-engineer` - Writing/editing Vue components
- `vue-spa-reviewer` - Code review and refactoring suggestions

**Why archived:** The orchestrator model has been removed. Agent descriptions in their config files are sufficient for Claude to auto-delegate when needed.

---

**End of archived instructions.**
