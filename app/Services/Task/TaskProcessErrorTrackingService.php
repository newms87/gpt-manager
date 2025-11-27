<?php

namespace App\Services\Task;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Traits\HasDebugLogging;
use Newms87\Danx\Models\Job\JobDispatch;

class TaskProcessErrorTrackingService
{
    use HasDebugLogging;

    /**
     * Update error counts for a JobDispatch
     * Finds associated TaskProcess and updates its error count
     */
    public function updateErrorCountsForJobDispatch(JobDispatch $jobDispatch): void
    {
        // Find the associated task process using the polymorphic relationship
        $taskProcesses = TaskProcess::whereHas('jobDispatches', function ($query) use ($jobDispatch) {
            $query->where('job_dispatch.id', $jobDispatch->id);
        })->get();

        if ($taskProcesses->isEmpty()) {
            static::logDebug("No TaskProcess found for JobDispatch {$jobDispatch->id}");

            return;
        }

        foreach ($taskProcesses as $taskProcess) {
            // Count errors from this job dispatch's audit request
            $errorCount = 0;
            if ($auditRequest = $jobDispatch->runningAuditRequest) {
                $errorCount = $auditRequest->errorLogEntries()->count();
            }

            static::logDebug("JobDispatch {$jobDispatch->id} has {$errorCount} errors");

            // Update the task process error count if needed
            $this->updateTaskProcessErrorCount($taskProcess);
        }
    }

    /**
     * Update error count for a task process by counting all errors from its job dispatches
     */
    public function updateTaskProcessErrorCount(TaskProcess $taskProcess): void
    {
        $totalErrors = 0;

        // Only count errors if the process has exhausted retries or is permanently failed
        // This prevents transient retry errors from being surfaced to users
        if (!$taskProcess->canBeRetried() || $taskProcess->isFailed()) {
            // Count all errors from all job dispatches for this task process
            foreach ($taskProcess->jobDispatches as $jobDispatch) {
                if ($auditRequest = $jobDispatch->runningAuditRequest) {
                    $totalErrors += $auditRequest->errorLogEntries()->count();
                }
            }
        }
        // If process can still be retried, totalErrors stays 0 (errors suppressed)

        // Update if changed
        if ($taskProcess->error_count !== $totalErrors) {
            $suppressedMsg = ($totalErrors === 0 && $taskProcess->canBeRetried()) ? ' (suppressed - can retry)' : '';
            static::logDebug("Updating TaskProcess {$taskProcess->id} error_count: {$taskProcess->error_count} → {$totalErrors}" . $suppressedMsg);
            $taskProcess->update(['error_count' => $totalErrors]);

            // Update the parent task run's aggregate error count
            if ($taskProcess->taskRun) {
                $this->updateTaskRunErrorCount($taskProcess->taskRun);
            }
        }
    }

    /**
     * Update task run error count by summing all task process error counts
     */
    public function updateTaskRunErrorCount(TaskRun $taskRun): void
    {
        $totalErrors = $taskRun->taskProcesses()->sum('error_count');

        if ($taskRun->task_process_error_count !== $totalErrors) {
            static::logDebug("Updating TaskRun {$taskRun->id} task_process_error_count from {$taskRun->task_process_error_count} to {$totalErrors}");
            $taskRun->update(['task_process_error_count' => $totalErrors]);
        }
    }
}
