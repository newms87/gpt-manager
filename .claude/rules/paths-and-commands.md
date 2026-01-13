# Paths and Commands

## CRITICAL: RELATIVE PATHS ONLY - NO EXCEPTIONS

**ABSOLUTE PATHS ARE FORBIDDEN IN ALL BASH COMMANDS**

This is a blocking requirement - absolute paths require manual approval and break autonomous operation.

### ALWAYS use relative paths:
- `./vendor/bin/sail artisan migrate`
- `./vendor/bin/sail test --filter=MyTest`
- `./vendor/bin/sail pint app/Services/MyService.php`
- `yarn build`

### NEVER use absolute paths:
- `/home/user/project/vendor/bin/sail ...`
- `/home/newms/web/gpt-manager/vendor/bin/sail ...`
- Any path starting with `/home/`, `/Users/`, `/var/`, etc.

### If your command fails due to wrong directory:
1. First, verify you're in the project root
2. Use `pwd` to check current directory
3. NEVER switch to absolute paths as a "fix"

## Tool Usage

**Always use specialized tools instead of bash commands:**
- Read tool (not cat/head/tail)
- Glob tool (not find)
- Grep tool (not grep/rg commands)
- Output text directly (not bash echo)

## Common Commands

### Laravel
- `./vendor/bin/sail test` - Run tests
- `./vendor/bin/sail test --filter=TestName` - Run specific test
- `./vendor/bin/sail pint <file>` - Format code
- `./vendor/bin/sail artisan fix` - Fix permissions (never use chmod!)

### Vue/SPA
- `yarn build` - Build and validate

## quasar-ui-danx: NEVER Rebuild

**Vite HMR handles all changes instantly. DO NOT rebuild after making changes to quasar-ui-danx.**

- DO NOT run `yarn build` in quasar-ui-danx after changes
- DO NOT run `yarn build` in the SPA after quasar-ui-danx changes
- Changes to .vue, .ts, .scss files are reflected immediately via HMR
- Only run `yarn build` for final validation before committing
