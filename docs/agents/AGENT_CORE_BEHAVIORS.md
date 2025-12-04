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
