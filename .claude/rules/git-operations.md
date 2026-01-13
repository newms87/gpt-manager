# Git Operations

## Git is READ-ONLY

Agents should only use git for reading status, not for making changes.

### Allowed git operations:
- `git status` - Check current state
- `git diff` - View changes
- `git log` - View history

### Not allowed without explicit user request:
- `git add` - Staging changes
- `git commit` - Committing changes
- `git push` - Pushing to remote
- `git checkout` - Switching branches or reverting
- `git revert` - Reverting commits

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
