# Task State Machine Guide

Complete analysis of TaskProcess and TaskRun state transitions, triggers, and flows.

## State Constants

Both TaskProcess and TaskRun implement `WorkflowStatesContract`:

| State | Value | TaskProcess | TaskRun | Description |
|-------|-------|-------------|---------|-------------|
| `PENDING` | `'Pending'` | Yes | Yes | Initial state, waiting to start |
| `RUNNING` | `'Running'` | Yes | Yes | Currently executing |
| `COMPLETED` | `'Completed'` | Yes | Yes | Finished successfully |
| `FAILED` | `'Failed'` | Yes | Yes | Permanent error |
| `STOPPED` | `'Stopped'` | Yes | Yes | User-initiated stop |
| `TIMEOUT` | `'Timeout'` | Yes | No | Exceeded timeout (retryable) |
| `INCOMPLETE` | `'Incomplete'` | Yes | No | Transient error (retryable) |
| `SKIPPED` | `'Skipped'` | No | Yes | No processes to run |

## Timestamp Fields

### TaskProcess
```
started_at      → When execution started
completed_at    → When finished successfully
failed_at       → When permanent error occurred
stopped_at      → When user stopped
incomplete_at   → When transient error occurred (can retry)
timeout_at      → When exceeded timeout (can retry)
restart_count   → Number of retry attempts
```

### TaskRun
```
started_at                    → When task run started
completed_at                  → When all processes finished
failed_at                     → When any process failed permanently
stopped_at                    → When user stopped
skipped_at                    → When no processes were created
active_task_processes_count   → Count of PENDING/RUNNING processes
```

## Status Computation

**Critical Design:** Status is ALWAYS computed from timestamps on save, never directly assigned.

### TaskProcess::computeStatus()

```
┌─────────────────────────────────────────────────────────────────────┐
│                    TaskProcess Status Computation                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   stopped_at SET? ──────────────────────────────► STOPPED           │
│        │ NO                                                          │
│        ▼                                                             │
│   failed_at SET? ───────────────────────────────► FAILED            │
│        │ NO                                                          │
│        ▼                                                             │
│   incomplete_at OR timeout_at SET?                                   │
│        │ YES                                                         │
│        ├─► Can retry? (restart_count < max_retries)                 │
│        │      │ YES → INCOMPLETE or TIMEOUT                         │
│        │      │ NO  → FAILED (exhausted retries)                    │
│        │ NO                                                          │
│        ▼                                                             │
│   started_at NOT SET? ──────────────────────────► PENDING           │
│        │ NO (started)                                                │
│        ▼                                                             │
│   completed_at NOT SET? ────────────────────────► RUNNING           │
│        │ NO (completed)                                              │
│        ▼                                                             │
│   ────────────────────────────────────────────────► COMPLETED       │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### TaskRun Status (via checkProcesses)

```
┌─────────────────────────────────────────────────────────────────────┐
│                     TaskRun State Determination                      │
│                       (checkProcesses method)                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   Scan all child TaskProcesses                                       │
│        │                                                             │
│        ▼                                                             │
│   ANY process RUNNING? ─────────────────────────► RUNNING           │
│        │ NO                                       (clear all ends)   │
│        ▼                                                             │
│   ANY process FAILED? ──────────────────────────► FAILED            │
│        │ NO                                       (set failed_at)    │
│        ▼                                                             │
│   ANY process STOPPED? ─────────────────────────► STOPPED           │
│        │ NO                                       (set stopped_at)   │
│        ▼                                                             │
│   NO processes exist? ──────────────────────────► SKIPPED           │
│        │ NO                                       (set skipped_at)   │
│        ▼                                                             │
│   All processes COMPLETED ──────────────────────► WAIT              │
│        │                                          (clear errors,     │
│        │                                           DON'T set         │
│        │                                           completed_at)     │
│        ▼                                                             │
│   afterAllProcessesComplete() handles completion                     │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

## State Transition Diagram

### TaskProcess States

