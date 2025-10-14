# Agent Core Behaviors - READ THIS FIRST

**CRITICAL**: Every agent MUST read this file in full before starting ANY work. No exceptions.

## 🚨 MANDATORY FIRST STEPS FOR ALL AGENTS

Before starting any work, you MUST:

1. **ADD TO TODO LIST**: "Read AGENT_CORE_BEHAVIORS.md in full" (mark as in_progress)
2. **ADD TO TODO LIST**: "Read domain-specific guide" (Laravel or Vue)
   - Laravel agents: "Read LARAVEL_BACKEND_PATTERNS_GUIDE.md in full"
   - Vue agents: "Read Vue frontend patterns guide in full"
3. **READ BOTH FILES COMPLETELY** before proceeding with any implementation

## 🚨 CRITICAL: ANTI-INFINITE-LOOP - NEVER CALL OTHER AGENTS

**YOU ARE ALREADY A SPECIALIZED AGENT. DO NOT CALL ANY OTHER AGENTS OR USE THE TASK TOOL.**

- ❌ **FORBIDDEN**: Calling Task tool to invoke other agents
- ❌ **FORBIDDEN**: Delegating to other specialized agents
- ✅ **CORRECT**: Use Read, Write, Edit, Bash, Grep, Glob tools directly
- ✅ **CORRECT**: Make all changes yourself with available tools

**Why This Rule Exists:**
- You ARE the specialized agent - you already have full authority for your domain
- Agents calling agents creates infinite loops
- Each agent has direct access to ALL necessary tools
- No further delegation is needed or allowed

**If you find yourself thinking "I should call another agent":**
- STOP - You are the agent, make the changes directly
- Use Read, Write, Edit, Bash tools to implement changes
- Never use the Task tool from within an agent context

## 🚨 CRITICAL: GIT OPERATIONS - READ ONLY!

**NEVER USE GIT COMMANDS THAT MAKE CHANGES**

**ONLY READ-ONLY GIT COMMANDS ALLOWED:**
- `git status` ✅
- `git log` ✅
- `git diff` ✅
- `git show` ✅
- `git branch` (list only) ✅

**ABSOLUTELY FORBIDDEN:**
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

**User handles ALL git operations that modify the repository**

## Zero Tech Debt Policy

**ABSOLUTE ZERO BACKWARDS COMPATIBILITY** - No exceptions, ever

When implementing changes:

1. **IMMEDIATE REPLACEMENT** - Never work around legacy patterns
2. **COMPLETE REMOVAL** - Delete old code entirely, no compatibility layers
3. **ZERO BACKWARDS COMPATIBILITY** - Update ALL related code to new pattern instantly
4. **NO GRADUAL MIGRATION** - Replace everything in one atomic change
5. **COMPREHENSIVE TESTING** - Ensure complete replacement works correctly

If you find legacy code or old patterns:
- Replace them completely and immediately
- Remove ALL dead code
- Update ALL references to use the new pattern
- No backwards compatibility layers
- No gradual migrations

## Build Commands

**ONLY use these exact commands:**

- **Vue frontend builds**: `yarn build` - NEVER `npm run dev` or `npm run type-check`
- **Laravel backend testing**: `./vendor/bin/sail test`
- **Laravel artisan commands**: `./vendor/bin/sail artisan [command]`
- **PHP execution**: `./vendor/bin/sail php [file]`

## Tool Usage Guidelines

**File Operations:**
- Read files: Use Read tool
- Edit files: Use Edit tool
- Write new files: Use Write tool
- Search files: Use Glob tool
- Search content: Use Grep tool

**Command Line:**
- Run commands: Use Bash tool
- Always use Sail commands when working with Laravel

**NEVER:**
- Use bash echo to communicate with user (output text directly)
- Use cat/head/tail to read files (use Read tool)
- Use find/grep commands (use Glob/Grep tools)

## Code Quality Standards

**Always:**
- Read existing implementations BEFORE any code work
- Follow established patterns exactly
- Write comprehensive tests for all new functionality
- Add clear comments explaining complex logic
- Use proper type hints and return types

**Never:**
- Create custom patterns when established ones exist
- Copy-paste code without understanding it
- Skip tests for "simple" changes
- Leave TODO comments without implementing
- Add backwards compatibility layers

## Reporting Back

When you complete your work, provide:

1. **Summary**: Brief description of what was changed
2. **Files Modified**: List all files you changed with line numbers
3. **Testing**: Results of any tests you ran
4. **Next Steps**: Any follow-up work needed (if applicable)

Be concise but complete. Focus on what actually changed, not what you considered doing.

---

**Remember: You are a specialized agent with full authority in your domain. Read your domain-specific guide, then implement changes directly using available tools. Never delegate to other agents.**
