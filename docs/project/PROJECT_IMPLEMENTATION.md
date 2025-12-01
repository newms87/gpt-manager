# GPT Manager - Implementation Standards

**This file contains technical implementation details for sub-agents.**

**⚠️ ORCHESTRATOR DOES NOT READ THIS FILE** - These are implementation-level details for code-writing agents only.

Read this file to understand specific commands, syntax requirements, and technical conventions.

---

## File Path Requirements

**🚨 ABSOLUTE REQUIREMENT: RELATIVE PATHS ONLY! 🚨**

- **NEVER EVER USE ABSOLUTE PATHS** - They will NEVER work in any command or tool
- **ALWAYS USE RELATIVE PATHS** - All file paths must be relative to current working directory

**Examples of CORRECT paths:**
- `spa/src/components/MyComponent.vue` ✅
- `app/Services/MyService.php` ✅
- `config/google-docs.php` ✅
- `./vendor/bin/sail test` ✅

**Examples of INCORRECT paths:**
- `/home/user/web/project/spa/src/components/MyComponent.vue` ❌
- `/home/newms/web/gpt-manager/app/Services/MyService.php` ❌
- `/var/www/config/google-docs.php` ❌

**This applies to ALL tools and ALL agents** - No exceptions!

**Why:** Absolute paths break when working directories change or when code runs in different environments (local, CI/CD, Docker, etc.).

---

## Build Commands

**ONLY use these exact commands:**

### Vue Frontend
- **Build**: `yarn build` (from spa/ directory)
  - ✅ Use for production builds and validation
  - ❌ NEVER `npm run dev` or `npm run type-check`

### Laravel Backend
- **Testing**: `./vendor/bin/sail test`
  - Run all tests
  - Add `--filter=TestName` for specific tests

- **Code Formatting**: `./vendor/bin/sail pint <file>`
  - **ALWAYS run after modifying ANY PHP file**
  - Ensures consistent code style

- **Artisan Commands**: `./vendor/bin/sail artisan [command]`
  - All Laravel CLI commands go through Sail

- **PHP Execution**: `./vendor/bin/sail php [file]`
  - When running PHP scripts directly

**Follow these commands exactly - NO EXCEPTIONS**

**Why:** These commands ensure code runs in the correct Docker environment with proper dependencies and configurations.

---

## Docker/Sail Commands

### Required Commands

- Use `./vendor/bin/sail artisan` for all artisan commands
- Run `./vendor/bin/sail artisan fix` for permission issues
- Never use chmod on files to fix permissions - always use `./vendor/bin/sail artisan fix`
- Never use the rg command, use grep instead
- When attempting to run PHP files, always use `./vendor/bin/sail php`

**Why:** Sail manages Docker containers. Direct commands may run outside the container with wrong PHP version or missing extensions.

---

## 🚨 CRITICAL: Database Operations (ABSOLUTE ZERO TOLERANCE)

### NEVER EVER DROP OR MODIFY DATABASES DIRECTLY

**FORBIDDEN COMMANDS:**
- ❌ `./vendor/bin/sail artisan db:wipe` - NEVER
- ❌ `./vendor/bin/sail artisan migrate:fresh` - NEVER
- ❌ `./vendor/bin/sail artisan migrate:reset` - NEVER
- ❌ Direct SQL operations (DROP, TRUNCATE, ALTER, etc.) - NEVER
- ❌ Any command that drops or modifies database structure - NEVER

**ALLOWED:**
- ✅ Laravel migrations ONLY for schema changes
- ✅ Reading database schema for verification

**Why:** Dropping databases destroys data and creates irreversible problems. Always use migrations for schema changes.

### Parallel Test Execution - Database Conflicts

**CRITICAL UNDERSTANDING:**
- Tests often run in parallel causing apparent database issues
- Database errors like "relation already exists" or "duplicate column" are USUALLY parallel test conflicts
- **FIRST RESPONSE**: Retry the test command - most "database issues" resolve on retry
- **SECOND RESPONSE**: Only if repeated failures occur, then investigate actual code issues
- **NEVER**: Drop or reset databases as a solution

