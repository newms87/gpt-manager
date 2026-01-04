<?php

namespace App\Services\Task\Debug;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

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

                $command->line("     - {$child->name} (ID: {$child->id}, Position: {$child->position}, child_artifacts_count: {$child->child_artifacts_count})");
                $command->line("       Stored files: $filesCount ($filesIds)");

                // Show classification if present
                $classification = $child->meta['classification'] ?? null;
                if ($classification) {
                    $trueFields = array_keys(array_filter($classification, fn($v) => $v === true));
                    $command->line('       Classifications: ' . implode(', ', array_slice($trueFields, 0, 5)));
                }

                // Show grandchildren (extraction artifacts)
                $grandchildren = $child->children()->get();
                if ($grandchildren->isNotEmpty()) {
                    $command->line("       Grandchildren: {$grandchildren->count()}");
                    foreach ($grandchildren->take(3) as $grandchild) {
                        $command->line("         - {$grandchild->name} (ID: {$grandchild->id})");
                    }
                    if ($grandchildren->count() > 3) {
                        $command->line('         ... and ' . ($grandchildren->count() - 3) . ' more');
                    }
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

            // Show parent artifact info
            $parentId   = $artifact->parent_artifact_id;
            $parentName = $artifact->parent?->name ?? '(none)';
            $command->line('    parent_artifact_id: ' . ($parentId ?? 'NULL') . " ($parentName)");

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
     * Run a task process directly to debug exceptions.
     *
     * This method resets the process, runs it synchronously, and catches/displays any exceptions.
     */
    public function runProcess(TaskRun $taskRun, int $processId, Command $command): int
    {
        $process = $taskRun->taskProcesses()->find($processId);

        if (!$process) {
            $command->error("TaskProcess $processId not found in TaskRun {$taskRun->id}");

            return 1;
        }

        // Set up authentication context (required for team() helper)
        $team = $taskRun->taskDefinition->team;
        $user = $team->users()->first();

        if (!$user) {
            $command->error("No users found in team '{$team->name}' - cannot set authentication context");

            return 1;
        }

        $originalUser = Auth::user();
        Auth::guard()->setUser($user);
        $user->currentTeam = $team;

        $command->line("Authenticated as user: {$user->email} (Team: {$team->name})");
        $command->newLine();

        $command->info("=== Running TaskProcess $processId ===");
        $command->line("Operation: {$process->operation}");
        $command->line("Current Status: {$process->status}");
        $command->newLine();

        // Reset the process so it can be re-attempted
        $command->info('Resetting process...');
        $this->resetProcess($process);
        $command->line('Process reset to Pending state');
        $command->newLine();

        // Run the process synchronously
        $command->info('Running process...');
        $command->newLine();

        try {
            $runner = app(ExtractDataTaskRunner::class)
                ->setTaskRun($taskRun)
                ->setTaskProcess($process);

            $runner->run();

            $command->info('Process completed successfully!');
            $command->newLine();

            // Refresh and show final status
            $process->refresh();
            $command->line("Final Status: {$process->status}");

            return 0;
        } catch (Throwable $e) {
            $command->error('Exception caught during process execution:');
            $command->newLine();

            $command->line('Exception Class: ' . get_class($e));
            $command->line('Message: ' . $e->getMessage());
            $command->line('File: ' . $e->getFile() . ':' . $e->getLine());
            $command->newLine();

            $command->info('Stack Trace (first 10 frames):');
            $trace      = $e->getTrace();
            $frameCount = min(10, count($trace));

            for ($i = 0; $i < $frameCount; $i++) {
                $frame    = $trace[$i];
                $file     = $frame['file']     ?? '(unknown)';
                $line     = $frame['line']     ?? '?';
                $class    = $frame['class']    ?? '';
                $function = $frame['function'] ?? '';
                $type     = $frame['type']     ?? '';

                $command->line("#$i $file:$line");
                $command->line("   $class$type$function()");
            }

            if (count($trace) > 10) {
                $command->line('... and ' . (count($trace) - 10) . ' more frames');
            }

            return 1;
        } finally {
            // Restore original auth state
            if ($originalUser) {
                Auth::guard()->setUser($originalUser);
            } else {
                Auth::guard()->forgetUser();
            }
        }
    }

    /**
     * Reset a task process so it can be re-attempted.
     */
    protected function resetProcess(TaskProcess $process): void
    {
        $process->started_at    = null;
        $process->completed_at  = null;
        $process->failed_at     = null;
        $process->stopped_at    = null;
        $process->incomplete_at = null;
        $process->timeout_at    = null;
        $process->is_ready      = true;
        $process->save();
    }

    /**
     * Show all TeamObjects created during extraction (from TaskRun meta resolved_objects).
     */
    public function showResolvedObjects(TaskRun $taskRun, Command $command): void
    {
        $command->info('=== Resolved Objects ===');

        $meta            = $taskRun->meta            ?? [];
        $resolvedObjects = $meta['resolved_objects'] ?? [];

        if (empty($resolvedObjects)) {
            $command->warn('No resolved objects found in TaskRun meta');

            return;
        }

        // Group by level and object type
        foreach ($resolvedObjects as $objectType => $levelData) {
            $command->info("Object Type: $objectType");

            foreach ($levelData as $level => $objectIds) {
                $command->line("  Level $level: " . count($objectIds) . ' objects');

                if (empty($objectIds)) {
                    continue;
                }

                // Load the TeamObjects
                $teamObjects = TeamObject::whereIn('id', $objectIds)->get();

                foreach ($teamObjects as $teamObject) {
                    $name      = $teamObject->name                               ?? '(unnamed)';
                    $type      = $teamObject->type                               ?? '(untyped)';
                    $createdAt = $teamObject->created_at?->format('Y-m-d H:i:s') ?? '?';

                    $command->line("    ID: {$teamObject->id}");
                    $command->line("      Name: $name");
                    $command->line("      Type: $type");
                    $command->line("      Created: $createdAt");

                    // Show first 100 chars of meta data if present
                    if ($teamObject->meta) {
                        $metaJson  = json_encode($teamObject->meta);
                        $truncated = strlen($metaJson) > 100 ? substr($metaJson, 0, 100) . '...' : $metaJson;
                        $command->line("      Data: $truncated");
                    }

                    $command->newLine();
                }
            }

            $command->newLine();
        }

        // Summary
        $totalObjects = 0;
        foreach ($resolvedObjects as $objectType => $levelData) {
            foreach ($levelData as $level => $objectIds) {
                $totalObjects += count($objectIds);
            }
        }

        $command->info("Total resolved objects: $totalObjects");
    }

    /**
     * Show the full TaskRun meta data.
     */
    public function showTaskRunMeta(TaskRun $taskRun, Command $command): void
    {
        $command->info('=== TaskRun Meta ===');
        $command->newLine();

        // Show basic task run info
        $command->line("TaskRun ID: {$taskRun->id}");
        $command->line("Status: {$taskRun->status}");
        $command->line("Task Definition ID: {$taskRun->task_definition_id}");
        $command->line('Started At: ' . ($taskRun->started_at?->format('Y-m-d H:i:s') ?? '(not started)'));
        $command->line('Completed At: ' . ($taskRun->completed_at?->format('Y-m-d H:i:s') ?? '(not completed)'));
        $command->newLine();

        // Show full meta as pretty-printed JSON
        $command->info('=== Full Meta Data ===');
        $meta = $taskRun->meta ?? [];

        if (empty($meta)) {
            $command->warn('No meta data stored on TaskRun');

            return;
        }

        $command->line(json_encode($meta, JSON_PRETTY_PRINT));
    }

    /**
     * Show extraction level progress for each level.
     */
    public function showLevelProgress(TaskRun $taskRun, Command $command): void
    {
        $command->info('=== Level Progress ===');
        $command->newLine();

        $meta          = $taskRun->meta          ?? [];
        $levelProgress = $meta['level_progress'] ?? [];
        $currentLevel  = $meta['current_level']  ?? 0;

        $command->line("Current Level: $currentLevel");
        $command->newLine();

        if (empty($levelProgress)) {
            $command->warn('No level progress data found in TaskRun meta');

            return;
        }

        // Sort levels by key
        ksort($levelProgress);

        foreach ($levelProgress as $level => $progress) {
            $command->info("Level $level:");

            $classificationComplete = $progress['classification_complete'] ?? false;
            $identityComplete       = $progress['identity_complete']       ?? false;
            $extractionComplete     = $progress['extraction_complete']     ?? false;

            $classificationStatus = $classificationComplete ? 'COMPLETE' : 'INCOMPLETE';
            $identityStatus       = $identityComplete ? 'COMPLETE' : 'INCOMPLETE';
            $extractionStatus     = $extractionComplete ? 'COMPLETE' : 'INCOMPLETE';

            $command->line("  Classification: $classificationStatus");
            $command->line("  Identity: $identityStatus");
            $command->line("  Extraction: $extractionStatus");

            // Show any additional progress keys
            $knownKeys = ['classification_complete', 'identity_complete', 'extraction_complete'];
            foreach ($progress as $key => $value) {
                if (!in_array($key, $knownKeys)) {
                    $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
                    $command->line("  $key: $displayValue");
                }
            }

            $command->newLine();
        }

        // Calculate overall progress
        $totalLevels          = count($levelProgress);
        $completeLevels       = 0;
        foreach ($levelProgress as $level => $progress) {
            $identityComplete   = $progress['identity_complete']   ?? false;
            $extractionComplete = $progress['extraction_complete'] ?? false;

            if ($identityComplete && $extractionComplete) {
                $completeLevels++;
            }
        }

        $command->info("Overall Progress: $completeLevels / $totalLevels levels complete");
    }
}
