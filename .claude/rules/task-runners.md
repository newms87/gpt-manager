---
paths:
  - "app/Services/Task/Runners/**"
  - "app/Services/Task/TaskRunnerService.php"
  - "app/Services/Task/TaskProcess*.php"
  - "app/Jobs/**"
  - "spa/src/components/Modules/TaskDefinitions/TaskRunners/**"
  - "tests/**/TaskRunner**"
---

# Task Runners Rules

These rules apply when working with task runners and jobs.

## Required Reading

Before making task runner changes, review:
- `docs/guides/TASK_STATE_MACHINE_GUIDE.md`

## Key Concepts

- **Task state transitions** - Lifecycle of task execution
- **State machine behavior** - Valid state transitions
- **Queue processing** - How tasks are dispatched and executed

## Critical: Queue Restart

After making changes to any job or task runner code:

```bash
./vendor/bin/sail artisan queue:restart
```

This signals Horizon workers to restart and pick up new code. Without this, workers continue running old code.

## Debug Commands

```bash
./vendor/bin/sail artisan debug:task-run {id}
```

Run `--help` for all available options:
- `--messages` - Show agent thread messages
- `--api-logs` - Show API logs for the process
- `--run-process={id}` - Re-run a specific process synchronously

## Architecture

- `TaskRunnerService` orchestrates task execution
- Individual runners handle specific task types
- Jobs dispatch tasks to queue workers
- State machine controls valid transitions