**CORRECT RESPONSE TO DATABASE ERRORS IN TESTS:**
1. ✅ **Retry the test** - Run `./vendor/bin/sail test --filter=TestName` again
2. ✅ **Check for parallel conflicts** - If error mentions migrations/tables, likely parallel execution issue
3. ✅ **Wait and retry** - Give database a moment, then retry test
4. ✅ **Only if persistent** - Investigate actual test code or migration issues
5. ❌ **NEVER drop/reset database** - This is NEVER the solution

**Why:** Parallel tests create timing conflicts that look like database errors but aren't. Retrying almost always resolves these.

---

## Authentication & API Testing

Use the `auth:token` command to generate authentication tokens for testing endpoints via CLI:

```bash
# Generate token for a user (uses first team)
./vendor/bin/sail artisan auth:token user@example.com

# Generate token for specific team
./vendor/bin/sail artisan auth:token user@example.com --team=team-uuid-here

# Generate token with custom name
./vendor/bin/sail artisan auth:token user@example.com --name=testing-token
```

### Usage in CLI requests:

```bash
# Test API endpoints with generated token
curl -H "Authorization: Bearer your-token-here" \
     -H "Accept: application/json" \
     http://localhost/api/user

# Test with data
curl -X POST \
     -H "Authorization: Bearer your-token-here" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -d '{"key":"value"}' \
     http://localhost/api/endpoint
```

### Authentication System Overview:
- Uses Laravel Sanctum for API authentication
- Multi-tenant with team-based access control
- Token names contain team UUID for context resolution
- Users must have roles assigned to generate tokens
- Supports team switching via token regeneration

---

## Code Quality Standards

### Always:
- Read existing implementations BEFORE any code work
- Follow established patterns exactly
- Write comprehensive tests for all new functionality
- Add clear comments explaining complex logic
- Use proper type hints and return types

### Never:
- Create custom patterns when established ones exist
- Copy-paste code without understanding it
- Skip tests for "simple" changes
- Leave TODO comments without implementing
- Add backwards compatibility layers
- Use deprecated features or syntax

**Why:** Code quality prevents bugs and makes maintenance easier. Every hour spent on quality saves days of debugging later.

---

## PHPUnit Testing Standards

**CRITICAL: Never use deprecated PHPUnit features**

- ❌ **FORBIDDEN**: `/** @test */` doc-comment annotations (deprecated in PHPUnit 12)
- ✅ **REQUIRED**: `#[Test]` PHP attributes for test methods
- ✅ **REQUIRED**: Add `use PHPUnit\Framework\Attributes\Test;` import

### Example - CORRECT test method:
```php
use PHPUnit\Framework\Attributes\Test;

#[Test]
public function user_can_create_team(): void
{
    // Test implementation
}
```

### Example - WRONG (deprecated):
```php
/** @test */
public function user_can_create_team(): void
{
    // Test implementation - will cause deprecation warnings
}
```

**Why:** PHPUnit 12 deprecated doc-comment annotations. Using deprecated features causes warnings and will break in future PHPUnit versions.

---

## Tool Usage Guidelines

**File Operations:**
- Read files: Use Read tool (NOT cat/head/tail)
- Edit files: Use Edit tool (NOT sed/awk)
- Write new files: Use Write tool (NOT echo >/cat <<EOF)
- Search files: Use Glob tool (NOT find or ls)
- Search content: Use Grep tool (NOT grep or rg commands)

**Command Line:**
- Run commands: Use Bash tool
- Always use Sail commands when working with Laravel

**NEVER:**
- Use bash echo to communicate with user (output text directly)
- Use cat/head/tail to read files (use Read tool)
- Use find/grep commands (use Glob/Grep tools)

**Why:** Specialized tools have better error handling, permissions, and formatting than raw bash commands.

---

**These implementation standards apply to all code-writing agents. Follow them exactly.**
