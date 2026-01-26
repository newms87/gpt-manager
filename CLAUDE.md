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

**📋 CORE ENGINEERING PRINCIPLES**

These principles MUST appear in EVERY plan and as the FIRST item in EVERY todo list:

`📋 Core Principles: SOLID/DRY/Zero-Debt/One-Way/Read-First/Test-First/Delegate`

| Principle          | Description                                                     |
|--------------------|-----------------------------------------------------------------|
| **Zero Tech Debt** | No legacy, backwards compat, dead, deprecated, or obsolete code |
| **SOLID**          | Single responsibility, small files, small methods               |
| **DRY**            | Don't repeat yourself, always refactor duplication              |
| **One Way**        | ONE correct way to do everything. Fix at source, not caller     |
| **Read First**     | Read existing implementations before writing                    |
| **Test-First**     | Bug fixes: failing test → fix → verify                          |
| **Delegate**       | Orchestrator → Architect → Engineer → QA (never skip)           |

**🔧 WHAT "REFACTORING" MEANS - READ THIS CAREFULLY**

When the user asks you to **refactor**, this is what they mean:

| Refactoring IS                                      | Refactoring is NOT                          |
|-----------------------------------------------------|---------------------------------------------|
| Breaking large components into small, focused ones  | Removing a few console.logs and calling it done |
| Ensuring EVERY file meets SOLID principles          | Marking SOLID violations as "future work"   |
| Fixing ALL DRY violations immediately               | Listing issues without fixing them          |
| Removing ALL dead code, debug logs, tech debt       | A partial cleanup pass                      |
| Making complex code simple and maintainable         | Cosmetic changes while ignoring architecture |

**⛔ NEVER mark ANY issue as "out of scope" or "future work" during refactoring ⛔**

Refactoring means fixing EVERYTHING. NOW. Not later. Not in a future PR. Not "when we have time."

**Component/File Splitting is MANDATORY:**

If a component has multiple distinct sections with their own logic (header, footer, buttons with complex behavior, dialogs, etc.), each MUST become its own sub-component. A 300+ line SFC with 5 responsibilities is a BLOCKING issue that must be resolved during refactoring.

- **Large files → Small, organized files**: Break apart large files into focused, single-purpose files. Each file should do ONE thing well. Organize related files into logical directories.
- **Large methods → Small, focused methods**: Break complex methods into smaller, well-named helper methods. Each method should be easy to read at a glance. Small methods are easier to test, maintain, and reuse.
- **Duplicated code → Shared utilities**: When you see the same logic in multiple places, extract it into a shared utility, composable, or service. Duplication is a maintenance nightmare - fix it immediately by creating reusable code.

**Priority Order for Refactoring:**

1. **Component/class splitting** - Break apart large files into focused, single-responsibility units
2. **SOLID violations** - Every file must have one clear responsibility
3. **DRY violations** - Extract duplicated code immediately
4. **Dead code removal** - Delete unused exports, imports, functions, variables
5. **Cleanup** - Remove debug logs, fix naming, add documentation

Items 1-4 are REQUIRED. Item 5 is the minimum baseline. You do ALL of them, not just item 5.

**The Zero Tech Debt Policy Applies to Refactoring:**

You cannot "partially refactor." If you identify a SOLID violation during refactoring, you fix it. Period. The zero tech debt policy means we never leave known issues unfixed. "I'll do it later" is not acceptable.

**📝 PLAN WRITING RULES - NO CODE IN PLANS**

When writing plans (in plan mode or plan files):

| ✅ DO                                      | ❌ DON'T                                       |
|-------------------------------------------|-----------------------------------------------|
| Describe requirements in natural language | Write implementation code                     |
| Explain what needs to change              | Show how to implement it line-by-line         |
| List files that need modification         | Include code blocks with full implementations |
| Describe the solution approach            | Dictate specific code patterns                |
| Use bullet points and prose               | Use code snippets longer than 1-2 lines       |

**Plans document WHAT and WHY, not HOW.** The engineers who implement will determine the code.

**Exception:** Tiny snippets (1-2 lines max) are acceptable ONLY to clarify a specific point, such as a method signature
or attribute name.

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

