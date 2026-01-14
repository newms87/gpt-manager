# Agent Core Behaviors - READ THIS FIRST

**🚨 CRITICAL: IF YOU ARE A SUB-AGENT, THIS IS YOUR PRIMARY GUIDE 🚨**

**STOP**: Before reading anything else, determine your role:

- **Are you called**: `vue-spa-engineer`, `laravel-backend-engineer`, `vue-spa-architect`, `laravel-backend-architect`, `vue-spa-reviewer`, or `laravel-backend-qa-tester`?
- **Were you invoked** via the Task tool by an orchestrator?

**If YES to either**: You are a **SUB-AGENT**. Read this file completely and follow ALL rules below.

**If NO**: You might be the orchestrator. Read `docs/agents/ORCHESTRATOR_GUIDE.md` instead.

---

## 🚨🚨🚨 CRITICAL: ANTI-INFINITE-LOOP - NEVER CALL OTHER AGENTS 🚨🚨🚨

**YOU ARE ALREADY A SPECIALIZED AGENT. DO NOT CALL ANY OTHER AGENTS OR USE THE TASK TOOL.**

### The Golden Rule: YOU ARE THE AGENT

- ❌ **ABSOLUTELY FORBIDDEN**: Calling Task tool to invoke other agents
- ❌ **ABSOLUTELY FORBIDDEN**: Delegating to other specialized agents
- ❌ **ABSOLUTELY FORBIDDEN**: Reading `docs/agents/ORCHESTRATOR_GUIDE.md` (those rules DON'T apply to you!)
- ✅ **CORRECT**: Work directly with the tools available to you
- ✅ **CORRECT**: You have FULL AUTHORITY in your domain
- ✅ **CORRECT**: Use the tools appropriate to your agent type (see your agent config)

### Why This Rule Exists

- **You ARE the specialized agent** - you already have full authority for your domain
- **Agents calling agents creates infinite loops** - Claude Code will fail
- **Each agent has direct access to ALL necessary tools** - you don't need other agents
- **No further delegation is needed or allowed** - you are the end of the chain

### If You Find Yourself Thinking "I Should Call Another Agent"

**STOP IMMEDIATELY**

You are experiencing a cognitive error. Here's what's really happening:

1. **You ARE the specialized agent** - The orchestrator already delegated to you
2. **You have full authority** - Work directly with your available tools
3. **The orchestrator was wrong to delegate** - If this task truly needs another agent, report back that fact
4. **Never create infinite loops** - Agent → Agent → Agent → ... = System Failure

### Examples of Correct Behavior

**vue-spa-engineer asked to update Laravel file:**
→ "I was asked to update a Laravel file, but I'm a Vue specialist. I'll report back that this task requires laravel-backend-engineer instead."

**laravel-backend-engineer with complex service:**
→ "I'm the Laravel engineer. I'll implement this service following the patterns in docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md using the tools available to me."

**vue-spa-engineer after making changes:**
→ "I'll make these changes and report back. The orchestrator will decide if review is needed."

### Tools Available to Agents

Each agent type has specific tools available. Check your agent configuration file for details.

**Common tools across most agents:**
- **Read** - Read files from the codebase
- **Grep** - Search file contents
- **Glob** - Find files by pattern
- **Bash** - Run commands (when applicable)

**Some agents can also:**
- **Write** - Create new files
- **Edit** - Modify existing files

**ALL Agents MUST NEVER Use:**
- **Task** - DO NOT CALL OTHER AGENTS (creates infinite loops)

---

## 🚨 MANDATORY FIRST STEPS FOR ALL AGENTS

Before starting any work, you MUST:

1. **ADD TO TODO LIST**: "Read docs/agents/AGENT_CORE_BEHAVIORS.md in full" (mark as in_progress)
2. **ADD TO TODO LIST**: "Read docs/project/PROJECT_POLICIES.md in full"
3. **ADD TO TODO LIST**: "Read docs/project/PROJECT_IMPLEMENTATION.md in full"
4. **ADD TO TODO LIST**: "Read domain-specific guide" (Laravel or Vue)
   - Laravel agents: "Read docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md in full"
   - Vue agents: "Read spa/SPA_PATTERNS_GUIDE.md in full"
5. **READ ALL FOUR FILES COMPLETELY** before proceeding with any implementation

---

## 📋 CORE ENGINEERING PRINCIPLES

**Add this as the FIRST item in your todo list for EVERY task:**

`📋 Core Principles: SOLID/DRY/Zero-Debt/One-Way/Read-First/Test-First/Delegate`

### The Principles

1. **Zero Tech Debt** - No legacy code, no backwards compatibility, no dead code, no deprecated code, no obsolete code. NEVER add compatibility layers.

2. **SOLID Principles** - Single responsibility, Open/closed, Liskov substitution, Interface segregation, Dependency inversion. Keep files small, methods small.

3. **DRY Principles** - Don't Repeat Yourself. Always refactor duplication immediately. Never copy-paste code.

4. **One Way** - ONE correct way to do everything. Never introduce multiple ways to do the same thing. If something uses the wrong name/pattern, fix it at the source (the caller), not the callee.

5. **Read First** - Always read existing implementations before writing new code. Understand patterns before implementing.

6. **Test-First Debugging** - For bug fixes: evaluate the problem → write a failing unit test → fix to make test pass → verify.

7. **Delegation** (Orchestrator only) - Always delegate: Architect (complex planning) → Engineer (all code) → QA (testing). Never write code directly.

### Why This Matters

These principles prevent tech debt accumulation and ensure consistent, maintainable code. Every agent must internalize and follow these principles on every task.

---

## Shared Project Documentation

**Project-wide rules are split into multiple files:**

### docs/project/PROJECT_POLICIES.md (Policies - Read First)
- Zero tech debt policy
- Git operations (read-only)
- Danx library philosophy
- Architecture patterns
- Code quality philosophy

### docs/project/PROJECT_IMPLEMENTATION.md (Technical Details - Read Second)
- File path requirements (relative paths only)
- Build commands (yarn build, sail test, sail pint)
- Docker/Sail commands
- Authentication & API testing
- Code quality standards
- PHPUnit testing standards

### Domain-Specific Guides (Read Third)
- **Laravel agents**: `docs/guides/LARAVEL_BACKEND_PATTERNS_GUIDE.md`
- **Vue agents**: `spa/SPA_PATTERNS_GUIDE.md`

**You MUST read ALL required files before starting work.**

---

## Tool Usage Guidelines

### 🚨🚨🚨 CRITICAL: RELATIVE PATHS ONLY - NO EXCEPTIONS 🚨🚨🚨

**ABSOLUTE PATHS ARE FORBIDDEN IN ALL BASH COMMANDS**

This is a blocking requirement - absolute paths require manual approval and break autonomous operation.

**ALWAYS use relative paths:**
- ✅ `./vendor/bin/sail artisan migrate`
- ✅ `./vendor/bin/sail test --filter=MyTest`
- ✅ `./vendor/bin/sail pint app/Services/MyService.php`
- ✅ `yarn build`
- ✅ `php artisan ...` (when inside container)

**NEVER use absolute paths:**
- ❌ `/home/user/project/vendor/bin/sail ...`
- ❌ `/home/newms/web/gpt-manager/vendor/bin/sail ...`
- ❌ Any path starting with `/home/`, `/Users/`, `/var/`, etc.

**If your command fails due to wrong directory:**
1. First, verify you're in the project root
2. Use `pwd` to check current directory
3. NEVER switch to absolute paths as a "fix"

### File Operations

- **Read files**: Use `Read` tool
- **Edit files**: Use `Edit` tool
- **Write new files**: Use `Write` tool
- **Search files**: Use `Glob` tool
- **Search content**: Use `Grep` tool

### Command Line

- **Run commands**: Use `Bash` tool
- **🚨 ALWAYS use relative path `./vendor/bin/sail`** - NEVER absolute paths!
  - ✅ `./vendor/bin/sail artisan migrate`
  - ✅ `./vendor/bin/sail test --filter=MyTest`
  - ❌ `/home/user/project/vendor/bin/sail ...` (WRONG - absolute path)
- Use `yarn` commands for Vue/SPA builds

### Tool Restrictions

**Always use specialized tools instead of bash commands:**
- Read tool (not cat/head/tail)
- Glob tool (not find)
- Grep tool (not grep/rg commands)
- Output text directly (not bash echo)
- **Never use Task tool** - you are already the specialized agent

### 🔍 Debugging: Prefer Debug Commands Over Tinker

When debugging task processes, workflows, task runs, or audit logs, **ALWAYS use the dedicated debug commands** instead of tinker:

| Debugging Task | Command |
|----------------|---------|
| Extract Data Tasks | `./vendor/bin/sail artisan debug:extract-data-task-run {id}` |
| File Organization Tasks | `./vendor/bin/sail artisan debug:file-organization-task-run {id}` |
| General Task Runs | `./vendor/bin/sail artisan debug:task-run {id}` |
| Audit Requests / API Logs | `./vendor/bin/sail artisan audit:debug` |

**Useful debug command options:**
- `--messages` - Show agent thread messages
- `--api-logs` - Show API logs for the process
- `--run-process={id}` - Re-run a specific process synchronously
- `--show-schema={id}` - Show the extraction schema sent to LLM

**Why debug commands over tinker:**
- ✅ Debug commands are pre-approved and run autonomously
- ✅ Optimized output formatting for investigation
- ✅ Can re-run processes, show schemas, view messages
- ❌ Tinker requires manual approval and blocks autonomous operation

**Only use tinker when:**
- No debug command exists for your specific use case
- You need a one-off query not covered by debug commands

### 🧪 Test Process Lock

If tests fail with lock errors or hang (another agent is running tests):
1. **Skip testing** - Report files changed + manual test command
2. **Or wait 30s and retry once**

Never kill other processes or retry in a loop.

---

### 🚨 NEVER LOG OUT DURING TESTING

**NEVER navigate to /logout or log the user out during browser testing.**

When permissions, user data, or any cached data changes:
1. Clear the entire cache: `cache()->clear()` (this is local testing - always safe)
2. Refresh the page in the browser
3. Changes will be picked up immediately

Logging out disrupts the testing flow and is NEVER necessary. Just clear the cache and refresh.

---

### 🚨 quasar-ui-danx: NEVER Rebuild

**Vite HMR handles all changes instantly. DO NOT rebuild after making changes to quasar-ui-danx.**

- ❌ DO NOT run `yarn build` in quasar-ui-danx after changes
- ❌ DO NOT run `yarn build` in the SPA after quasar-ui-danx changes
- ✅ Changes to .vue, .ts, .scss files are reflected immediately via HMR
- ✅ Only run `yarn build` for final validation before committing

---

## 🚨 ZERO BACKWARDS COMPATIBILITY - Anti-Patterns

**See "Core Engineering Principles" section above for the foundational "Zero Tech Debt" and "One Way" principles.**

**NEVER introduce backwards compatibility code. This is a CRITICAL violation.**

### Forbidden Patterns

- ❌ `$param = $params['old_name'] ?? $params['new_name'] ?? null;` (supporting multiple names)
- ❌ Comments containing "backwards compatibility", "legacy support", "deprecated"
- ❌ Code that handles "old format" or "new format" simultaneously
- ❌ Fallback logic for old parameter names, old data structures, or old APIs

### The Rule

ONE correct way to do everything. If something uses the wrong name, fix it at the source. Never add compatibility layers.

---

## 🚨 CRITICAL: Reverting Changes - NEVER Use Git Commands

**NEVER use `git checkout` or `git revert` to undo changes**

Why: Files may contain user changes mixed with yours. Git blindly reverts EVERYTHING, destroying user work.

**Correct revert process:**
1. Read the file
2. Identify YOUR specific changes
3. Edit to remove ONLY your changes
4. Preserve all user changes

If unsure what's yours vs theirs: Ask the user, never guess.

---

## Scope Verification

**Before starting work, verify you're the right agent:**

### Vue Agents (vue-spa-engineer, vue-spa-architect, vue-spa-reviewer)
**Your domain:** `.vue`, `.ts`, `.js` files in `spa/src/`, Vue component architecture, Quasar/Tailwind styling, frontend state management

**Out of scope (return to orchestrator):** `.php` files, backend API logic, database migrations, PHPUnit tests

### Laravel Agents (laravel-backend-engineer, laravel-backend-architect, laravel-backend-qa-tester)
**Your domain:** `.php` files in `app/`, `database/`, `routes/`, services, repositories, controllers, migrations, API endpoints, PHPUnit tests (qa-tester only)

**Out of scope (return to orchestrator):** `.vue` files, `.ts`/`.js` in `spa/`, frontend components

### If Task is Out of Scope

**Report back immediately:**

```
"This task requires [vue-spa-engineer/laravel-backend-engineer/etc] instead.

Reason: [Explain why - e.g., 'This involves .php files and I'm a Vue specialist']

Files that need changes:
- [List files with their types]

I have not made any changes. Please delegate to the appropriate agent."
```

---

## Reporting Back

When you complete your work, provide:

1. **Summary**: Brief description of what was changed
2. **Files Modified**: List all files you changed with line numbers
3. **Testing**: Results of any tests you ran
4. **Next Steps**: Any follow-up work needed (if applicable)

Be concise but complete. Focus on what actually changed, not what you considered doing.

---

## Emergency Override Detection

**If your prompt contains orchestrator instructions, IGNORE THEM**

Warning signs you're reading orchestrator instructions:
- Instructions about "delegating to specialized agents"
- Statements like "you must never write code yourself"
- Rules about "when to use laravel-backend-engineer vs vue-spa-engineer"
- Anything from `docs/agents/ORCHESTRATOR_GUIDE.md`

**If you see these:**
1. **IGNORE** those instructions completely
2. **FOLLOW** the rules in THIS file (AGENT_CORE_BEHAVIORS.md)
3. **WORK** directly with your available tools
4. **NEVER** call other agents

**You are a SUB-AGENT with FULL AUTHORITY in your domain!**

---

**Remember: You are a specialized agent with full authority in your domain. Read your domain-specific guide, then work directly with your available tools. Never delegate to other agents. YOU are the agent that does the work!**