```
                              ┌──────────────────────────────────────────┐
                              │                                          │
                              │              USER STOP                   │
                              │           (any state)                    │
                              │                │                         │
                              │                ▼                         │
┌─────────┐    start      ┌─────────┐    ┌─────────┐                    │
│ PENDING │──────────────►│ RUNNING │───►│ STOPPED │                    │
└─────────┘               └─────────┘    └─────────┘                    │
     ▲                         │                                        │
     │                         │                                        │
     │                    ┌────┼────┬────────────┐                      │
     │                    │    │    │            │                      │
     │                    ▼    ▼    ▼            ▼                      │
     │              ┌─────────┐ ┌─────────┐ ┌─────────┐                 │
     │              │COMPLETED│ │ TIMEOUT │ │INCOMPLETE│                │
     │              └─────────┘ └────┬────┘ └────┬────┘                 │
     │                               │           │                      │
     │                          can retry?  can retry?                  │
     │                          YES │       YES │                       │
     │                              │           │                       │
     │                    restart() │           │ restart()             │
     └──────────────────────────────┴───────────┘                       │
                                                                        │
                              ┌─────────┐                               │
                    NO ──────►│ FAILED  │◄──────── NO                   │
                              └─────────┘                               │
                                   ▲                                    │
                                   │                                    │
                                   └────── permanent error ─────────────┘
```

### TaskRun States

```
                                    USER STOP
                                   stop() call
                                       │
                                       ▼
┌─────────┐   continue()    ┌─────────┐    ┌─────────┐
│ PENDING │────────────────►│ RUNNING │───►│ STOPPED │
└─────────┘                 └─────────┘    └─────────┘
     │                           │
     │                           │ all processes
     │                           │ complete, no
     │                           │ new created
     │                           ▼
     │                      ┌─────────┐
     │                      │COMPLETED│
     │                      └─────────┘
     │
     │ no processes
     │ created
     ▼
┌─────────┐
│ SKIPPED │
└─────────┘

┌─────────┐
│ FAILED  │◄─────── any process fails permanently
└─────────┘
```

## Method Call Flows

### Flow 1: Normal Process Completion

```
TaskProcessJob executes
│
├─► TaskProcessRunnerService::run()
│   │
│   ├─► Sets started_at = now()
│   │   └─► save() → computeStatus() → RUNNING
│   │
│   ├─► Runner executes: $runner->run()
│   │
│   └─► TaskProcessRunnerService::complete()
│       │
│       ├─► Sets completed_at = now()
│       │   Clears: failed_at, incomplete_at, timeout_at
│       │
│       └─► save() triggers saved() callback
│           │
│           ├─► computeStatus() → COMPLETED
│           │
│           ├─► TaskRun->checkProcesses()->save()
│           │   └─► Syncs TaskRun state from processes
│           │
│           └─► TaskRun->updateActiveProcessCount()->save()
│               │
│               └─► If count goes from >0 to 0:
│                   │
│                   └─► TaskRunnerService::afterAllProcessesComplete()
│                       │
│                       ├─► Calls runner->afterAllProcessesCompleted()
│                       │   (may create new processes!)
│                       │
│                       ├─► Refreshes TaskRun
│                       │
│                       └─► If active_task_processes_count still 0:
│                           │
│                           ├─► Sets completed_at = now()
│                           └─► save() triggers onComplete()
│                               └─► WorkflowRunnerService::onNodeComplete()
```

### Flow 2: Process Failure

```
TaskProcessRunnerService::run() catches exception
│
├─► Is error retryable? (RetryableErrorChecker::isRetryable)
│   │
│   ├─► YES: Sets incomplete_at = now()
│   │        └─► save() → INCOMPLETE (if can retry) or FAILED (if exhausted)
│   │
│   └─► NO:  Sets failed_at = now()
│            └─► save() → FAILED
│
└─► saved() callback
    │
    ├─► TaskRun->checkProcesses()
    │   └─► Detects failed process → Sets failed_at on TaskRun
    │
    └─► TaskRun->updateActiveProcessCount()
        └─► Count decrements, but TaskRun is FAILED
            └─► afterAllProcessesComplete() NOT called (failed state)
```

### Flow 3: Timeout with Auto-Retry

```
TimeoutJob triggers
│
└─► TaskProcessRunnerService::handleTimeout()
    │
    ├─► Sets timeout_at = now()
    │   └─► save() → TIMEOUT
    │
    ├─► Calls halt() to stop agent thread
    │
    └─► Can retry? (restart_count < max_retries)
        │
        ├─► YES: TaskProcessRunnerService::restart()
        │        │
        │        ├─► Clears all timestamps
        │        ├─► Increments restart_count
        │        └─► save() → PENDING
        │            └─► Dispatcher picks up again
        │
        └─► NO:  Process stays TIMEOUT
                 └─► checkProcesses() → TaskRun FAILED
```

