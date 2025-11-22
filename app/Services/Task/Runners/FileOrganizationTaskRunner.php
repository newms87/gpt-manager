<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Repositories\ThreadRepository;
use App\Services\Task\FileOrganizationMergeService;
use App\Services\Task\TaskProcessDispatcherService;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Task\TaskRunnerService;
use Newms87\Danx\Exceptions\ValidationError;

class FileOrganizationTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'File Organization';

    public function run(): void
    {
        // Check if this is a comparison window process
        $windowFiles = $this->taskProcess->meta['window_files'] ?? null;
        if ($windowFiles) {
            static::logDebug('Running comparison window process');
            $this->runComparisonWindow($windowFiles);

            return;
        }

        // Check if this is a merge process
        $isMergeProcess = $this->taskProcess->meta['is_merge_process'] ?? false;
        if ($isMergeProcess) {
            static::logDebug('Running merge process');
            $this->runMergeProcess();

            return;
        }

        // This is the initial process - create window processes
        static::logDebug('Initial process - creating window processes');
        $this->createWindowProcesses();
        $this->complete();
    }

    /**
     * Run a comparison window process.
     * Compares adjacent files in the window and groups them.
     */
    protected function runComparisonWindow(array $windowFiles): void
    {
        $inputArtifacts = $this->taskProcess->inputArtifacts;

        // Filter artifacts to only those in the window
        $windowFileIds     = array_column($windowFiles, 'file_id');
        $artifactsInWindow = $inputArtifacts->whereIn('id', $windowFileIds);

        static::logDebug('Window contains ' . $artifactsInWindow->count() . ' artifacts');

        // Setup agent thread
        $agentThread = $this->setupAgentThread($artifactsInWindow);

        // Add file organization specific instructions
        app(ThreadRepository::class)->addMessageToThread($agentThread,
            "You are comparing adjacent files to organize them into logical groups.\n" .
            "Each file represents a page or document section.\n" .
            "Group files that belong together based on their content and context.\n\n" .
            "For each group:\n" .
            "- 'name': A clear, descriptive name for the group (e.g., 'Bills', 'Medical Summary')\n" .
            "- 'description': A high-level summary of what the group contains\n" .
            "- 'files': Array of position numbers (integers) for files in this group\n\n" .
            "IMPORTANT: Only include files that should be kept. If a file should be ignored (e.g., blank page, irrelevant content), simply don't include its position number in any group."
        );

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from agent thread');
        }

        // Store window metadata in the artifact
        $meta                 = $artifact->meta ?? [];
        $meta['window_start'] = $this->taskProcess->meta['window_start'];
        $meta['window_end']   = $this->taskProcess->meta['window_end'];
        $meta['window_files'] = $this->taskProcess->meta['window_files'];
        $artifact->meta       = $meta;
        $artifact->save();

        static::logDebug('Window comparison completed successfully');

        // Window artifacts should only be attached to the process, not the task run
        // They are intermediate results that will be merged later
        $this->completeWindowProcess([$artifact]);
    }

    /**
     * Complete a window process without adding artifacts to task run output.
     * Window artifacts are intermediate results that should only be attached to the process.
     */
    protected function completeWindowProcess(array $artifacts): void
    {
        static::logDebug("Window process completed: $this->taskProcess");

        if ($artifacts) {
            TaskRunnerService::validateArtifacts($artifacts);
            static::prepareArtifactsForOutput($artifacts);

            static::logDebug('Attaching window artifacts to process only (not task run): ' . collect($artifacts)->pluck('id')->toJson());

            // Only attach to the process, not the task run
            $artifactIds = collect($artifacts)->pluck('id')->toArray();
            $this->taskProcess->outputArtifacts()->sync($artifactIds);
            $this->taskProcess->updateRelationCounter('outputArtifacts');
        }

        if ($this->taskProcess->percent_complete < 100) {
            $this->activity('Task completed successfully', 100);
        }

        // Finished running the process
        TaskProcessRunnerService::complete($this->taskProcess);
    }

    /**
     * Run the merge process.
     * Merges all window results into final groups.
     */
    protected function runMergeProcess(): void
    {
        static::logDebug('Starting merge of window results');

        // Get all window artifacts from window task processes
        // Note: Window artifacts are not in task run outputs, only in their process outputs
        $windowProcesses = $this->taskRun->taskProcesses()
            ->whereNotNull('meta->window_files')
            ->get();

        $windowArtifacts = collect();
        foreach ($windowProcesses as $process) {
            $windowArtifacts = $windowArtifacts->merge($process->outputArtifacts);
        }

        static::logDebug('Found ' . $windowArtifacts->count() . ' window artifacts to merge');

        if ($windowArtifacts->isEmpty()) {
            static::logDebug('No window artifacts found, completing with no output');
            $this->complete();

            return;
        }

        // Merge the windows
        $mergeService = app(FileOrganizationMergeService::class);
        $finalGroups  = $mergeService->mergeWindowResults($windowArtifacts);

        static::logDebug('Merge completed: ' . count($finalGroups) . ' final groups');

        // Create output artifacts for each final group
        $outputArtifacts = [];

        foreach ($finalGroups as $group) {
            $groupName   = $group['name'];
            $description = $group['description'] ?? '';
            $fileIds     = $group['files'];

            // Get the artifacts for this group
            $groupArtifacts = $this->taskRun->inputArtifacts()
                ->whereIn('artifacts.id', $fileIds)
                ->orderBy('artifacts.position')
                ->get();

            if ($groupArtifacts->isEmpty()) {
                static::logDebug("Group '$groupName': no artifacts found, skipping");

                continue;
            }

            // Find the window artifact that identified this group (use its name if available)
            $windowArtifactName = null;
            foreach ($windowArtifacts as $windowArtifact) {
                $groups = $windowArtifact->json_content['groups'] ?? [];
                foreach ($groups as $windowGroup) {
                    if (($windowGroup['name'] ?? null) === $groupName) {
                        $windowArtifactName = $windowArtifact->name;
                        break 2;
                    }
                }
            }

            // Create copies of input artifacts to preserve originals
            $artifactCopies = [];
            foreach ($groupArtifacts as $artifact) {
                $artifactCopies[] = $artifact->copy();
            }

            // Create merged artifact for this group
            $mergedArtifact = \App\Services\Task\ArtifactsMergeService::class;
            $mergedArtifact = app($mergedArtifact)->merge($artifactCopies);

            // Use the window artifact's name if available, otherwise use a generic name
            $mergedArtifact->name = $windowArtifactName ?? "Group: $groupName";
            $mergedArtifact->meta = array_merge($mergedArtifact->meta ?? [], [
                'group_name'  => $groupName,
                'description' => $description,
                'file_count'  => count($fileIds),
            ]);
            $mergedArtifact->save();

            $outputArtifacts[] = $mergedArtifact;

            static::logDebug("Created merged artifact for group '$groupName' with " . count($fileIds) . ' files');
        }

        static::logDebug('Created ' . count($outputArtifacts) . ' output artifacts');

        $this->complete($outputArtifacts);
    }

    /**
     * Create window processes for comparing adjacent files.
     * Called from the initial process run.
     */
    protected function createWindowProcesses(): void
    {
        static::logDebug('Creating file organization window processes');

        // Get comparison window size from config
        $windowSize = $this->config('comparison_window_size', 3);

        // Validate window size
        if ($windowSize < 2 || $windowSize > 5) {
            throw new ValidationError('comparison_window_size must be between 2 and 5');
        }

        static::logDebug("Using comparison window size: $windowSize");

        // Get all input artifacts
        $inputArtifacts = $this->taskRun->inputArtifacts()
            ->orderBy('position')
            ->get();

        static::logDebug('Found ' . $inputArtifacts->count() . ' input artifacts');

        if ($inputArtifacts->isEmpty()) {
            static::logDebug('No input artifacts, skipping window creation');

            return;
        }

        // Create file list from artifacts
        $mergeService = app(FileOrganizationMergeService::class);
        $files        = $mergeService->getFileListFromArtifacts($inputArtifacts);

        // Create overlapping windows
        $windows = $mergeService->createOverlappingWindows($files, $windowSize);

        static::logDebug('Created ' . count($windows) . ' comparison windows');

        if (empty($windows)) {
            static::logDebug('No windows created, skipping');

            return;
        }

        // Create a TaskProcess for each window
        foreach ($windows as $window) {
            $windowFiles   = $window['files'];
            $windowFileIds = array_column($windowFiles, 'file_id');

            // Get artifacts for this window
            $windowArtifacts = $inputArtifacts->whereIn('id', $windowFileIds);

            // Create the process directly (not using TaskProcessRunnerService::prepare to avoid agent setup)
            $taskProcess = $this->taskRun->taskProcesses()->create([
                'name'     => "Compare Files {$window['window_start']}-{$window['window_end']}",
                'activity' => 'Comparing adjacent files in window',
                'meta'     => [
                    'window_files' => $windowFiles,
                    'window_start' => $window['window_start'],
                    'window_end'   => $window['window_end'],
                    'window_index' => $window['window_index'],
                ],
                'is_ready' => true, // Ready to run immediately
            ]);

            // Attach input artifacts to the window process
            foreach ($windowArtifacts as $artifact) {
                $taskProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            }
            $taskProcess->updateRelationCounter('inputArtifacts');

            static::logDebug("Created window process {$taskProcess->id}: positions {$window['window_start']}-{$window['window_end']}");
        }

        $this->taskRun->updateRelationCounter('taskProcesses');

        static::logDebug('Window creation completed');

        // Dispatch the window processes
        TaskProcessDispatcherService::dispatchForTaskRun($this->taskRun);
    }

    /**
     * Prepare the task run.
     * Creates an initial process that will create window processes when it runs.
     */
    public function prepareRun(): void
    {
        parent::prepareRun();
        static::logDebug('File organization task run prepared');

        // Create the initial process that will create window processes
        $initialProcess = $this->taskRun->taskProcesses()->create([
            'name'     => 'Initialize File Organization',
            'activity' => 'Creating window processes for file comparison',
            'meta'     => [], // Empty meta indicates this is the initial process
            'is_ready' => true, // Ready to run immediately
        ]);

        $this->taskRun->updateRelationCounter('taskProcesses');

        static::logDebug("Created initial process: {$initialProcess->id}");
    }

    /**
     * Called after all parallel processes have completed.
     * Creates a merge process to combine all window results.
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();

        // Check if a merge process already exists
        $hasMergeProcess = $this->taskRun->taskProcesses()
            ->whereNotNull('meta->is_merge_process')
            ->exists();

        if ($hasMergeProcess) {
            static::logDebug('Merge process already exists or completed');

            return;
        }

        // Check if window processes exist and have completed
        $windowProcesses = $this->taskRun->taskProcesses()
            ->whereNotNull('meta->window_files')
            ->get();

        if ($windowProcesses->isEmpty()) {
            static::logDebug('No window processes found - skipping merge process creation');

            return;
        }

        // Check if all window processes are completed
        $allCompleted = $windowProcesses->every(function ($process) {
            return $process->status === 'Completed';
        });

        if (!$allCompleted) {
            static::logDebug('Window processes still running - skipping merge process creation');

            return;
        }

        static::logDebug('Creating merge process');

        // Create the merge process
        $mergeProcess = $this->taskRun->taskProcesses()->create([
            'name'     => 'Merge Window Results',
            'activity' => 'Merging window comparison results into final groups',
            'meta'     => ['is_merge_process' => true],
            'is_ready' => true,
        ]);

        $this->taskRun->updateRelationCounter('taskProcesses');

        static::logDebug("Created merge process: $mergeProcess");

        // Dispatch the merge process
        TaskProcessDispatcherService::dispatchForTaskRun($this->taskRun);
    }

    /**
     * Get the JSON Schema for file organization responses.
     */
    protected function getFileOrganizationSchema(): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [
                'groups' => [
                    'type'        => 'array',
                    'items'       => [
                        'type'                 => 'object',
                        'properties'           => [
                            'name'        => [
                                'type'        => 'string',
                                'description' => 'Name of this group (e.g., "Bills", "Medical Summary", "Contract")',
                            ],
                            'description' => [
                                'type'        => 'string',
                                'description' => 'High-level description of the contents of this group',
                            ],
                            'files'       => [
                                'type'        => 'array',
                                'items'       => [
                                    'type'        => 'integer',
                                    'description' => 'Position number of the file in the original document',
                                ],
                                'description' => 'Array of file position numbers that belong to this group',
                            ],
                        ],
                        'required'             => ['name', 'description', 'files'],
                        'additionalProperties' => false,
                    ],
                    'description' => 'Groups of related files. Only include files that should be kept - omit files that should be ignored (e.g., blank pages).',
                ],
            ],
            'required'             => ['groups'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Override runAgentThreadWithSchema to use our custom schema.
     */
    public function setupAgentThread($artifacts = [], $contextArtifacts = []): AgentThread
    {
        // Call parent to create the thread
        $agentThread = parent::setupAgentThread($artifacts, $contextArtifacts);

        // Get or create the schema definition for file organization
        $schema = $this->getFileOrganizationSchema();

        $schemaDefinition = SchemaDefinition::updateOrCreate([
            'team_id' => $this->taskRun->taskDefinition->team_id,
            'name'    => 'File Organization Response',
            'type'    => 'FileOrganizationResponse',
        ], [
            'description'   => 'JSON schema for file organization task responses',
            'schema'        => $schema,
            'schema_format' => SchemaDefinition::FORMAT_JSON,
        ]);

        // Always use the current schema
        $this->taskDefinition->schema_definition_id = $schemaDefinition->id;
        $this->taskDefinition->save();

        return $agentThread;
    }
}
