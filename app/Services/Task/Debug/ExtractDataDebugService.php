<?php

namespace App\Services\Task\Debug;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Task\DataExtraction\IdentityExtractionService;
use App\Services\Task\Debug\Concerns\DebugOutputHelper;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Newms87\Danx\Models\Job\JobDispatch;
use ReflectionMethod;
use Throwable;

class ExtractDataDebugService
{
    use DebugOutputHelper;

    /**
     * Show task run overview.
     */
    public function showOverview(TaskRun $taskRun, Command $command): void
    {
        $this->showTaskRunHeader($taskRun, $command);

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
                $this->showClassifyProcessSummary($operationProcesses, $count, $command);
            }

            // Show details for Extract Identity processes
            if ($operation === ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY) {
                $this->showExtractionProcessSummary($operationProcesses, $command);
            }

            // Show details for Extract Remaining processes
            if ($operation === ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING) {
                $this->showExtractionProcessSummary($operationProcesses, $command);
            }
        }
        $command->newLine();
    }

    /**
     * Show summary of classify processes with status breakdown.
     */
    protected function showClassifyProcessSummary(
        Collection $operationProcesses,
        int $count,
        Command $command
    ): void {
        $completed = $operationProcesses->where('status', WorkflowStatesContract::STATUS_COMPLETED)->count();
        $pending   = $operationProcesses->where('status', WorkflowStatesContract::STATUS_PENDING)->count();
        $running   = $operationProcesses->where('status', WorkflowStatesContract::STATUS_RUNNING)->count();
        $failed    = $operationProcesses->where('status', WorkflowStatesContract::STATUS_FAILED)->count();

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

    /**
     * Show summary of extraction processes (identity or remaining) with status breakdown and details.
     */
    protected function showExtractionProcessSummary(
        Collection $operationProcesses,
        Command $command
    ): void {
        $completed = $operationProcesses->where('status', WorkflowStatesContract::STATUS_COMPLETED)->count();
        $pending   = $operationProcesses->where('status', WorkflowStatesContract::STATUS_PENDING)->count();
        $running   = $operationProcesses->where('status', WorkflowStatesContract::STATUS_RUNNING)->count();
        $failed    = $operationProcesses->where('status', WorkflowStatesContract::STATUS_FAILED)->count();

        $command->line("    Completed: $completed, Failed: $failed, Pending: $pending, Running: $running");

        // Show each process with level and object type
        foreach ($operationProcesses as $process) {
            $level      = $process->meta['level'] ?? '?';
            $status     = $process->status;
            $objectType = $this->getObjectTypeFromProcess($process);

            $command->line("      Level $level - $objectType: $status");
        }
    }

    /**
     * Extract object type from a task process meta.
     */
    protected function getObjectTypeFromProcess(TaskProcess $process): string
    {
        // For Extract Identity processes
        $identityGroup = $process->meta['identity_group'] ?? null;
        if ($identityGroup) {
            return $identityGroup['object_type'] ?? $identityGroup['name'] ?? 'Unknown';
        }

        // For Extract Remaining processes
        $extractionGroup = $process->meta['extraction_group'] ?? null;
        if ($extractionGroup) {
            return $extractionGroup['name'] ?? $extractionGroup['object_type'] ?? 'Unknown';
        }

        return 'Unknown';
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

        // Set started_at before running (matches TaskProcessRunnerService behavior)
        $process->started_at = now();
        $process->save();

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

            // Show the agent thread messages if any
            $this->showAgentThreadMessages($process, $command);

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
     *
     * This performs a full reset including:
     * - Deleting job dispatches and API logs from previous runs
     * - Deleting output artifacts linked to this process
     * - Clearing output artifact relationships
     * - Resetting all timestamps and status fields
     */
    protected function resetProcess(TaskProcess $process): void
    {
        // Delete job dispatches associated with this process (clears API logs from previous runs)
        $jobDispatchIds = DB::table('job_dispatchables')
            ->where('model_type', get_class($process))
            ->where('model_id', $process->id)
            ->pluck('job_dispatch_id');

        if ($jobDispatchIds->isNotEmpty()) {
            JobDispatch::whereIn('id', $jobDispatchIds)->get()->each->delete();
        }

        // Get output artifact IDs for this process and delete them
        // Using relationship instead of JSON query to avoid PostgreSQL unicode issues
        $artifactIds = $process->outputArtifacts()->pluck('artifacts.id');

        if ($artifactIds->isNotEmpty()) {
            Artifact::whereIn('id', $artifactIds)->delete();
        }

        // Clear output artifact relationship
        $process->clearOutputArtifacts();

        // Reset all timestamps and status fields
        $process->started_at    = null;
        $process->completed_at  = null;
        $process->failed_at     = null;
        $process->stopped_at    = null;
        $process->incomplete_at = null;
        $process->timeout_at    = null;
        $process->is_ready      = true;
        $process->activity      = null;
        $process->error_count   = 0;
        $process->status        = WorkflowStatesContract::STATUS_PENDING;
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
                        $command->line('      Data: ' . $this->truncate(json_encode($teamObject->meta), 100, '...'));
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

    /**
     * Show cached extraction plan with fragment selector types.
     */
    public function showCachedPlan(TaskRun $taskRun, Command $command): void
    {
        $taskDef = $taskRun->taskDefinition;
        $plan    = $taskDef->meta['extraction_plan'] ?? null;

        $command->info('=== Cached Extraction Plan ===');
        $command->line("TaskDefinition ID: {$taskDef->id}");
        $command->newLine();

        if (!$plan) {
            $command->warn('No extraction_plan cached on TaskDefinition');

            return;
        }

        $levels = $plan['levels'] ?? [];
        $command->line('Total Levels: ' . count($levels));
        $command->newLine();

        foreach ($levels as $levelIdx => $level) {
            $command->info("Level $levelIdx:");

            // Show identity groups
            $command->line('  Identity Groups:');
            foreach ($level['identities'] ?? [] as $identity) {
                $objectType = $identity['object_type']       ?? 'Unknown';
                $selector   = $identity['fragment_selector'] ?? [];

                $command->line("    $objectType:");
                $this->showFragmentSelectorTypes($selector, $command, 6);
            }

            // Show remaining groups
            $command->line('  Remaining Groups:');
            foreach ($level['remaining'] ?? [] as $group) {
                $groupName  = $group['name']              ?? 'Unknown';
                $objectType = $group['object_type']       ?? 'Unknown';
                $selector   = $group['fragment_selector'] ?? [];

                $command->line("    $groupName ($objectType):");
                $this->showFragmentSelectorTypes($selector, $command, 6);
            }

            $command->newLine();
        }

        // Also show the schema's actual types for comparison
        $schema = $taskDef->schemaDefinition?->schema ?? [];
        $command->info('=== Schema Actual Types ===');
        $this->showSchemaTypes($schema, $command);
    }

    /**
     * Recursively show fragment selector types.
     */
    protected function showFragmentSelectorTypes(array $selector, Command $command, int $indent = 0): void
    {
        $prefix = str_repeat(' ', $indent);
        $type   = $selector['type'] ?? 'unknown';
        $command->line("{$prefix}type: $type");

        $children = $selector['children'] ?? [];
        if (!empty($children)) {
            foreach ($children as $name => $child) {
                $childType = $child['type'] ?? 'unknown';
                $command->line("{$prefix}  $name: $childType");

                // Recursively show nested children (but limit depth)
                if (isset($child['children']) && $indent < 12) {
                    $this->showFragmentSelectorTypes($child, $command, $indent + 4);
                }
            }
        }
    }

    /**
     * Show top-level schema types for comparison.
     */
    protected function showSchemaTypes(array $schema, Command $command, string $path = '', int $depth = 0): void
    {
        if ($depth > 3) {
            return; // Limit depth
        }

        $prefix      = str_repeat('  ', $depth);
        $type        = $schema['type'] ?? 'unknown';
        $pathDisplay = $path ?: '(root)';
        $command->line("{$prefix}{$pathDisplay}: $type");

        $properties = $schema['properties'] ?? [];
        foreach ($properties as $propName => $propSchema) {
            $propPath = $path ? "$path.$propName" : $propName;
            $propType = $propSchema['type'] ?? 'unknown';

            // Handle union types
            if (is_array($propType)) {
                $propType = implode('|', $propType);
            }

            $command->line("{$prefix}  $propName: $propType");

            // For arrays, show items type
            if ($propType === 'array' && isset($propSchema['items'])) {
                $this->showSchemaTypes($propSchema['items'], $command, "$propPath.items", $depth + 2);
            } elseif ($propType === 'object' && isset($propSchema['properties'])) {
                // For objects, recurse into properties
                $this->showSchemaTypes($propSchema, $command, $propPath, $depth + 2);
            }
        }
    }

    /**
     * Clear cached extraction plan from TaskDefinition.
     */
    public function clearCachedPlan(TaskRun $taskRun, Command $command): void
    {
        $taskDef = $taskRun->taskDefinition;
        $meta    = $taskDef->meta;

        if (!isset($meta['extraction_plan'])) {
            $command->warn('No extraction_plan to clear');

            return;
        }

        unset($meta['extraction_plan']);
        $taskDef->meta = $meta;
        $taskDef->save();

        $command->info("Cleared extraction_plan from TaskDefinition {$taskDef->id}");
    }

    /**
     * Show the extraction response schema for a task process.
     */
    public function showExtractionSchema(TaskRun $taskRun, int $processId, Command $command): void
    {
        $process = $taskRun->taskProcesses()->find($processId);

        if (!$process) {
            $command->error("TaskProcess $processId not found in TaskRun {$taskRun->id}");

            return;
        }

        $meta          = $process->meta          ?? [];
        $identityGroup = $meta['identity_group'] ?? null;

        if (!$identityGroup) {
            $command->warn('No identity_group in task process meta');

            return;
        }

        $schemaDefinition = $taskRun->taskDefinition->schemaDefinition;

        if (!$schemaDefinition) {
            $command->warn('No schema definition found');

            return;
        }

        $command->info('=== Extraction Response Schema ===');
        $command->line('Process ID: ' . $processId);
        $command->line('Operation: ' . ($process->operation ?? 'unknown'));
        $command->line('Object Type: ' . ($identityGroup['object_type'] ?? 'unknown'));
        $command->line('Identity Fields: ' . json_encode($identityGroup['identity_fields'] ?? []));
        $command->newLine();

        // Use reflection to call protected method
        $service = app(IdentityExtractionService::class);
        $method  = new ReflectionMethod($service, 'buildExtractionResponseSchema');

        $schema = $method->invoke($service, $schemaDefinition, $identityGroup, []);

        $command->info('=== Response Schema (sent to LLM) ===');
        $command->line(json_encode($schema, JSON_PRETTY_PRINT));
    }

    /**
     * Display agent thread messages from a task process.
     */
    protected function showAgentThreadMessages(TaskProcess $process, Command $command): void
    {
        $thread = $process->agentThread;

        if (!$thread) {
            $command->warn('No agent thread associated with this process');

            return;
        }

        $command->newLine();
        $command->info('=== Agent Thread Messages ===');
        $command->line("Thread ID: {$thread->id}");
        $command->newLine();

        $messages = $thread->messages()->orderBy('id')->get();

        if ($messages->isEmpty()) {
            $command->warn('No messages in thread');

            return;
        }

        foreach ($messages as $message) {
            $role = strtoupper($message->role ?? 'unknown');
            $command->line("[$role]");

            // Show title if present
            if ($message->title) {
                $command->line("Title: {$message->title}");
            }

            // Show content (truncate if very long)
            $content = $message->content ?? '';
            if (strlen($content) > 2000) {
                $content = substr($content, 0, 2000) . "\n... (truncated, " . strlen($message->content) . ' chars total)';
            }
            $command->line($content);
            $command->newLine();
        }

        $command->line("Total messages: {$messages->count()}");
    }
}