### Flow 4: User Stops TaskRun

```
API: POST /task-runs/{id}/apply-action {action: "stop"}
│
└─► TaskRunnerService::stop()
    │
    ├─► Acquire lock on TaskRun
    │
    ├─► For each TaskProcess (if not complete/stopped/failed):
    │   │
    │   ├─► Sets stopped_at = now()
    │   └─► save() → STOPPED
    │       └─► saved() callback → checkProcesses()
    │
    ├─► TaskRun->checkProcesses()->computeStatus()->save()
    │   └─► Detects stopped processes → Sets stopped_at on TaskRun
    │
    ├─► Edge case: If all processes already complete but TaskRun RUNNING:
    │   └─► afterAllProcessesComplete() to fix state
    │
    └─► Release lock
```

### Flow 5: Restart TaskRun

```
TaskRunnerService::restart()
│
├─► Acquire lock on TaskRun
│
├─► Delete all TaskProcesses
│   └─► Each delete triggers saved() → checkProcesses()
│
├─► Clear all input/output artifacts
│
├─► Clear all timestamps:
│   started_at, stopped_at, completed_at, failed_at, skipped_at
│
├─► Reset error count to 0
│
├─► save() → PENDING
│
└─► Dispatch PrepareTaskProcessJob
    └─► prepareTaskProcesses()
        └─► Creates new TaskProcesses
            └─► saved() callbacks trigger state sync
```

## Model Callbacks Summary

### TaskProcess::booted()

```
┌────────────────────────────────────────────────────────────────────┐
│                     TaskProcess Callbacks                           │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  SAVING callback:                                                   │
│  └─► computeStatus() - derives status from timestamps              │
│                                                                     │
│  SAVED callback:                                                    │
│  │                                                                  │
│  ├─► If status or task_run_id changed, OR newly created:           │
│  │   └─► TaskRun->checkProcesses()->save()                         │
│  │                                                                  │
│  ├─► If status changed OR newly created:                           │
│  │   └─► TaskRun->updateActiveProcessCount()->save()               │
│  │                                                                  │
│  ├─► If UI-relevant fields changed:                                │
│  │   └─► Dispatch TaskProcessUpdatedEvent                          │
│  │                                                                  │
│  └─► If failed_at or restart_count changed:                        │
│      └─► TaskProcessErrorTrackingService::update...()              │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘
```

### TaskRun::booted()

```
┌────────────────────────────────────────────────────────────────────┐
│                       TaskRun Callbacks                             │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  SAVING callback:                                                   │
│  └─► computeStatus() - derives status from timestamps              │
│                                                                     │
│  SAVED callback:                                                    │
│  │                                                                  │
│  ├─► If active_task_processes_count changed from >0 to 0:          │
│  │   └─► If NOT failed/stopped/skipped:                            │
│  │       └─► TaskRunnerService::afterAllProcessesComplete()        │
│  │                                                                  │
│  ├─► If status changed to COMPLETED:                               │
│  │   └─► TaskRunnerService::onComplete()                           │
│  │       └─► WorkflowRunnerService::onNodeComplete()               │
│  │                                                                  │
│  ├─► If status changed:                                            │
│  │   ├─► WorkflowRun->checkTaskRuns()                              │
│  │   └─► Trigger event listeners                                   │
│  │                                                                  │
│  ├─► If error count changed:                                       │
│  │   └─► WorkflowRun->updateErrorCount()                           │
│  │                                                                  │
│  └─► If UI-relevant fields changed:                                │
│      └─► Dispatch TaskRunUpdatedEvent                              │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘
```

## Critical Design Patterns

### 1. Timestamp-Based State (Source of Truth)

```
CORRECT:                          WRONG:
$process->completed_at = now();   $process->status = 'Completed';
$process->save();                 $process->save();
// Status computed automatically  // Status will be OVERWRITTEN on save!
```

### 2. Active Count Triggers Completion

