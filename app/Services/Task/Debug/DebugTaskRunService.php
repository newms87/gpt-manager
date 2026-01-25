<?php

namespace App\Services\Task\Debug;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\Debug\Concerns\DebugOutputHelper;
use App\Services\Task\TaskRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Models\Audit\ApiLog;
use Newms87\Danx\Models\Job\JobDispatch;

class DebugTaskRunService
{
    use DebugOutputHelper;

    /**
     * Resolve TaskRun from ID (can be TaskRun ID, TaskProcess ID, or JobDispatch ID).
     *
     * @return array{taskRun: TaskRun, taskProcess: TaskProcess|null, jobDispatch: JobDispatch|null}|null
     */
    public function resolveTaskRun(string $id): ?array
    {
        // First try to find as TaskRun
        $taskRun = TaskRun::find($id);

        if ($taskRun) {
            return [
                'taskRun'     => $taskRun,
                'taskProcess' => null,
                'jobDispatch' => null,
            ];
        }

        // Try to find as TaskProcess
        $taskProcess = TaskProcess::withTrashed()->find($id);

        if ($taskProcess) {
            return [
                'taskRun'     => $taskProcess->taskRun,
                'taskProcess' => $taskProcess,
                'jobDispatch' => null,
            ];
        }

        // Try to find as JobDispatch
        $jobDispatch = JobDispatch::find($id);

        if ($jobDispatch) {
            // Get TaskProcess from pivot table
            $pivotRecord = DB::table('job_dispatchables')
                ->where('job_dispatch_id', $id)
                ->where('model_type', TaskProcess::class)
                ->first();

            if ($pivotRecord) {
                $taskProcess = TaskProcess::withTrashed()->find($pivotRecord->model_id);

                if ($taskProcess) {
                    return [
                        'taskRun'     => $taskProcess->taskRun,
                        'taskProcess' => $taskProcess,
                        'jobDispatch' => $jobDispatch,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Show generic task run overview.
     */
    public function showOverview(TaskRun $taskRun, Command $command): void
    {
        $this->showTaskRunHeader($taskRun, $command);

        // Show task processes grouped by operation
        $command->info('=== Task Processes ===');
        $processes          = $taskRun->taskProcesses()->get();
        $groupedByOperation = $processes->groupBy('operation');

        foreach ($groupedByOperation as $operation => $operationProcesses) {
            $statusCounts = $operationProcesses->groupBy('status')->map->count();
            $statusStr    = $statusCounts->map(fn($count, $status) => "$status: $count")->join(', ');
            $command->line("  $operation ({$operationProcesses->count()}): $statusStr");
        }
        $command->newLine();

        // Show input artifacts
        $command->info('=== Input Artifacts ===');
        $inputArtifacts = $taskRun->inputArtifacts()->orderBy('position')->get();
        $command->line("Total: {$inputArtifacts->count()} artifacts");
        $command->newLine();

        // Show output artifacts
        $command->info('=== Output Artifacts ===');
        $outputArtifacts = $taskRun->outputArtifacts()->get();
        $command->line("Total: {$outputArtifacts->count()} artifacts");
        $command->newLine();
    }

    /**
     * Show agent thread messages from first process with a thread.
     */
    public function showAgentThreadMessages(TaskRun $taskRun, Command $command): void
    {
        $command->info('=== Agent Thread Messages ===');

        $processWithThread = $taskRun->taskProcesses()
            ->whereNotNull('agent_thread_id')
            ->first();

        if (!$processWithThread?->agentThread) {
            $command->line('No agent threads found');
            $command->newLine();

            return;
        }

        $thread = $processWithThread->agentThread;
        $command->line("Process ID: {$processWithThread->id}, Thread ID: {$thread->id}");
        $command->newLine();

        foreach ($thread->messages()->orderBy('created_at')->get() as $message) {
            $command->line("[$message->role] - {$message->created_at}");
            $content = $message->content;

            // Truncate long content
            $content = $this->truncate($content, 1500);

            $command->line($content);
            $command->newLine();
        }
    }

    /**
     * Show artifact JSON content (first 5 artifacts, first 1000 chars).
     */
    public function showArtifactContent(Collection $inputArtifacts, Collection $outputArtifacts, Command $command): void
    {
        $command->info('=== Input Artifact Content ===');
        foreach ($inputArtifacts->take(5) as $artifact) {
            $command->line("Artifact #{$artifact->id}: {$artifact->name}");
            if ($artifact->json_content) {
                $this->showJsonContent($artifact->json_content, $command, 1000, 2);
            }
            $command->newLine();
        }
        if ($inputArtifacts->count() > 5) {
            $command->line('... and ' . ($inputArtifacts->count() - 5) . ' more');
        }

        $command->info('=== Output Artifact Content ===');
        foreach ($outputArtifacts->take(5) as $artifact) {
            $command->line("Artifact #{$artifact->id}: {$artifact->name}");
            if ($artifact->json_content) {
                $this->showJsonContent($artifact->json_content, $command, 1000, 2);
            }
            $command->newLine();
        }
        if ($outputArtifacts->count() > 5) {
            $command->line('... and ' . ($outputArtifacts->count() - 5) . ' more');
        }
    }

    /**
     * Show raw artifact meta data.
     */
    public function showRawArtifactData(Collection $inputArtifacts, Collection $outputArtifacts, Command $command): void
    {
        $command->info('=== Raw Artifact Data ===');

        foreach ($outputArtifacts as $artifact) {
            $command->line("[RAW] Artifact ID: {$artifact->id}");
            $command->line('[RAW] json_content is null: ' . ($artifact->json_content === null ? 'YES' : 'NO'));
            $command->line('[RAW] json_content is empty array: ' . (is_array($artifact->json_content) && empty($artifact->json_content) ? 'YES' : 'NO'));
            $command->line('[RAW] json_content has data: ' . (is_array($artifact->json_content) && !empty($artifact->json_content) ? 'YES' : 'NO'));
            if ($artifact->meta) {
                $command->line('[RAW] meta: ' . json_encode($artifact->meta, JSON_PRETTY_PRINT));
            }
            $command->newLine();
        }
    }

    /**
     * Show detailed info for a specific task process.
     */
    public function showProcessDetail(TaskRun $taskRun, int $processId, Command $command): int
    {
        $process = $taskRun->taskProcesses()->find($processId);

        if (!$process) {
            $command->error("TaskProcess $processId not found in TaskRun {$taskRun->id}");

            return 1;
        }

        $command->info("=== TaskProcess $processId Details ===");
        $command->line("Status: {$process->status}");
        $command->line("Operation: {$process->operation}");
        $command->line("Activity: {$process->activity}");
        $command->newLine();

        // Show meta data
        if ($process->meta) {
            $command->info('=== Meta Data ===');
            $command->line(json_encode($process->meta, JSON_PRETTY_PRINT));
            $command->newLine();
        }

        // Show input artifacts
        $command->info('=== Input Artifacts ===');
        $inputArtifacts = $process->inputArtifacts;
        $command->line("Total: {$inputArtifacts->count()} artifacts");

        foreach ($inputArtifacts as $artifact) {
            $command->line("  Artifact #{$artifact->id}: {$artifact->name}");
            if ($artifact->meta) {
                $command->line('    Meta:');
                $command->line($this->indentContent(json_encode($artifact->meta, JSON_PRETTY_PRINT), 4));
            }
            if ($artifact->json_content) {
                $command->line('    JSON Content:');
                $command->line($this->indentContent(json_encode($artifact->json_content, JSON_PRETTY_PRINT), 4));
            }
        }
        $command->newLine();

        // Show output artifacts with full JSON content
        $command->info('=== Output Artifacts ===');
        $outputArtifacts = $process->outputArtifacts;
        $command->line("Total: {$outputArtifacts->count()} artifacts");

        foreach ($outputArtifacts as $artifact) {
            $command->line("  Artifact #{$artifact->id}: {$artifact->name}");
            if ($artifact->json_content) {
                $command->line('    JSON Content:');
                $command->line($this->indentContent(json_encode($artifact->json_content, JSON_PRETTY_PRINT), 4));
            }
            if ($artifact->meta) {
                $command->line('    Meta:');
                $command->line($this->indentContent(json_encode($artifact->meta, JSON_PRETTY_PRINT), 4));
            }
        }
        $command->newLine();

        // Show agent thread messages (full content, not truncated)
        if ($process->agentThread) {
            $command->info('=== Agent Thread Messages ===');
            $thread = $process->agentThread;
            $command->line("Thread ID: {$thread->id}");
            $command->newLine();

            foreach ($thread->messages()->orderBy('created_at')->get() as $message) {
                $command->line("[$message->role] - {$message->created_at}");
                $command->line($message->content);
                $command->newLine();
            }
        }

        return 0;
    }

    /**
     * Create a new task run with the same inputs as the source.
     */
    public function createNewTaskRun(TaskRun $sourceTaskRun, Command $command): int
    {
        $taskDef        = $sourceTaskRun->taskDefinition;
        $inputArtifacts = $sourceTaskRun->inputArtifacts()->get();

        $command->info("Creating new TaskRun for: {$taskDef->name}");
        $command->line("Input artifacts: {$inputArtifacts->count()}");

        // Create new task run
        $newTaskRun = $taskDef->taskRuns()->create([
            'team_id' => $taskDef->team_id,
            'status'  => WorkflowStatesContract::STATUS_PENDING,
        ]);

        // Attach input artifacts
        $newTaskRun->addInputArtifacts($inputArtifacts);

        $command->info("Created TaskRun: {$newTaskRun->id}");

        // Prepare task processes (creates the initial Default Task process)
        $processes = TaskRunnerService::prepareTaskProcesses($newTaskRun);
        $command->line('Prepared ' . count($processes) . ' task process(es)');

        // Start it
        TaskRunnerService::continue($newTaskRun);

        $command->info("Started! Use: php artisan debug:task-run {$newTaskRun->id}");

        return 0;
    }

    /**
     * Show task processes filtered by status.
     */
    public function showProcessesByStatus(TaskRun $taskRun, string $status, Command $command): void
    {
        $this->showTaskRunHeader($taskRun, $command);

        $command->info("=== Processes with Status: $status ===");

        $processes = $taskRun->taskProcesses()
            ->where('status', $status)
            ->get();

        if ($processes->isEmpty()) {
            $command->line("No processes found with status: $status");

            return;
        }

        $command->line("Found: {$processes->count()} processes");
        $command->newLine();

        foreach ($processes as $process) {
            $command->line("Process ID: {$process->id}");
            $command->line("  Operation: {$process->operation}");
            $command->line('  Activity: ' . ($process->activity ?? '(none)'));
            if ($process->failed_at) {
                $command->line("  Failed at: {$process->failed_at}");
            }
            if ($process->meta) {
                $metaJson = json_encode($process->meta);
                $command->line('  Meta: ' . $this->truncate($metaJson, 200));
            }
            $command->newLine();
        }
    }

    /**
     * Reset and re-dispatch an existing task run.
     */
    public function resetAndRerunTaskRun(TaskRun $taskRun, Command $command): int
    {
        $command->info("Resetting TaskRun {$taskRun->id}");

        // Delete all task processes
        $processCount = $taskRun->taskProcesses()->count();
        $taskRun->taskProcesses()->delete();
        $command->line("Deleted $processCount task processes");

        // Delete output artifacts (including children)
        foreach ($taskRun->outputArtifacts as $artifact) {
            // Delete children first
            $artifact->children()->delete();
            $artifact->delete();
        }
        $taskRun->outputArtifacts()->detach();
        $taskRun->updateRelationCounter('outputArtifacts');
        $command->line('Deleted output artifacts');

        // Reset status
        $taskRun->status       = WorkflowStatesContract::STATUS_PENDING;
        $taskRun->meta         = [];
        $taskRun->started_at   = null;
        $taskRun->completed_at = null;
        $taskRun->save();

        // Prepare task processes (creates the initial Default Task process)
        $processes = TaskRunnerService::prepareTaskProcesses($taskRun);
        $command->line('Prepared ' . count($processes) . ' task process(es)');

        // Start it
        TaskRunnerService::continue($taskRun);

        $command->info("Re-started TaskRun {$taskRun->id}");

        return 0;
    }

    /**
     * Show timing information for task processes.
     */
    public function showTiming(TaskRun $taskRun, Command $command): void
    {
        $this->showTaskRunHeader($taskRun, $command);

        $command->info('=== Process Timing ===');

        $processes = $taskRun->taskProcesses()->get();

        if ($processes->isEmpty()) {
            $command->line('No processes found');

            return;
        }

        // Group by operation
        $groupedByOperation = $processes->groupBy('operation');

        foreach ($groupedByOperation as $operation => $operationProcesses) {
            $count = $operationProcesses->count();

            // Calculate durations for completed processes
            $durations = [];
            foreach ($operationProcesses as $process) {
                if ($process->started_at && $process->completed_at) {
                    $durations[] = $process->started_at->diffInSeconds($process->completed_at);
                } elseif ($process->started_at && $process->failed_at) {
                    $durations[] = $process->started_at->diffInSeconds($process->failed_at);
                }
            }

            if (empty($durations)) {
                $command->line("  $operation ($count processes): no timing data");

                continue;
            }

            $total = array_sum($durations);
            $avg   = $total / count($durations);

            $command->line(sprintf(
                '  %s (%d processes): avg %s, total %s',
                $operation,
                $count,
                $this->formatDuration($avg),
                $this->formatDuration($total)
            ));
        }

        $command->newLine();

        // Show total TaskRun duration
        if ($taskRun->started_at) {
            $endTime       = $taskRun->completed_at ?? now();
            $totalDuration = $taskRun->started_at->diffInSeconds($endTime);
            $command->line('Total TaskRun duration: ' . $this->formatDuration($totalDuration));
        }
    }

    /**
     * Format duration in seconds to human-readable string.
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.1fs', $seconds);
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $secs    = $seconds % 60;

            return sprintf('%dm %ds', $minutes, $secs);
        }

        $hours   = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%dh %dm', $hours, $minutes);
    }

    /**
     * Show all job dispatches for a task process in a table format.
     */
    public function showJobDispatches(TaskProcess $taskProcess, Command $command): void
    {
        $command->info("=== Job Dispatches for TaskProcess {$taskProcess->id} ===");
        $command->newLine();

        $jobDispatches = $taskProcess->jobDispatches()->get();

        if ($jobDispatches->isEmpty()) {
            $command->line('No job dispatches found for this task process.');

            return;
        }

        $rows = $jobDispatches->map(fn(JobDispatch $dispatch) => [
            $dispatch->id,
            $dispatch->status,
            $dispatch->job_tag ?? '(none)',
            $dispatch->count,
            $dispatch->created_at?->format('Y-m-d H:i:s') ?? '(none)',
        ])->toArray();

        $command->table(
            ['ID', 'Status', 'Job Tag', 'Count', 'Created At'],
            $rows
        );

        $command->newLine();
        $command->line("Total: {$jobDispatches->count()} job dispatch(es)");
        $command->line('Use --api-logs --job-dispatch=<ID> to view API logs for a specific job dispatch.');
    }

    /**
     * Show API logs for a task process's job dispatch.
     */
    public function showApiLogs(TaskProcess $taskProcess, ?int $jobDispatchId, Command $command): void
    {
        // Get the specific job dispatch or the most recent one
        if ($jobDispatchId) {
            $jobDispatch = $taskProcess->jobDispatches()->where('job_dispatch.id', $jobDispatchId)->first();

            if (!$jobDispatch) {
                $command->error("JobDispatch #{$jobDispatchId} not found for TaskProcess {$taskProcess->id}");

                return;
            }
        } else {
            $jobDispatch = $taskProcess->jobDispatches()->first();

            if (!$jobDispatch) {
                $command->line('No job dispatches found for this task process.');

                return;
            }
        }

        $command->info("=== API Logs for JobDispatch #{$jobDispatch->id} ===");
        $command->line("Status: {$jobDispatch->status}");
        $command->line('Job Tag: ' . ($jobDispatch->job_tag ?? '(none)'));
        $command->newLine();

        $apiLogs           = collect();
        $sourceDescription = '';

        // Strategy 1: Via running audit request
        if ($jobDispatch->runningAuditRequest) {
            $apiLogs = $jobDispatch->runningAuditRequest->apiLogs()->orderBy('id')->get();
            if ($apiLogs->isNotEmpty()) {
                $sourceDescription = "via running audit request #{$jobDispatch->running_audit_request_id}";
            }
        }

        // Strategy 2: Via dispatch audit request
        if ($apiLogs->isEmpty() && $jobDispatch->dispatchAuditRequest) {
            $apiLogs = $jobDispatch->dispatchAuditRequest->apiLogs()->orderBy('id')->get();
            if ($apiLogs->isNotEmpty()) {
                $sourceDescription = "via dispatch audit request #{$jobDispatch->dispatch_audit_request_id}";
            }
        }

        // Strategy 3: Find by time range during job execution
        if ($apiLogs->isEmpty() && $jobDispatch->ran_at) {
            $startTime = $jobDispatch->ran_at->subSeconds(5);
            $endTime   = $jobDispatch->completed_at ?? now();

            $apiLogs = ApiLog::query()
                ->where('created_at', '>=', $startTime)
                ->where('created_at', '<=', $endTime)
                ->orderBy('id')
                ->get();

            if ($apiLogs->isNotEmpty()) {
                $sourceDescription = "via time range search ({$startTime} to {$endTime})";
            }
        }

        if ($apiLogs->isEmpty()) {
            $command->line('No API logs found for this job dispatch.');
            $command->line('Checked: runningAuditRequest, dispatchAuditRequest, and time-based search');

            return;
        }

        $command->line("Found {$apiLogs->count()} API log(s) {$sourceDescription}:");
        $command->newLine();

        foreach ($apiLogs as $apiLog) {
            $command->line("--- ApiLog #{$apiLog->id} ---");
            $command->line((string)$apiLog);
            $command->newLine();
        }
    }

    /**
     * List recent job dispatches in a table format for finding IDs.
     */
    public function listRecentJobDispatches(Command $command): void
    {
        $command->info('=== Recent Job Dispatches ===');
        $command->newLine();

        $jobDispatches = JobDispatch::orderByDesc('id')->take(20)->get();

        if ($jobDispatches->isEmpty()) {
            $command->line('No job dispatches found.');

            return;
        }

        $rows = $jobDispatches->map(function (JobDispatch $dispatch) {
            // Look up associated TaskProcess from pivot table
            $pivotRecord = DB::table('job_dispatchables')
                ->where('job_dispatch_id', $dispatch->id)
                ->where('model_type', TaskProcess::class)
                ->first();

            $taskProcessId = $pivotRecord?->model_id ?? '-';

            return [
                $dispatch->id,
                $dispatch->status,
                $dispatch->ref                                ?? '-',
                $dispatch->created_at?->format('Y-m-d H:i:s') ?? '-',
                $taskProcessId,
            ];
        })->toArray();

        $command->table(
            ['ID', 'Status', 'Ref', 'Created At', 'TaskProcess ID'],
            $rows
        );

        $command->newLine();
        $command->line('Use: ./vendor/bin/sail artisan debug:task-run <ID> to debug a specific dispatch.');
    }
}
