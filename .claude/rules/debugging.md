# Debugging Guidelines

## Prefer Debug Commands Over Tinker

When debugging task processes, workflows, task runs, or audit logs, **ALWAYS use the dedicated debug commands** instead of tinker.

### Debug Commands

| Debugging Task | Command |
|----------------|---------|
| Extract Data Tasks | `./vendor/bin/sail artisan debug:extract-data-task-run {id}` |
| File Organization Tasks | `./vendor/bin/sail artisan debug:file-organization-task-run {id}` |
| General Task Runs | `./vendor/bin/sail artisan debug:task-run {id}` |
| Audit Requests / API Logs | `./vendor/bin/sail artisan audit:debug` |

### Useful debug command options
- `--messages` - Show agent thread messages
- `--api-logs` - Show API logs for the process
- `--run-process={id}` - Re-run a specific process synchronously
- `--show-schema={id}` - Show the extraction schema sent to LLM

### ALWAYS run --help first!
Before using any debug command, run `--help` to see ALL available options:
```bash
./vendor/bin/sail artisan debug:task-run {id} --help
./vendor/bin/sail artisan audit:debug --help
```

### Why debug commands over tinker
- Debug commands are pre-approved and run autonomously
- Optimized output formatting for investigation
- Can re-run processes, show schemas, view messages
- Tinker requires manual approval and blocks autonomous operation

### Only use tinker when:
- No debug command exists for your specific use case
- You need a one-off query not covered by debug commands