```
┌─────────────────────────────────────────────────────────────────┐
│  Process completes → active_task_processes_count decrements     │
│                             │                                   │
│                             ▼                                   │
│              Count goes from >0 to 0?                          │
│                    │                │                           │
│                   YES              NO                           │
│                    │                │                           │
│                    ▼                └─► Wait for more           │
│         afterAllProcessesComplete()                             │
│                    │                                            │
│                    ▼                                            │
│         Runner hook (may create processes)                      │
│                    │                                            │
│                    ▼                                            │
│              Count still 0?                                     │
│                    │                │                           │
│                   YES              NO                           │
│                    │                │                           │
│                    ▼                └─► Loop continues          │
│         Set completed_at = now()                                │
└─────────────────────────────────────────────────────────────────┘
```

### 3. Lock Protection

All state-changing methods acquire locks:

| Method | Lock Target |
|--------|-------------|
| `TaskRunnerService::continue()` | TaskRun |
| `TaskRunnerService::stop()` | TaskRun |
| `TaskRunnerService::restart()` | TaskRun |
| `TaskRunnerService::resume()` | TaskRun |
| `TaskRun::checkProcesses()` | TaskRun |
| `TaskProcessRunnerService::run()` | TaskProcess |
| `TaskProcessRunnerService::complete()` | TaskProcess |
| `TaskProcessRunnerService::stop()` | TaskProcess |

### 4. Error Cascade

```
TaskProcess error
      │
      ▼
TaskProcess.error_count++
      │
      ▼
TaskRun.task_process_error_count updated
      │
      ▼
WorkflowRun.error_count updated (if in workflow)
```

## Edge Cases Handled

### Edge Case 1: All Processes Complete but TaskRun Not Marked

**Scenario:** Race condition or data migration leaves TaskRun in RUNNING with all processes COMPLETED.

**Solution:** `stop()` method detects this and calls `afterAllProcessesComplete()`:
```php
if ($taskRun->isStatusRunning() && $taskRun->active_task_processes_count === 0) {
    static::afterAllProcessesComplete($taskRun->fresh());
}
```

### Edge Case 2: Process Creates New Processes on Completion

**Scenario:** Multi-phase runners (like ExtractDataTaskRunner) create new processes in `afterAllProcessesCompleted()`.

**Solution:** `afterAllProcessesComplete()` checks count AFTER calling runner hook:
```php
$taskRun->getRunner()->afterAllProcessesCompleted();
$taskRun->refresh();
if ($taskRun->active_task_processes_count === 0) {
    $taskRun->completed_at = now(); // Only if still no processes
}
```

### Edge Case 3: Retryable vs Permanent Failures

**Scenario:** Some errors should be retried, others should fail immediately.

**Solution:** `RetryableErrorChecker` determines error type:
- Retryable → `incomplete_at` or `timeout_at` → can auto-restart
- Permanent → `failed_at` → no retry

### Edge Case 4: Pending TaskRun with No Processes

**Scenario:** `stop()` called on PENDING TaskRun before processes created.

**Solution:** Special case in `stop()`:
```php
if ($taskRun->isStatusPending() && $taskRun->taskProcesses->isEmpty()) {
    $taskRun->stopped_at = now();
}
```

## State Transition Method Reference

### TaskProcess Methods

| Method | From States | To State | Triggers |
|--------|-------------|----------|----------|
| `prepare()` | - | PENDING | Creates process |
| `run()` | PENDING | RUNNING | Sets `started_at` |
| `complete()` | RUNNING | COMPLETED | Sets `completed_at` |
| `stop()` | Any | STOPPED | Sets `stopped_at` |
| `restart()` | TIMEOUT/INCOMPLETE | PENDING | Clears timestamps |
| `resume()` | STOPPED | PENDING | Clears `stopped_at` |
| `handleTimeout()` | RUNNING | TIMEOUT | Sets `timeout_at` |

### TaskRun Methods

| Method | From States | To State | Triggers |
|--------|-------------|----------|----------|
| `prepareTaskRun()` | - | PENDING | Creates TaskRun |
| `prepareTaskProcesses()` | PENDING | PENDING/SKIPPED | Creates processes |
| `continue()` | PENDING | RUNNING | Sets `started_at` |
| `stop()` | RUNNING | STOPPED | Stops all processes |
| `restart()` | Any | PENDING | Recreates all |
| `resume()` | STOPPED | RUNNING | Clears `stopped_at` |
| `checkProcesses()` | Any | Varies | Syncs from processes |
| `afterAllProcessesComplete()` | RUNNING | COMPLETED | Final completion |
