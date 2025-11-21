# Agent Core Behaviors - READ THIS FIRST

**CRITICAL**: Every agent MUST read this file in full before starting ANY work. No exceptions.

## 🚨 MANDATORY FIRST STEPS FOR ALL AGENTS

Before starting any work, you MUST:

1. **ADD TO TODO LIST**: "Read AGENT_CORE_BEHAVIORS.md in full" (mark as in_progress)
2. **ADD TO TODO LIST**: "Read PROJECT_POLICIES.md in full"
3. **ADD TO TODO LIST**: "Read PROJECT_IMPLEMENTATION.md in full"
4. **ADD TO TODO LIST**: "Read domain-specific guide" (Laravel or Vue)
   - Laravel agents: "Read LARAVEL_BACKEND_PATTERNS_GUIDE.md in full"
   - Vue agents: "Read spa/SPA_PATTERNS_GUIDE.md in full"
5. **READ ALL FOUR FILES COMPLETELY** before proceeding with any implementation

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

## Shared Project Documentation

**Project-wide rules are split into two files:**

### PROJECT_POLICIES.md (Policies - Read First)
- Zero tech debt policy
- Git operations (read-only)
- Danx library philosophy
- Architecture patterns
- Code quality philosophy

### PROJECT_IMPLEMENTATION.md (Technical Details - Read Second)
- File path requirements (relative paths only)
- Build commands (yarn build, sail test, sail pint)
- Docker/Sail commands
- Authentication & API testing
- Code quality standards
- PHPUnit testing standards

**You MUST read BOTH files before starting work.**

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


## Reporting Back

When you complete your work, provide:

1. **Summary**: Brief description of what was changed
2. **Files Modified**: List all files you changed with line numbers
3. **Testing**: Results of any tests you ran
4. **Next Steps**: Any follow-up work needed (if applicable)

Be concise but complete. Focus on what actually changed, not what you considered doing.

---

**Remember: You are a specialized agent with full authority in your domain. Read your domain-specific guide, then implement changes directly using available tools. Never delegate to other agents.**
