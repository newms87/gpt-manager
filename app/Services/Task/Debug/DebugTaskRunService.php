<?php

namespace App\Services\Task\Debug;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\TaskRunnerService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class DebugTaskRunService
{
    /**
     * Resolve TaskRun from ID (can be TaskRun ID or TaskProcess ID).
     *
     * @return array{taskRun: TaskRun, taskProcess: TaskProcess|null}|null
     */
    public function resolveTaskRun(string $id): ?array
    {
        // First try to find as TaskRun
        $taskRun = TaskRun::find($id);

        if ($taskRun) {
            return [
                'taskRun'     => $taskRun,
                'taskProcess' => null,
            ];
        }

        // Try to find as TaskProcess
        $taskProcess = TaskProcess::find($id);

        if (!$taskProcess) {
            return null;
        }

        return [
            'taskRun'     => $taskProcess->taskRun,
            'taskProcess' => $taskProcess,
        ];
    }

    /**
     * Show generic task run overview.
     */
    public function showOverview(TaskRun $taskRun, Command $command): void
    {
        $command->info("=== TaskRun {$taskRun->id} ===");
        $command->line("Status: {$taskRun->status}");
        $command->line("TaskDefinition: {$taskRun->taskDefinition->name}");
        $command->line("Runner: {$taskRun->taskDefinition->task_runner_name}");
        $command->newLine();

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
            if (strlen($content) > 1500) {
                $content = substr($content, 0, 1500) . '... [truncated]';
            }

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
                $jsonContent = json_encode($artifact->json_content, JSON_PRETTY_PRINT);
                $truncated   = strlen($jsonContent) > 1000 ? substr($jsonContent, 0, 1000) . '... [truncated]' : $jsonContent;
                $command->line('  ' . str_replace("\n", "\n  ", $truncated));
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
                $jsonContent = json_encode($artifact->json_content, JSON_PRETTY_PRINT);
                $truncated   = strlen($jsonContent) > 1000 ? substr($jsonContent, 0, 1000) . '... [truncated]' : $jsonContent;
                $command->line('  ' . str_replace("\n", "\n  ", $truncated));
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
            if ($artifact->json_content) {
                $command->line('    JSON Content:');
                $command->line('    ' . str_replace("\n", "\n    ", json_encode($artifact->json_content, JSON_PRETTY_PRINT)));
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
                $command->line('    ' . str_replace("\n", "\n    ", json_encode($artifact->json_content, JSON_PRETTY_PRINT)));
            }
            if ($artifact->meta) {
                $command->line('    Meta:');
                $command->line('    ' . str_replace("\n", "\n    ", json_encode($artifact->meta, JSON_PRETTY_PRINT)));
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
}
