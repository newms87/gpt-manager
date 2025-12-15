<?php

namespace App\Services\Task\Debug;

use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use Illuminate\Console\Command;

class ExtractDataDebugService
{
    /**
     * Show task run overview.
     */
    public function showOverview(TaskRun $taskRun, Command $command): void
    {
        $command->info("=== TaskRun {$taskRun->id} ===");
        $command->line("Status: {$taskRun->status}");
        $command->line("TaskDefinition: {$taskRun->taskDefinition->name}");
        $command->line("Runner: {$taskRun->taskDefinition->task_runner_name}");
        $command->newLine();

        // Show task definition prompt
        $command->info('=== Task Definition Prompt ===');
        $command->line($taskRun->taskDefinition->prompt ?: '(none)');
        $command->newLine();

        // Show output artifacts
        $command->info('=== Output Artifacts ===');
        $outputArtifacts = $taskRun->outputArtifacts()->get();
        $command->line("Total: {$outputArtifacts->count()}");

        foreach ($outputArtifacts as $artifact) {
            $command->line("  - {$artifact->name} (ID: {$artifact->id})");
        }
        $command->newLine();

        // Show task processes grouped by operation
        $command->info('=== Task Processes ===');
        $processes        = $taskRun->taskProcesses()->get();
        $groupedProcesses = $processes->groupBy('operation');

        foreach ($groupedProcesses as $operation => $operationProcesses) {
            $count = $operationProcesses->count();
            $command->line("  $operation: $count");

            // Show details for classify processes
            if ($operation === ExtractDataTaskRunner::OPERATION_CLASSIFY) {
                $completed = $operationProcesses->where('status', 'Completed')->count();
                $pending   = $operationProcesses->where('status', 'Pending')->count();
                $running   = $operationProcesses->where('status', 'Running')->count();
                $failed    = $operationProcesses->where('status', 'Failed')->count();

                $command->line("    Completed: $completed, Pending: $pending, Running: $running, Failed: $failed");

                // Show child artifacts with classifications
                foreach ($operationProcesses->take(5) as $process) {
                    $childArtifactId = $process->meta['child_artifact_id'] ?? null;
                    $inputArtifact   = $process->inputArtifacts->first();
                    $pageNumber      = $inputArtifact?->position ?? '?';
                    $status          = $process->status;

                    $command->line("      Page $pageNumber (Artifact $childArtifactId): $status");

                    // Show classification from child artifact meta if available
                    $classification = $inputArtifact?->meta['classification'] ?? null;
                    if ($classification) {
                        $trueFields = array_keys(array_filter($classification, fn($v) => $v === true));
                        $trueCount  = count($trueFields);
                        $command->line("        Classifications: $trueCount fields true");
                    }
                }

                if ($count > 5) {
                    $command->line('      ... and ' . ($count - 5) . ' more');
                }
            }
        }
        $command->newLine();
    }

    /**
     * Show artifact structure (parent/child hierarchy).
     */
    public function showArtifactStructure(TaskRun $taskRun, Command $command): void
    {
        $command->info('=== Artifact Structure ===');

        $outputArtifacts = $taskRun->outputArtifacts()->get();

        foreach ($outputArtifacts as $parentArtifact) {
            $command->line("📁 Parent: {$parentArtifact->name} (ID: {$parentArtifact->id})");

            $children = $parentArtifact->children()->orderBy('position')->get();
            $command->line("   Children: {$children->count()}");

            foreach ($children->take(10) as $child) {
                $storedFiles = $child->storedFiles;
                $filesCount  = $storedFiles->count();
                $filesIds    = $storedFiles->pluck('id')->implode(', ');

                $command->line("     - {$child->name} (ID: {$child->id}, Position: {$child->position})");
                $command->line("       Stored files: $filesCount ($filesIds)");

                // Show classification if present
                $classification = $child->meta['classification'] ?? null;
                if ($classification) {
                    $trueFields = array_keys(array_filter($classification, fn($v) => $v === true));
                    $command->line('       Classifications: ' . implode(', ', array_slice($trueFields, 0, 5)));
                }
            }

            if ($children->count() > 10) {
                $command->line('     ... and ' . ($children->count() - 10) . ' more');
            }

            $command->newLine();
        }
    }

    /**
     * Show all classify processes with their input artifacts and meta.
     */
    public function showClassifyProcesses(TaskRun $taskRun, Command $command): void
    {
        $command->info('=== Classify Processes ===');

        $classifyProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->orderBy('id')
            ->get();

        $command->line("Total: {$classifyProcesses->count()}");
        $command->newLine();

        foreach ($classifyProcesses as $process) {
            $childArtifactId = $process->meta['child_artifact_id'] ?? null;
            $inputArtifact   = $process->inputArtifacts->first();
            $pageNumber      = $inputArtifact?->position ?? '?';
            $storedFile      = $inputArtifact?->storedFiles->first();
            $status          = $process->status;

            $command->line("Process ID: {$process->id}");
            $command->line("  Page: $pageNumber");
            $command->line("  Child Artifact ID: $childArtifactId");
            $command->line('  Stored File: ' . ($storedFile?->id ?? 'none'));
            $command->line("  Status: $status");

            // Show input artifacts count
            $command->line("  Input Artifacts: {$process->inputArtifacts->count()}");

            // Show classification from child artifact meta
            $classification = $inputArtifact?->meta['classification'] ?? null;
            if ($classification) {
                $trueFields  = array_keys(array_filter($classification, fn($v) => $v === true));
                $falseFields = array_keys(array_filter($classification, fn($v) => $v === false));

                $command->line('  Classification (from artifact meta):');
                $command->line('    True (' . count($trueFields) . '): ' . implode(', ', array_slice($trueFields, 0, 5)) . (count($trueFields) > 5 ? '...' : ''));
                $command->line('    False (' . count($falseFields) . '): ' . implode(', ', array_slice($falseFields, 0, 5)) . (count($falseFields) > 5 ? '...' : ''));
            } else {
                $command->line('  Classification: (not yet available)');
            }

            $command->newLine();
        }
    }

    /**
     * Show detailed view of a specific process.
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
}
