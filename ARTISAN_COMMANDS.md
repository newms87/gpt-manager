# Artisan Commands Reference

This document describes all custom artisan commands available in the GPT Manager application.

## Quick Reference

| Command | Description |
|---------|-------------|
| `auth:token` | Generate API authentication token for CLI testing |
| `app:investigate-task-process` | Investigate task process for debugging |
| `billing:process-daily-usage` | Process daily usage billing for all teams |
| `debug:task-run` | Debug a TaskRun to understand agent communication |
| `google:oauth:authorize` | Get Google OAuth authorization URL |
| `prompt:list` | List all available prompt tests |
| `prompt:resources` | List available agents and MCP servers for testing |
| `prompt:test` | Run prompt engineering tests with real API calls |
| `task:timeout` | Check for timed out task processes |
| `test:classification-deduplication` | Test property-specific classification deduplication |
| `test:file-organization` | Test FileOrganizationTaskRunner with a WorkflowInput |
| `test:google-docs-markdown` | Test Google Docs markdown formatting conversion |
| `test:google-docs-template` | Test Google Docs template task runner |
| `test:usage-subscription` | Test the polymorphic usage subscription system |
| `usage:generate-fake` | Generate fake usage data for testing |
| `workflow:build` | Build workflows through interactive AI conversation |
| `workspace:clean` | Delete workspace data based on flags |
| `workspace:remove-team` | Thoroughly remove a team and all related data |

---

## Authentication & Authorization

### `auth:token`

Generate an API authentication token for CLI testing.

```bash
./vendor/bin/sail artisan auth:token {email} [--team=] [--name=]
```

**Arguments:**
- `email` - User email address (required)

**Options:**
- `--team=` - Team UUID (optional, defaults to first team)
- `--name=` - Token name (optional, defaults to team UUID)

**Example:**
```bash
./vendor/bin/sail artisan auth:token user@example.com --team=abc123
```

---

## Debugging & Investigation

### `app:investigate-task-process`

Investigate a task process for debugging. Useful for understanding what happened during file organization and other task operations.

```bash
./vendor/bin/sail artisan app:investigate-task-process {id} [options]
```

**Arguments:**
- `id` - Task process ID to investigate

**Options:**
- `--artifact=` - Show details of a specific artifact ID
- `--windows` - Show all window processes for the task run
- `--search=` - Search for a term in artifact JSON content
- `--files` - Show stored files for output artifacts
- `--page=` - Show assignment history for a specific page number
- `--simulate` - Simulate the merge process to debug group assignments
- `--boundary` - Find boundary conflicts between groups

**Examples:**
```bash
# Basic investigation
./vendor/bin/sail artisan app:investigate-task-process 1758

# View a specific artifact
./vendor/bin/sail artisan app:investigate-task-process 1758 --artifact=26283

# Search for a term across all artifacts
./vendor/bin/sail artisan app:investigate-task-process 1758 --search="Mountain View"

# Show all comparison window processes
./vendor/bin/sail artisan app:investigate-task-process 1758 --windows

# Show page assignment history
./vendor/bin/sail artisan app:investigate-task-process 1758 --page=127

# Simulate merge to debug group assignments
./vendor/bin/sail artisan app:investigate-task-process 1758 --simulate

# Find boundary conflicts between groups
./vendor/bin/sail artisan app:investigate-task-process 1758 --boundary
```

### `debug:task-run`

Debug a TaskRun to understand agent communication and results.

```bash
./vendor/bin/sail artisan debug:task-run {task-run} [--messages]
```

**Arguments:**
- `task-run` - TaskRun ID to debug

**Options:**
- `--messages` - Show agent thread messages

**Example:**
```bash
./vendor/bin/sail artisan debug:task-run 81 --messages
```

---

## Prompt Testing

### `prompt:test`

Run prompt engineering tests with real API calls.

```bash
./vendor/bin/sail artisan prompt:test [test] [options]
```

**Arguments:**
- `test` - Test name/path to run (optional, runs all tests if not specified)

**Options:**
- `--agent=` - Agent ID to use for testing
- `--mcp-server=` - MCP Server ID to use for testing
- `--detailed` - Show detailed output
- `--save-results` - Save test results to database
- `--continue-on-failure` - Continue running tests even if one fails

**Example:**
```bash
./vendor/bin/sail artisan prompt:test my-test --agent=5 --detailed
```

### `prompt:list`

List all available prompt tests.

```bash
./vendor/bin/sail artisan prompt:list
```

### `prompt:resources`

List available agents and MCP servers for testing.

```bash
./vendor/bin/sail artisan prompt:resources
```

---

## Task & Workflow Testing

### `test:file-organization`

Test FileOrganizationTaskRunner with a WorkflowInput.

```bash
./vendor/bin/sail artisan test:file-organization {workflow-input}
```

**Arguments:**
- `workflow-input` - Workflow input ID

### `test:classification-deduplication`

Test property-specific classification deduplication with real AI agent.

```bash
./vendor/bin/sail artisan test:classification-deduplication
```

### `test:google-docs-template`

Test Google Docs template task runner - extracts variables and populates with team data.

```bash
./vendor/bin/sail artisan test:google-docs-template
```

### `test:google-docs-markdown`

Test Google Docs markdown formatting conversion - verifies bold, italic, headings work correctly.

```bash
./vendor/bin/sail artisan test:google-docs-markdown
```

### `test:usage-subscription`

Test the polymorphic usage subscription system.

```bash
./vendor/bin/sail artisan test:usage-subscription
```

---

## Workflow Building

### `workflow:build`

Build and modify workflows through interactive AI-powered conversation.

```bash
./vendor/bin/sail artisan workflow:build
```

This command provides a conversational interface for:
- Creating new workflows from natural language descriptions
- Continuing existing workflow builder chat sessions
- Modifying existing workflows with additional requirements
- Monitoring workflow build progress in real-time

---

## Billing & Usage

### `billing:process-daily-usage`

Process daily usage billing for all teams with usage-based subscriptions.

```bash
./vendor/bin/sail artisan billing:process-daily-usage
```

### `usage:generate-fake`

Generate fake usage data for a specific UI demand.

```bash
./vendor/bin/sail artisan usage:generate-fake
```

---

## Task Management

### `task:timeout`

Checks for any task processes that have timed out, and updates their statuses.

```bash
./vendor/bin/sail artisan task:timeout
```

---

## Workspace Management

### `workspace:clean`

Deletes workspace data based on flags.

```bash
./vendor/bin/sail artisan workspace:clean [--user-data] [--runs] [--inputs] [--auditing]
```

**Options:**
- `--user-data` - Delete UI demands, demand templates, and team objects
- `--runs` - Delete all runs (workflow runs, task runs, etc.)
- `--inputs` - Delete workflow inputs
- `--auditing` - Delete audit logs

**Example:**
```bash
./vendor/bin/sail artisan workspace:clean --runs --auditing
```

### `workspace:remove-team`

Thoroughly remove a team and all related data (excluding users).

```bash
./vendor/bin/sail artisan workspace:remove-team
```

---

## Google Integration

### `google:oauth:authorize`

Get Google OAuth authorization URL for Google Docs API.

```bash
./vendor/bin/sail artisan google:oauth:authorize
```

---

## Notes

- All commands should be run through Laravel Sail: `./vendor/bin/sail artisan <command>`
- Use `./vendor/bin/sail artisan <command> --help` for detailed help on any command
- Test commands (`test:*`) are for development/debugging and make real API calls