**🚨🚨🚨 CRITICAL: RELATIVE PATHS ONLY - NO EXCEPTIONS 🚨🚨🚨**

**ABSOLUTE PATHS ARE FORBIDDEN IN ALL BASH COMMANDS** - They require manual approval and break autonomous agent
operation.

| ✅ CORRECT (Relative)          | ❌ WRONG (Absolute)                                  |
|-------------------------------|-----------------------------------------------------|
| `./vendor/bin/sail test`      | `/home/newms/web/gpt-manager/vendor/bin/sail test`  |
| `./vendor/bin/sail pint app/` | `/home/user/project/vendor/bin/sail pint`           |
| `yarn build`                  | Any path starting with `/home/`, `/Users/`, `/var/` |

**If a command fails:** Verify you're in the project root with `pwd` - NEVER switch to absolute paths as a "fix".

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

**🔍 DEBUGGING: USE DEBUG COMMANDS, NOT TINKER**

When debugging task processes, errors, or API requests:

1. **ALWAYS run `--help` first** on the relevant debug command
2. **USE the debug command options** - they cover virtually every scenario
3. **NEVER default to tinker** - it means you didn't check the available tools

```bash
# Debug commands (ALWAYS run --help first!):
./vendor/bin/sail artisan debug:extract-data-task-run {id} --help
./vendor/bin/sail artisan debug:task-run {id} --help
./vendor/bin/sail artisan audit:debug --help

# Common options you'll need:
--process={id}    # Debug specific task process
--api-logs        # Show API request/response sent to LLM
--messages        # Show agent thread messages
--recent-errors=N # Find recent errors
```

**See `docs/agents/ORCHESTRATOR_GUIDE.md` for complete debugging documentation.**

**📜 LOG READING: NEVER FILTER LOGS WHEN DEBUGGING**

When reading log files to debug complex problems:

1. **Read the FULL log output** around the timeframe of interest — do NOT grep/filter for specific patterns
2. The whole point of logging is to see the FULL picture of what happened. If you filter, you miss critical context (
   errors, warnings, state transitions) that you don't know exist yet
3. **ONLY filter** if you are 100% certain you only need that specific information AND there is 0% chance that other
   logging might provide invaluable insights
4. When investigating a timeframe, use `tail` or line-range reads to get ALL logs in that window, not grep for specific
   keywords

**🧪 TESTING BEST PRACTICES**

When instructing the `laravel-backend-qa-tester` agent to run tests:

**Run targeted tests:**

- Use `--filter` to run specific test classes or methods
- Example: `./vendor/bin/sail test --filter=FileOrganizationTaskRunnerTest`
- Example: `./vendor/bin/sail test --filter=test_operation_routing`
- Run tests for the specific feature/module you modified

**Examples:**

```bash
./vendor/bin/sail test --filter=FileOrganizationTaskRunner
./vendor/bin/sail test --filter=ClassifierTaskRunner
./vendor/bin/sail test tests/Feature/Services/Task/
./vendor/bin/sail test --filter=test_creates_window_processes_with_correct_operations
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

**🌐 Local Development URL:**

- **Base URL**: `http://localhost:5173/`
- **Dashboard (Start Here)**: `http://localhost:5173/dashboard`
- When testing the app in a browser, always navigate to `http://localhost:5173/` first
- The app will redirect to the dashboard automatically

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

**🔄 Queue Worker Restart (IMPORTANT):**

After making changes to any code that affects **jobs, task runners, or queue-processed code**, you MUST run:

```bash
./vendor/bin/sail artisan queue:restart
```

This signals Horizon workers to restart and pick up the new code. Without this, workers continue running the old code
even though files have changed.

**Key Dependencies:**

- `quasar-ui-danx` - Shared UI component library
- Laravel Sail - Docker development environment
- Tailwind CSS - Utility-first styling

---

**Remember:**

- **Claude Code (Orchestrator)**: Read `docs/agents/ORCHESTRATOR_GUIDE.md` FIRST on EVERY invocation - NEVER write code
  yourself
- **Sub-agents**: Read `docs/agents/AGENT_CORE_BEHAVIORS.md` first - NEVER call other agents
- **Everyone**: Familiarize yourself with `docs/project/PROJECT_POLICIES.md`
