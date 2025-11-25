<?php

namespace App\Services\Task\Runners;

use App\Models\Agent\AgentThread;
use App\Models\Schema\SchemaDefinition;
use App\Repositories\ThreadRepository;
use App\Services\Task\FileOrganizationMergeService;
use App\Services\Task\TaskAgentThreadBuilderService;
use App\Services\Task\TaskProcessDispatcherService;
use App\Services\Task\TaskProcessRunnerService;
use App\Services\Task\TaskRunnerService;
use Newms87\Danx\Exceptions\ValidationError;

class FileOrganizationTaskRunner extends AgentThreadTaskRunner
{
    const string RUNNER_NAME = 'File Organization';

    const string OPERATION_INITIALIZE = 'Initialize';

    const string OPERATION_COMPARISON_WINDOW = 'Comparison Window';

    const string OPERATION_MERGE = 'Merge';

    const string OPERATION_LOW_CONFIDENCE_RESOLUTION = 'Low Confidence Resolution';

    const string OPERATION_NULL_GROUP_RESOLUTION = 'Null Group Resolution';

    public function run(): void
    {
        // Check if this is a comparison window process
        if ($this->taskProcess->operation === self::OPERATION_COMPARISON_WINDOW) {
            static::logDebug('Running comparison window process');
            $windowFiles = $this->taskProcess->meta['window_files'] ?? null;
            $this->runComparisonWindow($windowFiles);

            return;
        }

        // Check if this is a merge process
        if ($this->taskProcess->operation === self::OPERATION_MERGE) {
            static::logDebug('Running merge process');
            $this->runMergeProcess();

            return;
        }

        // Check if this is a low confidence resolution process
        if ($this->taskProcess->operation === self::OPERATION_LOW_CONFIDENCE_RESOLUTION) {
            static::logDebug('Running low confidence resolution process');
            $this->runLowConfidenceResolution();

            return;
        }

        // Check if this is a null group resolution process
        if ($this->taskProcess->operation === self::OPERATION_NULL_GROUP_RESOLUTION) {
            static::logDebug('Running null group resolution process');
            $this->runNullGroupResolution();

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

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from agent thread');
        }

        // Validate that no page appears in multiple groups
        $this->validateNoDuplicatePages($artifact->json_content);

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
            ->where('operation', self::OPERATION_COMPARISON_WINDOW)
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
        $mergeResult  = $mergeService->mergeWindowResults($windowArtifacts);
        $finalGroups  = $mergeResult['groups'];
        $fileToGroup  = $mergeResult['file_to_group_mapping'];
        $nullGroupsNeedingLlm = $mergeResult['null_groups_needing_llm'] ?? [];

        static::logDebug('Merge completed: ' . count($finalGroups) . ' final groups');

        if (!empty($nullGroupsNeedingLlm)) {
            static::logDebug('Found ' . count($nullGroupsNeedingLlm) . ' null group files that need LLM resolution');
        }

        // Check for low-confidence files
        $lowConfidenceFiles = $mergeService->identifyLowConfidenceFiles($fileToGroup);

        // Store any issues that need resolution in task process meta
        $metaUpdates = [];

        if (!empty($lowConfidenceFiles)) {
            static::logDebug('Found ' . count($lowConfidenceFiles) . ' low-confidence files - storing in task process meta');
            $metaUpdates['low_confidence_files'] = $lowConfidenceFiles;
        }

        if (!empty($nullGroupsNeedingLlm)) {
            static::logDebug('Found ' . count($nullGroupsNeedingLlm) . ' null group files needing LLM resolution - storing in task process meta');
            $metaUpdates['null_groups_needing_llm'] = $nullGroupsNeedingLlm;
        }

        if (!empty($metaUpdates)) {
            $this->taskProcess->meta = array_merge($this->taskProcess->meta ?? [], $metaUpdates);
            $this->taskProcess->save();
        }

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

        // Create file list from artifacts (using page_number from StoredFile)
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
                'name'      => "Compare Files {$window['window_start']}-{$window['window_end']}",
                'operation' => self::OPERATION_COMPARISON_WINDOW,
                'activity'  => 'Comparing adjacent files in window',
                'meta'      => [
                    'window_files' => $windowFiles, // Contains page_number and file_id
                    'window_start' => $window['window_start'],
                    'window_end'   => $window['window_end'],
                    'window_index' => $window['window_index'],
                ],
                'is_ready'  => true, // Ready to run immediately
            ]);

            // Attach input artifacts to the window process
            foreach ($windowArtifacts as $artifact) {
                $taskProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
            }
            $taskProcess->updateRelationCounter('inputArtifacts');

            static::logDebug("Created window process {$taskProcess->id}: page numbers {$window['window_start']}-{$window['window_end']}");
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
            'name'      => 'Initialize File Organization',
            'operation' => self::OPERATION_INITIALIZE,
            'activity'  => 'Creating window processes for file comparison',
            'meta'      => [],
            'is_ready'  => true, // Ready to run immediately
        ]);

        $this->taskRun->updateRelationCounter('taskProcesses');

        static::logDebug("Created initial process: {$initialProcess->id}");
    }

    /**
     * Called after all parallel processes have completed.
     * Creates a merge process to combine all window results, or a resolution process for low-confidence files.
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();

        // Check if resolution processes already exist
        $hasLowConfidenceResolution = $this->taskRun->taskProcesses()
            ->where('operation', self::OPERATION_LOW_CONFIDENCE_RESOLUTION)
            ->exists();

        $hasNullGroupResolution = $this->taskRun->taskProcesses()
            ->where('operation', self::OPERATION_NULL_GROUP_RESOLUTION)
            ->exists();

        if ($hasLowConfidenceResolution && $hasNullGroupResolution) {
            static::logDebug('All resolution processes already exist or completed');

            return;
        }

        // Check if merge process has low-confidence files
        $mergeProcess = $this->taskRun->taskProcesses()
            ->where('operation', self::OPERATION_MERGE)
            ->first();

        // Create low-confidence resolution process if needed
        if (!$hasLowConfidenceResolution && $mergeProcess && isset($mergeProcess->meta['low_confidence_files'])) {
            $lowConfidenceFiles = $mergeProcess->meta['low_confidence_files'];

            if (!empty($lowConfidenceFiles)) {
                static::logDebug('Creating resolution process for ' . count($lowConfidenceFiles) . ' low-confidence files');

                // Get the uncertain files' artifacts
                $uncertainFileIds   = array_column($lowConfidenceFiles, 'file_id');
                $uncertainArtifacts = $this->taskRun->inputArtifacts()
                    ->whereIn('artifacts.id', $uncertainFileIds)
                    ->get();

                // Create the resolution process
                $resolutionProcess = $this->taskRun->taskProcesses()->create([
                    'name'      => 'Resolve Low Confidence Files',
                    'operation' => self::OPERATION_LOW_CONFIDENCE_RESOLUTION,
                    'activity'  => 'Reviewing files with uncertain grouping',
                    'meta'      => [
                        'low_confidence_files' => $lowConfidenceFiles,
                    ],
                    'is_ready'  => true,
                ]);

                // Attach uncertain files as input artifacts
                foreach ($uncertainArtifacts as $artifact) {
                    $resolutionProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
                }
                $resolutionProcess->updateRelationCounter('inputArtifacts');

                $this->taskRun->updateRelationCounter('taskProcesses');

                static::logDebug("Created low confidence resolution process: $resolutionProcess");

                // Dispatch the resolution process
                TaskProcessDispatcherService::dispatchForTaskRun($this->taskRun);

                return;
            }
        }

        // Create null group resolution process if needed
        if (!$hasNullGroupResolution && $mergeProcess && isset($mergeProcess->meta['null_groups_needing_llm'])) {
            $nullGroupFiles = $mergeProcess->meta['null_groups_needing_llm'];

            if (!empty($nullGroupFiles)) {
                static::logDebug('Creating null group resolution process for ' . count($nullGroupFiles) . ' files');

                // Get the null group files' artifacts
                $nullFileIds       = array_column($nullGroupFiles, 'file_id');
                $nullFileArtifacts = $this->taskRun->inputArtifacts()
                    ->whereIn('artifacts.id', $nullFileIds)
                    ->get();

                // Create the resolution process
                $nullResolutionProcess = $this->taskRun->taskProcesses()->create([
                    'name'      => 'Resolve Null Group Files',
                    'operation' => self::OPERATION_NULL_GROUP_RESOLUTION,
                    'activity'  => 'Determining group assignment for files with no clear identifier',
                    'meta'      => [
                        'null_groups_needing_llm' => $nullGroupFiles,
                    ],
                    'is_ready'  => true,
                ]);

                // Attach null group files as input artifacts
                foreach ($nullFileArtifacts as $artifact) {
                    $nullResolutionProcess->inputArtifacts()->attach($artifact->id, ['category' => 'input']);
                }
                $nullResolutionProcess->updateRelationCounter('inputArtifacts');

                $this->taskRun->updateRelationCounter('taskProcesses');

                static::logDebug("Created null group resolution process: $nullResolutionProcess");

                // Dispatch the resolution process
                TaskProcessDispatcherService::dispatchForTaskRun($this->taskRun);

                return;
            }
        }

        // Check if a merge process already exists
        $hasMergeProcess = $this->taskRun->taskProcesses()
            ->where('operation', self::OPERATION_MERGE)
            ->exists();

        if ($hasMergeProcess) {
            static::logDebug('Merge process already exists or completed');

            return;
        }

        // Check if window processes exist and have completed
        $windowProcesses = $this->taskRun->taskProcesses()
            ->where('operation', self::OPERATION_COMPARISON_WINDOW)
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
            'name'      => 'Merge Window Results',
            'operation' => self::OPERATION_MERGE,
            'activity'  => 'Merging window comparison results into final groups',
            'meta'      => [],
            'is_ready'  => true,
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
                                'description' => 'Name of this group (e.g., "Section A", "Category 1", "Entity Name"). Use empty string "" if no clear identifier exists.',
                            ],
                            'description' => [
                                'type'        => 'string',
                                'description' => 'High-level description of the contents of this group',
                            ],
                            'files'       => [
                                'type'        => 'array',
                                'items'       => [
                                    'type'                 => 'object',
                                    'properties'           => [
                                        'page_number' => [
                                            'type'        => 'integer',
                                            'description' => 'Page number of the file in the original document',
                                        ],
                                        'confidence'  => [
                                            'type'        => 'integer',
                                            'minimum'     => 0,
                                            'maximum'     => 5,
                                            'description' => 'Confidence score (0-5) for this file assignment',
                                        ],
                                        'explanation' => [
                                            'type'        => 'string',
                                            'description' => 'Brief explanation for this assignment and confidence level',
                                        ],
                                    ],
                                    'required'             => ['page_number', 'confidence', 'explanation'],
                                    'additionalProperties' => false,
                                ],
                                'description' => 'Array of file assignments with confidence scores',
                            ],
                        ],
                        'required'             => ['name', 'description', 'files'],
                        'additionalProperties' => false,
                    ],
                    'description' => 'Groups of related files with confidence scores. Each page must appear in EXACTLY ONE group. If uncertain about placement, use a low confidence score (0-2) to trigger automatic resolution.',
                ],
            ],
            'required'             => ['groups'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Override setupAgentThread to use simplified artifact filtering.
     * Only sends page_number from metadata, along with the file itself.
     */
    public function setupAgentThread($artifacts = [], $contextArtifacts = []): AgentThread
    {
        // NEVER reuse an existing agent thread - each comparison window needs a fresh thread
        // with its own set of file messages. Reusing would result in missing file messages.
        $taskDefinition = $this->taskRun->taskDefinition;

        if (!$taskDefinition->agent) {
            throw new \Exception("Agent not found for TaskRun: $this->taskRun");
        }

        $this->activity("Setting up agent thread for: {$taskDefinition->agent->name}", 5);

        // Build the agent thread using the task-specific builder WITHOUT artifacts
        // We'll manually add artifacts in a simplified format
        $builder = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $this->taskRun);

        // Build the thread (this adds directives and prompts)
        $agentThread = $builder->build();

        // Manually add artifacts in a simplified format: just the page number in text
        // The image file itself is attached to the message
        foreach (collect($artifacts) as $artifact) {
            // Get page_number from the StoredFile model (NOT from artifact meta)
            // Each artifact should have ONE storedFile with a page_number
            $storedFile = $artifact->storedFiles ? $artifact->storedFiles->first() : null;
            $pageNumber = $storedFile?->page_number ?? null;

            // Get stored file IDs safely
            $fileIds = $artifact->storedFiles ? $artifact->storedFiles->pluck('id')->toArray() : [];

            if ($pageNumber !== null) {
                // Add message with just the page number - the file itself is already attached
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    "Page $pageNumber",
                    $fileIds
                );
            } else {
                // No page number, just attach the files without text
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    '',
                    $fileIds
                );
            }
        }

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

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        // Add file organization specific instructions as the LAST message in the thread
        // This must be the final message before the agent runs
        app(ThreadRepository::class)->addMessageToThread($agentThread,
            "You are comparing adjacent files to organize them into logical groups.\n" .
            "Each file represents a page or document section.\n" .
            "Group files that belong together based on their content and context.\n\n" .
            "For each group:\n" .
            "- 'name': A clear, descriptive name for the group (e.g., 'Section A', 'Category 1', 'Entity Name')\n" .
            "- 'description': A high-level summary of what the group contains\n" .
            "- 'files': Array of file objects with page_number, confidence, and explanation\n\n" .
            "GROUPING STRATEGY - PRIORITIZE CONTINUITY:\n" .
            "Pages are presented in sequential order. Follow these rules:\n" .
            "1. DEFAULT TO SAME GROUP: When a page has no clear grouping indicators, keep it with the PREVIOUS page's group\n" .
            "2. ONLY SPLIT when there is CLEAR EVIDENCE of a boundary\n" .
            "3. CONTINUATION PAGES: Multi-page documents, narratives, or related content should stay together\n" .
            "4. BLANK/SEPARATOR PAGES: These often belong to the FOLLOWING content, not the preceding content\n" .
            "5. AMBIGUOUS PAGES: When in doubt, assume continuity - use the same group as the previous page\n\n" .
            "Examples of CLEAR boundaries (split groups):\n" .
            "- Change in primary identifying header or logo at the top of the page\n" .
            "- Explicit labels indicating a new entity, section, or category\n" .
            "- Clear visual separators or end-of-section markers\n" .
            "- Change in document structure, format, or template\n" .
            "- Different identifying numbers, codes, or case references\n\n" .
            "Examples of CONTINUITY (same group) - DO NOT SPLIT:\n" .
            "- Page numbering (Page 2, 3, 4...) indicating a multi-page document\n" .
            "- Same header/logo with different sub-headers, locations, or sections\n" .
            "- Same primary identifier with additional secondary identifiers\n" .
            "- Narrative text or data continuing from previous page\n" .
            "- Forms or tables without distinct primary headers (assume continuation)\n" .
            "- Individual names or sub-categories appearing within a larger entity\n\n" .
            "IDENTIFICATION HIERARCHY:\n" .
            "When you see multiple potential identifiers on a page, prioritize:\n" .
            "1. PRIMARY HEADER/LOGO at the top of the page (most important)\n" .
            "2. EXPLICIT LABELS that clearly identify the entity (e.g., labeled fields, titles)\n" .
            "3. STRUCTURAL ELEMENTS that frame the content\n" .
            "Lower priority (often NOT the group identifier):\n" .
            "- Sub-headers, location names, or branch identifiers\n" .
            "- Individual names or secondary references\n" .
            "- Facility, department, or section labels within a larger entity\n\n" .
            "CONFIDENCE SCORING (0-5 scale):\n" .
            "- 5: Absolutely certain - clear evidence this file belongs in this group\n" .
            "- 4: Very confident - strong indicators support this grouping\n" .
            "- 3: Moderately confident - reasonable but not definitive (or continuation assumed)\n" .
            "- 2: Uncertain - could belong here or elsewhere\n" .
            "- 1: Very uncertain - minimal evidence for this grouping\n" .
            "- 0: Guessing - no clear indicators\n\n" .
            "CRITICAL RULES:\n" .
            "- Each page MUST appear in EXACTLY ONE group - NEVER place the same page in multiple groups\n" .
            "- PREFER CONTINUITY: When uncertain, default to the same group as the previous page\n" .
            "- If uncertain about placement, use a MODERATE confidence score (3) for continuity assumptions\n" .
            "- Only use LOW confidence (0-2) when genuinely conflicted between multiple different groups\n" .
            "- Only include page numbers that were provided in the input messages\n" .
            "- If a file should be ignored (e.g., completely blank page), simply don't include it in any group\n\n" .
            "WHEN NO CLEAR IDENTIFIER EXISTS:\n" .
            "- If you cannot find ANY clear identifier for a group, use an empty string \"\" for the name\n" .
            "- For files with no identifier, use confidence score 0 or 1 (minimum)\n" .
            "- Example: {\"name\": \"\", \"description\": \"No clear identifier found\", \"files\": [...]}\n" .
            "- An empty name explicitly signals that no valid grouping could be determined\n\n" .
            "Example file object:\n" .
            "{\n" .
            "  \"page_number\": 98,\n" .
            "  \"confidence\": 4,\n" .
            "  \"explanation\": \"Contains content consistent with the primary header shown in this group\"\n" .
            '}'
        );

        return $agentThread;
    }

    /**
     * Run low confidence resolution process.
     * Reviews files with uncertain grouping assignments and updates existing merged artifacts.
     */
    protected function runLowConfidenceResolution(): void
    {
        static::logDebug('Starting low confidence resolution');

        $lowConfidenceFiles = $this->taskProcess->meta['low_confidence_files'] ?? [];

        if (empty($lowConfidenceFiles)) {
            static::logDebug('No low-confidence files to resolve');
            $this->complete();

            return;
        }

        static::logDebug('Resolving ' . count($lowConfidenceFiles) . ' low-confidence files');

        // Setup agent thread with uncertain files and context
        $uncertainFileIds   = array_column($lowConfidenceFiles, 'file_id');
        $uncertainArtifacts = $this->taskProcess->inputArtifacts()
            ->whereIn('artifacts.id', $uncertainFileIds)
            ->get();

        $agentThread = $this->setupResolutionAgentThread($uncertainArtifacts, $lowConfidenceFiles);

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from resolution agent thread');
        }

        // Validate resolution has no duplicate pages
        $this->validateNoDuplicatePages($artifact->json_content);

        static::logDebug('Resolution completed successfully');

        // Apply resolution decisions to existing merged artifacts
        $this->applyResolutionToMergedArtifacts($artifact->json_content);

        // Delete the temporary resolution artifact - we don't need it as output
        $artifact->delete();

        $this->complete();
    }

    /**
     * Setup agent thread for low-confidence file resolution.
     * Provides all context from window comparisons to help agent make better decisions.
     */
    protected function setupResolutionAgentThread($artifacts, array $lowConfidenceFiles): AgentThread
    {
        $taskDefinition = $this->taskRun->taskDefinition;

        if (!$taskDefinition->agent) {
            throw new \Exception("Agent not found for TaskRun: $this->taskRun");
        }

        $this->activity("Setting up resolution agent thread for: {$taskDefinition->agent->name}", 5);

        // Build the agent thread
        $builder     = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $this->taskRun);
        $agentThread = $builder->build();

        // Add file messages
        foreach ($artifacts as $artifact) {
            $storedFile = $artifact->storedFiles ? $artifact->storedFiles->first() : null;
            $pageNumber = $storedFile?->page_number ?? null;
            $fileIds    = $artifact->storedFiles ? $artifact->storedFiles->pluck('id')->toArray() : [];

            if ($pageNumber !== null) {
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    "Page $pageNumber",
                    $fileIds
                );
            } else {
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    '',
                    $fileIds
                );
            }
        }

        // Build context message showing ALL explanations from all windows
        $contextMessage = "CONTEXT: Low-confidence file assignments requiring review\n\n";
        $contextMessage .= "These files were assigned with low confidence (< 3) during the windowed comparison process.\n";
        $contextMessage .= "Below are ALL explanations from ALL comparison windows for each file:\n\n";

        foreach ($lowConfidenceFiles as $fileData) {
            $pageNumber      = $fileData['page_number'];
            $bestAssignment  = $fileData['best_assignment'];
            $allExplanations = $fileData['all_explanations'];

            $contextMessage .= "--- Page $pageNumber ---\n";
            $contextMessage .= "Best assignment: '{$bestAssignment['group_name']}' (confidence: {$bestAssignment['confidence']})\n";
            $contextMessage .= "Description: {$bestAssignment['description']}\n\n";

            $contextMessage .= "All explanations from comparison windows:\n";
            foreach ($allExplanations as $idx => $explanation) {
                $num            = $idx + 1;
                $contextMessage .= "  $num. Group: '{$explanation['group_name']}' (confidence: {$explanation['confidence']})\n";
                $contextMessage .= "     Explanation: {$explanation['explanation']}\n";
            }
            $contextMessage .= "\n";
        }

        app(ThreadRepository::class)->addMessageToThread($agentThread, $contextMessage);

        // Use the same schema as window comparisons
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

        $this->taskDefinition->schema_definition_id = $schemaDefinition->id;
        $this->taskDefinition->save();

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        // Add resolution-specific instructions
        app(ThreadRepository::class)->addMessageToThread($agentThread,
            "TASK: Resolve uncertain file groupings\n\n" .
            "You have been provided with files that had CONFLICTING LOW CONFIDENCE assignments from multiple windows.\n" .
            "Above, you can see ALL explanations from ALL comparison windows that reviewed each file.\n\n" .
            "Your task:\n" .
            "1. Review each file carefully with the full context provided\n" .
            "2. Look at the sequential context - which group did pages BEFORE and AFTER belong to?\n" .
            "3. Make a FINAL DECISION on the correct group assignment\n" .
            "4. Assign a NEW confidence score (0-5) based on your review\n" .
            "5. Provide a detailed explanation for your decision\n\n" .
            "RESOLUTION STRATEGY - PREFER CONTINUITY:\n" .
            "- If a file appears between pages that belong to the SAME group, it likely belongs there too\n" .
            "- Only place a file in a DIFFERENT group if there's clear evidence of a document boundary\n" .
            "- When genuinely uncertain, default to the group that maintains sequential continuity\n" .
            "- Continuation pages (page 2, 3, 4 of a document) almost always stay with page 1\n\n" .
            "IMPORTANT:\n" .
            "- You can create NEW groups if none of the existing groups fit AND there's clear evidence\n" .
            "- Use ALL the context from previous windows to make informed decisions\n" .
            "- Consider the SEQUENCE: what came before and after this page?\n" .
            "- Aim for confidence >= 3 for all assignments\n" .
            "- If still uncertain after review, explain WHY in detail\n" .
            "- If NO clear identifier exists for a file, use empty string \"\" for name and confidence 0-1\n\n" .
            'Return your assignments using the same format as the comparison windows.'
        );

        return $agentThread;
    }

    /**
     * Validate that no page_number appears in multiple groups.
     * Each page must belong to exactly ONE group.
     *
     * @param  array  $jsonContent  The artifact's json_content with groups
     *
     * @throws ValidationError if any page appears in multiple groups
     */
    protected function validateNoDuplicatePages(array $jsonContent): void
    {
        $groups = $jsonContent['groups'] ?? [];

        if (empty($groups)) {
            return;
        }

        // Track which page_numbers we've seen and in which group
        $pageToGroup = [];

        foreach ($groups as $group) {
            $groupName = $group['name']  ?? 'Unknown';
            $files     = $group['files'] ?? [];

            foreach ($files as $fileData) {
                // Handle both old format (integer) and new format (object)
                if (is_int($fileData)) {
                    $pageNumber = $fileData;
                } else {
                    $pageNumber = $fileData['page_number'] ?? null;
                }

                if ($pageNumber === null) {
                    continue;
                }

                // Check if we've seen this page before
                if (isset($pageToGroup[$pageNumber])) {
                    $firstGroup = $pageToGroup[$pageNumber];
                    throw new ValidationError(
                        "Invalid file organization: Page $pageNumber appears in multiple groups.\n" .
                        "First group: '$firstGroup'\n" .
                        "Second group: '$groupName'\n\n" .
                        'Each page must belong to exactly ONE group. Please revise the grouping so that each page appears in only one group.',
                        400
                    );
                }

                // Record this page
                $pageToGroup[$pageNumber] = $groupName;
            }
        }

        static::logDebug('Validation passed: No duplicate pages found across ' . count($groups) . ' groups');
    }

    /**
     * Apply resolution decisions to existing merged artifacts.
     * Moves files between groups based on the agent's final decisions.
     *
     * @param  array  $resolutionContent  The resolution artifact's json_content with final group assignments
     */
    protected function applyResolutionToMergedArtifacts(array $resolutionContent): void
    {
        static::logDebug('Applying resolution decisions to merged artifacts');

        $resolutionGroups = $resolutionContent['groups'] ?? [];

        if (empty($resolutionGroups)) {
            static::logDebug('No resolution groups found');

            return;
        }

        // Build a map of file_id => group_name from resolution
        $fileToResolvedGroup = [];
        foreach ($resolutionGroups as $group) {
            $groupName = $group['name']  ?? null;
            $files     = $group['files'] ?? [];

            if (!$groupName) {
                continue;
            }

            foreach ($files as $fileData) {
                // Handle both old format (integer) and new format (object)
                if (is_int($fileData)) {
                    $pageNumber = $fileData;
                } else {
                    $pageNumber = $fileData['page_number'] ?? null;
                }

                if ($pageNumber === null) {
                    continue;
                }

                // Map page_number to file_id by looking at input artifacts
                foreach ($this->taskRun->inputArtifacts as $inputArtifact) {
                    $storedFile         = $inputArtifact->storedFiles ? $inputArtifact->storedFiles->first() : null;
                    $artifactPageNumber = $storedFile?->page_number ?? null;

                    if ($artifactPageNumber === $pageNumber) {
                        $fileToResolvedGroup[$inputArtifact->id] = $groupName;
                        static::logDebug("Resolution: Page $pageNumber (file {$inputArtifact->id}) -> '$groupName'");
                        break;
                    }
                }
            }
        }

        if (empty($fileToResolvedGroup)) {
            static::logDebug('No file resolutions to apply');

            return;
        }

        // Get existing merged artifacts from the task run output
        $mergedArtifacts = $this->taskRun->outputArtifacts()->get();

        if ($mergedArtifacts->isEmpty()) {
            static::logDebug('No merged artifacts found to update');

            return;
        }

        static::logDebug('Found ' . $mergedArtifacts->count() . ' merged artifacts to update');

        // For each resolved file, move it to the correct group
        foreach ($fileToResolvedGroup as $fileId => $targetGroupName) {
            static::logDebug("Processing file $fileId -> '$targetGroupName'");

            // Find which merged artifact currently contains this file
            $sourceArtifact = null;
            $targetArtifact = null;

            foreach ($mergedArtifacts as $artifact) {
                $groupName = $artifact->meta['group_name'] ?? null;

                // Check if this artifact's children include a copy of the input artifact
                // Children are copies that reference the original input artifact as their parent
                $hasFile = false;
                foreach ($artifact->children as $child) {
                    // Check if this child is a copy of the input artifact
                    // The child's parent should be the original input artifact
                    if ($child->parent_artifact_id == $fileId) {
                        $hasFile = true;
                        break;
                    }
                }

                if ($hasFile && $groupName !== $targetGroupName) {
                    $sourceArtifact = $artifact;
                    static::logDebug("  Found file in source group: '$groupName'");
                } elseif ($groupName === $targetGroupName) {
                    $targetArtifact = $artifact;
                    static::logDebug("  Found target group: '$targetGroupName'");
                }
            }

            // If file is already in the correct group, skip
            if (!$sourceArtifact && $targetArtifact) {
                static::logDebug('  File already in correct group, skipping');

                continue;
            }

            // If we found a source but no target, the agent wants to move to a NEW group
            if ($sourceArtifact && !$targetArtifact) {
                static::logDebug("  Creating new group: '$targetGroupName'");

                // Get the input artifact
                $inputArtifact = $this->taskRun->inputArtifacts()->where('artifacts.id', $fileId)->first();

                if (!$inputArtifact) {
                    static::logDebug("  ERROR: Could not find input artifact $fileId");

                    continue;
                }

                // Create a copy for the new group
                $artifactCopy = $inputArtifact->copy();

                // Create new merged artifact for this group
                $targetArtifact       = app(\App\Services\Task\ArtifactsMergeService::class)->merge([$artifactCopy]);
                $targetArtifact->name = "Group: $targetGroupName";
                $targetArtifact->meta = [
                    'group_name'  => $targetGroupName,
                    'description' => 'Group created during resolution',
                    'file_count'  => 1,
                ];
                $targetArtifact->save();

                // Add to task run outputs
                $this->taskRun->outputArtifacts()->attach($targetArtifact->id, ['category' => 'output']);

                static::logDebug("  Created new merged artifact: $targetArtifact");
            }

            // Move the file from source to target
            if ($sourceArtifact && $targetArtifact) {
                static::logDebug("  Moving file from '{$sourceArtifact->meta['group_name']}' to '$targetGroupName'");

                // Get the child artifact (copy) from source
                // Find the child whose parent is the input artifact
                $childArtifact = null;
                foreach ($sourceArtifact->children as $child) {
                    if ($child->parent_artifact_id == $fileId) {
                        $childArtifact = $child;
                        break;
                    }
                }

                if (!$childArtifact) {
                    static::logDebug('  ERROR: Could not find child artifact in source');

                    continue;
                }

                // Remove from source artifact's children
                $sourceArtifact->children()->detach($childArtifact->id);
                $sourceArtifact->updateRelationCounter('children');

                // Update source artifact meta
                $sourceArtifact->meta = array_merge($sourceArtifact->meta ?? [], [
                    'file_count' => $sourceArtifact->children()->count(),
                ]);
                $sourceArtifact->save();

                // If source artifact now has no children, delete it
                if ($sourceArtifact->children()->count() === 0) {
                    static::logDebug("  Source group '{$sourceArtifact->meta['group_name']}' now empty - removing from outputs");
                    $this->taskRun->outputArtifacts()->detach($sourceArtifact->id);
                    $sourceArtifact->delete();
                }

                // Add to target artifact's children
                $targetArtifact->children()->attach($childArtifact->id);
                $targetArtifact->updateRelationCounter('children');

                // Update target artifact meta
                $targetArtifact->meta = array_merge($targetArtifact->meta ?? [], [
                    'file_count' => $targetArtifact->children()->count(),
                ]);
                $targetArtifact->save();

                static::logDebug('  Successfully moved file');
            }
        }

        static::logDebug('Resolution application completed');
    }

    /**
     * Run null group resolution process.
     * These are files where no clear identifier was found, but they're between two different groups.
     * Ask the LLM to decide which adjacent group they should belong to.
     */
    protected function runNullGroupResolution(): void
    {
        static::logDebug('Starting null group resolution');

        $nullGroupFiles = $this->taskProcess->meta['null_groups_needing_llm'] ?? [];

        if (empty($nullGroupFiles)) {
            static::logDebug('No null group files to resolve');
            $this->complete();

            return;
        }

        static::logDebug('Resolving ' . count($nullGroupFiles) . ' null group files');

        // Setup agent thread with null group files
        $nullFileIds       = array_column($nullGroupFiles, 'file_id');
        $nullFileArtifacts = $this->taskProcess->inputArtifacts()
            ->whereIn('artifacts.id', $nullFileIds)
            ->get();

        $agentThread = $this->setupNullGroupResolutionAgentThread($nullFileArtifacts, $nullGroupFiles);

        // Run the agent thread
        $artifact = $this->runAgentThread($agentThread);

        if (!$artifact || !$artifact->json_content) {
            throw new ValidationError(static::class . ': No JSON content returned from null group resolution agent thread');
        }

        // Validate resolution has no duplicate pages
        $this->validateNoDuplicatePages($artifact->json_content);

        static::logDebug('Null group resolution completed successfully');

        // Apply resolution decisions to existing merged artifacts
        $this->applyResolutionToMergedArtifacts($artifact->json_content);

        // Delete the temporary resolution artifact - we don't need it as output
        $artifact->delete();

        $this->complete();
    }

    /**
     * Setup agent thread for null group resolution.
     * Provides context about adjacent groups to help agent decide assignment.
     */
    protected function setupNullGroupResolutionAgentThread($artifacts, array $nullGroupFiles): AgentThread
    {
        $taskDefinition = $this->taskRun->taskDefinition;

        if (!$taskDefinition->agent) {
            throw new \Exception("Agent not found for TaskRun: $this->taskRun");
        }

        $this->activity("Setting up null group resolution agent thread for: {$taskDefinition->agent->name}", 5);

        // Build the agent thread
        $builder     = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $this->taskRun);
        $agentThread = $builder->build();

        // Add file messages
        foreach ($artifacts as $artifact) {
            $storedFile = $artifact->storedFiles ? $artifact->storedFiles->first() : null;
            $pageNumber = $storedFile?->page_number ?? null;
            $fileIds    = $artifact->storedFiles ? $artifact->storedFiles->pluck('id')->toArray() : [];

            if ($pageNumber !== null) {
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    "Page $pageNumber",
                    $fileIds
                );
            } else {
                app(ThreadRepository::class)->addMessageToThread(
                    $agentThread,
                    '',
                    $fileIds
                );
            }
        }

        // Build context message explaining the situation
        $contextMessage = "CONTEXT: Files with no clear identifier that need group assignment\n\n";
        $contextMessage .= "These files had no clear identifying header or label during comparison.\n";
        $contextMessage .= "Each file is positioned between two different groups.\n";
        $contextMessage .= "Your task is to decide which adjacent group each file should belong to.\n\n";

        foreach ($nullGroupFiles as $fileData) {
            $pageNumber    = $fileData['page_number'];
            $previousGroup = $fileData['previous_group'];
            $nextGroup     = $fileData['next_group'];
            $description   = $fileData['description'] ?? 'No identifier found';

            $contextMessage .= "--- Page $pageNumber ---\n";
            $contextMessage .= "Observation: $description\n";
            $contextMessage .= "Previous group (pages before): '$previousGroup'\n";
            $contextMessage .= "Next group (pages after): '$nextGroup'\n";
            $contextMessage .= "Decision needed: Should this page belong to '$previousGroup' or '$nextGroup'?\n\n";
        }

        app(ThreadRepository::class)->addMessageToThread($agentThread, $contextMessage);

        // Use the same schema as window comparisons
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

        $this->taskDefinition->schema_definition_id = $schemaDefinition->id;
        $this->taskDefinition->save();

        $this->taskProcess->agentThread()->associate($agentThread)->save();

        // Add null group resolution instructions
        app(ThreadRepository::class)->addMessageToThread($agentThread,
            "TASK: Assign files with no clear identifier to the correct adjacent group\n\n" .
            "You have been provided with files that had NO CLEAR IDENTIFIER (empty string name) during windowing.\n" .
            "Each file is positioned between two groups - a previous group and a next group.\n\n" .
            "Your task:\n" .
            "1. Review each file carefully\n" .
            "2. Look at the content, layout, and visual characteristics\n" .
            "3. Compare with the context of the previous and next groups\n" .
            "4. Decide whether the file should belong to the PREVIOUS or NEXT group\n" .
            "5. Assign confidence score 1-5 based on your decision certainty\n" .
            "6. Provide explanation for your choice\n\n" .
            "DECISION STRATEGY:\n" .
            "- Consider: Does this page's content match the previous group or next group?\n" .
            "- Consider: Is this a continuation page (page 2, 3, etc.) that follows the previous group?\n" .
            "- Consider: Does this page show signs of starting something new (matches next group)?\n" .
            "- When truly ambiguous, prefer assigning to the PREVIOUS group (maintains continuity)\n\n" .
            "IMPORTANT:\n" .
            "- You MUST assign each file to either the previous group or the next group\n" .
            "- Use the exact group name provided in the context (don't create new names)\n" .
            "- If genuinely uncertain, use confidence 1-2 and default to previous group\n" .
            "- Provide clear explanation for your decision\n"
        );

        return $agentThread;
    }
}
