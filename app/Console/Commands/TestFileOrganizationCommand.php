<?php

namespace App\Console\Commands;

use App\Models\Task\Artifact;
use App\Models\Task\TaskDefinition;
use App\Models\Task\TaskRun;
use App\Models\Workflow\WorkflowInput;
use App\Services\Task\Runners\FileOrganizationTaskRunner;
use App\Services\Task\TaskProcessDispatcherService;
use Illuminate\Console\Command;

class TestFileOrganizationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:file-organization {workflow-input : Workflow input ID}';

    /**
     * The console command description.
     */
    protected $description = 'Test FileOrganizationTaskRunner with a WorkflowInput';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $workflowInputId = $this->argument('workflow-input');

        // Load WorkflowInput
        $this->info("Loading WorkflowInput: $workflowInputId");
        $workflowInput = WorkflowInput::findOrFail($workflowInputId);

        // Get storedFiles from WorkflowInput
        $storedFiles = $workflowInput->storedFiles;
        $this->info("Found {$storedFiles->count()} stored files");

        if ($storedFiles->isEmpty()) {
            $this->error('No stored files found in WorkflowInput');

            return 1;
        }

        // Create artifacts from files/transcodes
        $artifacts = collect();
        $position  = 1;

        foreach ($storedFiles as $storedFile) {
            $this->line("Processing StoredFile {$storedFile->id}: {$storedFile->filename}");

            // Check for transcodes
            $transcodes = $storedFile->transcodes();
            if ($transcodes->exists()) {
                $transcodesList = $transcodes->get();
                $this->info("  Found {$transcodesList->count()} transcodes");

                foreach ($transcodesList as $transcode) {
                    $artifact = Artifact::factory()->create([
                        'name'     => $transcode->filename,
                        'team_id'  => $workflowInput->team_id,
                        'position' => $position++,
                    ]);
                    // Attach the transcode file to the artifact
                    $artifact->storedFiles()->attach($transcode->id);
                    $artifacts->push($artifact);
                    $this->line("    Created artifact from transcode: {$transcode->filename}");
                }
            } else {
                // Use the file itself
                $this->info('  No transcodes, using file directly');
                $artifact = Artifact::factory()->create([
                    'name'     => $storedFile->filename,
                    'team_id'  => $workflowInput->team_id,
                    'position' => $position++,
                ]);
                // Attach the file to the artifact
                $artifact->storedFiles()->attach($storedFile->id);
                $artifacts->push($artifact);
                $this->line("  Created artifact from file: {$storedFile->filename}");
            }
        }

        $this->info("Created {$artifacts->count()} total artifacts");
        $this->newLine();

        // Get or create an agent for the task definition
        $agent = \App\Models\Agent\Agent::where('team_id', $workflowInput->team_id)->first();
        if (!$agent) {
            $this->error('No agent found for team. Please create an agent first.');

            return 1;
        }

        // Create TaskDefinition for file-organization runner
        $this->info('Creating TaskDefinition for File Organization runner');
        $taskDefinition = TaskDefinition::factory()->create([
            'name'                => 'Test File Organization',
            'task_runner_name'    => FileOrganizationTaskRunner::RUNNER_NAME,
            'team_id'             => $workflowInput->team_id,
            'agent_id'            => $agent->id,
            'task_runner_config'  => [
                'comparison_window_size' => 3,
            ],
            'output_artifact_mode' => TaskDefinition::OUTPUT_ARTIFACT_MODE_GROUP_ALL,
        ]);

        $this->line("Using agent: {$agent->name} ({$agent->model})");

        $this->line("TaskDefinition created: {$taskDefinition->id}");
        $this->newLine();

        // Create TaskRun
        $this->info('Creating TaskRun');
        $taskRun = TaskRun::factory()->create([
            'task_definition_id' => $taskDefinition->id,
        ]);

        $this->line("TaskRun created: {$taskRun->id}");
        $this->newLine();

        // Attach input artifacts to TaskRun
        $this->info('Attaching input artifacts to TaskRun');
        foreach ($artifacts as $artifact) {
            $taskRun->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
        }

        $taskRun->updateRelationCounter('inputArtifacts');
        $this->line("Attached {$artifacts->count()} input artifacts");
        $this->newLine();

        // Get the runner and prepare
        $this->info('Preparing TaskRun');
        $runner = $taskRun->getRunner();

        $this->line('Runner class: ' . get_class($runner));
        $this->line("Before prepareRun - processes: {$taskRun->taskProcesses()->count()}");

        $runner->prepareRun();
        $taskRun->save(); // Save after prepareRun to persist any changes

        $this->line("After prepareRun - processes: {$taskRun->taskProcesses()->count()}");
        $this->line('TaskRun prepared successfully');
        $this->newLine();

        // Refresh to get the processes created during prepareRun
        $taskRun->refresh();
        $this->line("After refresh - processes: {$taskRun->taskProcesses()->count()}");

        // Display initial state
        $this->info('=== INITIAL STATE ===');
        $this->line("Files/transcodes: {$artifacts->count()}");
        foreach ($artifacts as $artifact) {
            $this->line("  - Position {$artifact->position}: {$artifact->name}");
        }
        $this->newLine();

        $windowProcesses = $taskRun->taskProcesses()->get();
        $this->line("Window processes created: {$windowProcesses->count()}");
        foreach ($windowProcesses as $process) {
            $inputArtifact = $process->inputArtifacts->first();
            $storedFiles   = $inputArtifact?->storedFiles;
            $windowStart   = $storedFiles?->min('page_number') ?? 'N/A';
            $windowEnd     = $storedFiles?->max('page_number') ?? 'N/A';
            $fileCount     = $storedFiles?->count() ?? 0;
            $this->line("  - Window {$windowStart}-{$windowEnd}: {$fileCount} files");
        }
        $this->newLine();

        // Run window processes
        $this->info('Dispatching window processes...');
        TaskProcessDispatcherService::dispatchForTaskRun($taskRun);

        // Wait for processes to complete
        $this->info('Waiting for window processes to complete...');
        $this->waitForProcesses($taskRun, 'window');
        $this->newLine();

        // Display window results from window process output artifacts
        $this->info('=== WINDOW COMPARISON RESULTS ===');
        $windowProcesses = $taskRun->taskProcesses()
            ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
            ->get();

        $this->line("Window processes: {$windowProcesses->count()}");
        foreach ($windowProcesses as $windowProcess) {
            $inputArtifact = $windowProcess->inputArtifacts->first();
            $storedFiles   = $inputArtifact?->storedFiles;
            $windowStart   = $storedFiles?->min('page_number') ?? '?';
            $windowEnd     = $storedFiles?->max('page_number') ?? '?';

            $outputArtifact = $windowProcess->outputArtifacts->first();
            $groups         = $outputArtifact?->json_content['groups'] ?? [];
            $this->line("  Window {$windowStart}-{$windowEnd}: " . count($groups) . ' groups found');
            foreach ($groups as $group) {
                $groupName = $group['name'] ?? $group['group_id'] ?? 'Unknown';
                $fileCount = count($group['files'] ?? []);
                $this->line("    - Group '{$groupName}': {$fileCount} files");
            }
        }
        $this->newLine();

        // Trigger merge process
        $this->info('Triggering merge process...');
        $runner->afterAllProcessesCompleted();

        // Wait for merge to complete
        $this->info('Waiting for merge process to complete...');
        $this->waitForProcesses($taskRun, 'merge');
        $this->newLine();

        // Display final results
        $this->info('=== FINAL MERGED GROUPS ===');
        $taskRun->refresh();
        $finalArtifacts = $taskRun->outputArtifacts()
            ->whereNotNull('meta->group_name')
            ->get();

        $this->line("Final groups: {$finalArtifacts->count()}");
        $this->newLine();

        foreach ($finalArtifacts as $finalArtifact) {
            $groupName   = $finalArtifact->meta['group_name'];
            $description = $finalArtifact->meta['description'] ?? '';
            $fileCount   = $finalArtifact->meta['file_count']  ?? 0;

            $this->info("Group: $groupName");
            $this->line("  Description: $description");
            $this->line("  Files: $fileCount");

            // Get file names from children
            $groupArtifacts = $finalArtifact->children()
                ->orderBy('position')
                ->get();

            foreach ($groupArtifacts as $artifact) {
                $this->line("    - {$artifact->name}");
            }

            $this->newLine();
        }

        $this->info('✅ File organization test completed successfully');

        return 0;
    }

    /**
     * Wait for processes to complete.
     *
     * @param  string  $processType  'window' or 'merge'
     */
    protected function waitForProcesses(TaskRun $taskRun, string $processType): void
    {
        $maxWaitTime    = 300; // 5 minutes
        $startTime      = time();
        $dotInterval    = 2; // Print a dot every 2 seconds
        $lastDot        = time();
        $statusInterval = 10; // Print detailed status every 10 seconds
        $lastStatus     = time();

        while (true) {
            $taskRun->refresh();

            if ($processType === 'window') {
                $allProcesses = $taskRun->taskProcesses()
                    ->where('operation', FileOrganizationTaskRunner::OPERATION_COMPARISON_WINDOW)
                    ->get();
                $pendingProcesses = $allProcesses->whereNotIn('status', ['Completed', 'Failed'])->count();
            } else {
                // merge
                $allProcesses = $taskRun->taskProcesses()
                    ->where('operation', FileOrganizationTaskRunner::OPERATION_MERGE)
                    ->get();
                $pendingProcesses = $allProcesses->whereNotIn('status', ['Completed', 'Failed'])->count();
            }

            if ($pendingProcesses === 0) {
                $this->line(''); // New line after dots
                $this->info('All processes completed');

                // Show any failures
                $failedProcesses = $allProcesses->where('status', 'Failed');
                if ($failedProcesses->count() > 0) {
                    $this->warn("Failed processes: {$failedProcesses->count()}");
                    foreach ($failedProcesses as $failed) {
                        $this->error("  - Process {$failed->id}: {$failed->name} - {$failed->error_message}");
                    }
                }
                break;
            }

            // Print detailed status every interval
            if (time() - $lastStatus >= $statusInterval) {
                $this->line(''); // New line
                $completed = $allProcesses->where('status', 'Completed')->count();
                $failed    = $allProcesses->where('status', 'Failed')->count();
                $running   = $allProcesses->whereIn('status', ['Running', 'Pending'])->count();
                $this->line("  Status: {$completed} completed, {$failed} failed, {$running} running/pending");
                $lastStatus = time();
            } elseif (time() - $lastDot >= $dotInterval) {
                // Print dot every interval
                $this->line('.', null, false);
                $lastDot = time();
            }

            // Check timeout
            if (time() - $startTime > $maxWaitTime) {
                $this->line(''); // New line after dots
                $this->warn('Timeout waiting for processes to complete');
                break;
            }

            sleep(1);
        }
    }
}
