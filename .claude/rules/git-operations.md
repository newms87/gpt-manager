# Git Operations

## CRITICAL: Wait for Explicit User Instruction

**NEVER stage (`git add`) or commit (`git commit`) until the user explicitly tells you to.**

This is a blocking requirement. When the user says "commit", only then should you stage and commit.

## CRITICAL: Never Reset or Remove Other Changes

**NEVER use `git reset` or any command that would unstage or remove changes made by other agents or the user.**

When committing:
1. Stage ONLY your new changes (use `git add <specific-files>`)
2. NEVER reset the staging area
3. NEVER unstage files that were already staged
4. Commit everything that is staged together

## Git is READ-ONLY (Until Told Otherwise)

Agents should only use git for reading status, not for making changes.

### Allowed git operations (always):
- `git status` - Check current state
- `git diff` - View changes
- `git log` - View history

### Not allowed without explicit user request:
- `git add` - Staging changes
- `git commit` - Committing changes
- `git push` - Pushing to remote
- `git checkout` - Switching branches or reverting
- `git revert` - Reverting commits
- `git reset` - Resetting staging area or commits

## Reverting Changes - NEVER Use Git Commands

**NEVER use `git checkout` or `git revert` to undo changes**

### Why:
Files may contain user changes mixed with yours. Git blindly reverts EVERYTHING, destroying user work.

### Correct revert process:
1. Read the file
2. Identify YOUR specific changes
3. Edit to remove ONLY your changes
4. Preserve all user changes

**If unsure what's yours vs theirs:** Ask the user, never guess.
