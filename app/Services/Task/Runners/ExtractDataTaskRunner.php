<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\DataExtraction\ClassificationExecutorService;
use App\Services\Task\DataExtraction\ClassificationSchemaBuilder;
use App\Services\Task\DataExtraction\ExtractionPlanningService;
use App\Services\Task\DataExtraction\ExtractionProcessOrchestrator;
use App\Services\Task\DataExtraction\GroupExtractionService;
use App\Services\Task\DataExtraction\ObjectResolutionService;
use App\Services\Task\DataExtraction\ObjectTypeExtractor;
use App\Services\Task\DataExtraction\PerObjectPlanningService;
use Exception;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

class ExtractDataTaskRunner extends AgentThreadTaskRunner
{
    public const string RUNNER_NAME = 'Extract Data';

    public const string OPERATION_PLAN_IDENTIFY = 'Plan: Identify',
        OPERATION_PLAN_REMAINING                = 'Plan: Remaining',
        OPERATION_CLASSIFY                      = 'Classify',
        OPERATION_RESOLVE_OBJECTS               = 'Resolve Objects',
        OPERATION_EXTRACT_GROUP                 = 'Extract Group';

    /**
     * Get the task runner name.
     */
    public static function name(): string
    {
        return self::RUNNER_NAME;
    }

    /**
     * Get the task runner slug.
     */
    public static function slug(): string
    {
        return 'extract-data';
    }

    /**
     * Get the task runner description.
     */
    public static function description(): string
    {
        return 'Extracts structured data from documents by first resolving object identities, then extracting additional data points for those objects. Operates on grouped/classified artifacts.';
    }

    /**
     * Prepare the task run.
     * Validates schema definition exists.
     */
    public function prepareRun(): void
    {
        parent::prepareRun();

        static::logDebug('Preparing extract data task run');

        // Validate schema definition exists
        $taskDefinition = $this->taskRun->taskDefinition;
        if (!$taskDefinition->schemaDefinition) {
            throw new ValidationError(
                'ExtractDataTaskRunner requires a Schema Definition. Please configure the output schema before running this task.'
            );
        }
    }

    /**
     * Route to appropriate handler based on operation type.
     */
    public function run(): void
    {
        static::logDebug("Running extract data operation: {$this->taskProcess->operation}");

        match ($this->taskProcess->operation) {
            self::OPERATION_PLAN_IDENTIFY   => $this->runPlanIdentifyOperation(),
            self::OPERATION_PLAN_REMAINING  => $this->runPlanRemainingOperation(),
            self::OPERATION_CLASSIFY        => $this->runClassificationOperation(),
            self::OPERATION_RESOLVE_OBJECTS => $this->runResolveObjectsOperation(),
            self::OPERATION_EXTRACT_GROUP   => $this->runExtractGroupOperation(),
            default                         => $this->runInitializeOperation()
        };
    }

    /**
     * Run the initialize operation (Default Task handler).
     * This is the first process created by TaskRunnerService.
     * Checks for cached plan and creates either per-object planning or per-page classification processes.
     */
    protected function runInitializeOperation(): void
    {
        static::logDebug('Running initialize operation (Default Task)');

        // Check for cached plan
        $planningService = app(ExtractionPlanningService::class);
        $cachedPlan      = $planningService->getCachedPlan($this->taskRun->taskDefinition);

        if ($cachedPlan) {
            // Plan exists - create per-page classification processes
            static::logDebug('Cached plan found - creating per-page classification processes');

            // Resolve all pages from input artifacts
            $pages = $this->resolvePages();

            if (empty($pages)) {
                static::logDebug('No pages found to classify');
                $this->complete();

                return;
            }

            // Build and store boolean classification schema
            $schemaBuilder = app(ClassificationSchemaBuilder::class);
            $booleanSchema = $schemaBuilder->buildBooleanSchema($cachedPlan);
            $this->storeClassificationSchema($booleanSchema);

            // Create per-page classification processes
            $orchestrator = app(ExtractionProcessOrchestrator::class);
            $orchestrator->createClassifyProcessesPerPage($this->taskRun, $pages);

            static::logDebug('Created per-page classification processes', ['pages_count' => count($pages)]);
        } else {
            // No plan exists - create per-object planning processes
            static::logDebug('No cached plan found - creating identity planning processes');

            $objectExtractor = app(ObjectTypeExtractor::class);
            $objectTypes     = $objectExtractor->extractObjectTypes(
                $this->taskRun->taskDefinition->schemaDefinition
            );

            $planningService = app(PerObjectPlanningService::class);
            $planningService->createIdentityPlanningProcesses($this->taskRun, $objectTypes);

            static::logDebug('Created identity planning processes', ['count' => count($objectTypes)]);
        }

        $this->taskRun->updateRelationCounter('taskProcesses');
        $this->complete();
    }

    /**
     * Run identity planning for a single object type.
     */
    protected function runPlanIdentifyOperation(): void
    {
        static::logDebug('Running Plan: Identify operation', [
            'object_type' => $this->taskProcess->meta['object_type'] ?? 'unknown',
        ]);

        $planningService = app(PerObjectPlanningService::class);
        $planningService->executeIdentityPlanning($this->taskRun, $this->taskProcess);

        $this->complete();
    }

    /**
     * Run remaining field grouping for a single object type.
     */
    protected function runPlanRemainingOperation(): void
    {
        static::logDebug('Running Plan: Remaining operation', [
            'object_type' => $this->taskProcess->meta['object_type'] ?? 'unknown',
        ]);

        $planningService = app(PerObjectPlanningService::class);
        $planningService->executeRemainingPlanning($this->taskRun, $this->taskProcess);

        $this->complete();
    }

    /**
     * Run the classification operation.
     * Classifies a single page using the boolean classification schema.
     */
    protected function runClassificationOperation(): void
    {
        static::logDebug('Running classification operation for single page');

        // Get page info from TaskProcess meta
        $artifactId = $this->taskProcess->meta['artifact_id'] ?? null;
        $fileId     = $this->taskProcess->meta['file_id']     ?? null;
        $pageNumber = $this->taskProcess->meta['page_number'] ?? null;

        if (!$artifactId || !$fileId) {
            throw new ValidationError('Missing artifact_id or file_id in TaskProcess meta');
        }

        // Cast to int (meta returns strings)
        $artifactId = (int)$artifactId;
        $fileId     = (int)$fileId;

        static::logDebug('Processing page classification', [
            'artifact_id' => $artifactId,
            'file_id'     => $fileId,
            'page_number' => $pageNumber,
        ]);

        // Load the artifact
        $artifact = Artifact::find($artifactId);

        if (!$artifact) {
            throw new ValidationError("Artifact not found: $artifactId");
        }

        // Get the boolean classification schema from TaskRun meta
        $booleanSchema = $this->getClassificationSchema();

        if (!$booleanSchema) {
            throw new ValidationError('No classification schema found in TaskRun meta');
        }

        // Run classification on this page
        $classificationService = app(ClassificationExecutorService::class);
        $classificationResult  = $classificationService->classifyPage(
            $this->taskRun,
            $this->taskProcess,
            $booleanSchema,
            $artifact,
            $fileId
        );

        static::logDebug('Classification completed for page', [
            'artifact_id' => $artifactId,
            'page_number' => $pageNumber,
            'result'      => $classificationResult,
        ]);

        // Create output artifact with classification result
        $outputArtifact = Artifact::create([
            'name'            => "Classification: Page $pageNumber",
            'task_process_id' => $this->taskProcess->id,
            'task_run_id'     => $this->taskRun->id,
            'meta'            => [
                'classification' => $classificationResult,
                'page_number'    => $pageNumber,
                'artifact_id'    => $artifactId,
                'file_id'        => $fileId,
            ],
        ]);

        static::logDebug('Created output artifact with classification', [
            'output_artifact_id' => $outputArtifact->id,
        ]);

        $this->complete([$outputArtifact]);
    }

    /**
     * Run the resolve objects operation.
     * Resolves/creates TeamObjects from artifacts.
     * This runs FIRST at each level before extracting additional data.
     */
    protected function runResolveObjectsOperation(): void
    {
        $level = $this->taskProcess->meta['level'] ?? 0;
        $plan  = $this->getExtractionPlan();

        static::logDebug('Running resolve objects operation', [
            'level' => $level,
        ]);

        $resolutionService = app(ObjectResolutionService::class);

        // Check for existing object reference first
        $resolutionService->verifyExistingObjectIfProvided(
            $this->taskRun,
            $this->taskProcess,
            $plan,
            $level
        );

        // Get identification groups at this level
        $identificationGroups = $resolutionService->getIdentificationGroupsAtLevel($plan, $level);

        if (empty($identificationGroups)) {
            static::logDebug('No identification groups at this level', ['level' => $level]);
            $this->updateLevelProgress($level, 'resolution_complete', true);
            $this->complete();

            return;
        }

        // Resolve objects for each identification group
        foreach ($identificationGroups as $groupIndex => $group) {
            static::logDebug('Resolving objects for group', [
                'level'       => $level,
                'group_index' => $groupIndex,
                'group_name'  => $group['name'] ?? "Group $groupIndex",
            ]);

            $resolutionService->resolveObjectsForGroup(
                $this->taskRun,
                $this->taskProcess,
                $group,
                $level
            );
        }

        // Mark resolution complete
        $this->updateLevelProgress($level, 'resolution_complete', true);

        static::logDebug('Object resolution completed for level', ['level' => $level]);

        $this->complete();
    }

    /**
     * Run the extract group operation.
     * Extracts additional data points for resolved objects.
     */
    protected function runExtractGroupOperation(): void
    {
        $group      = $this->taskProcess->meta['extraction_group'];
        $level      = $this->taskProcess->meta['level']       ?? 0;
        $objectId   = $this->taskProcess->meta['object_id'];
        $searchMode = $this->taskProcess->meta['search_mode'] ?? 'exhaustive';

        static::logDebug('Running extract group operation', [
            'level'       => $level,
            'object_id'   => $objectId,
            'search_mode' => $searchMode,
            'group_name'  => $group['name'] ?? 'Unknown',
        ]);

        $extractionService = app(GroupExtractionService::class);

        // Get classified artifacts for this group
        $artifacts = $extractionService->getClassifiedArtifactsForGroup($this->taskRun, $group);

        if ($artifacts->isEmpty()) {
            static::logDebug('No classified artifacts for group', ['group' => $group['name'] ?? 'Unknown']);
            $this->complete();

            return;
        }

        // Load resolved TeamObject
        $teamObject = TeamObject::find($objectId);

        if (!$teamObject) {
            throw new Exception("Resolved TeamObject not found: $objectId");
        }

        // Extract based on search mode
        if ($searchMode === 'skim') {
            $extractedData = $extractionService->extractWithSkimMode(
                $this->taskRun,
                $this->taskProcess,
                $group,
                $artifacts,
                $teamObject
            );
        } else {
            $extractedData = $extractionService->extractExhaustive(
                $this->taskRun,
                $this->taskProcess,
                $group,
                $artifacts,
                $teamObject
            );
        }

        // Update TeamObject with extracted data
        if (!empty($extractedData)) {
            $extractionService->updateTeamObjectWithExtractedData($this->taskRun, $teamObject, $extractedData, $group);
        }

        static::logDebug('Extract group operation completed', [
            'level'      => $level,
            'group_name' => $group['name'] ?? 'Unknown',
        ]);

        $this->complete();
    }

    /**
     * Called after all parallel processes have completed.
     * Creates next phase of processes based on current state.
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();

        static::logDebug('All processes completed - checking next phase');

        $perObjectService = app(PerObjectPlanningService::class);
        $orchestrator     = app(ExtractionProcessOrchestrator::class);

        // Check if identity planning phase just completed
        $identifyProcesses = $this->taskRun->taskProcesses()
            ->where('operation', self::OPERATION_PLAN_IDENTIFY)
            ->get();

        if ($identifyProcesses->isNotEmpty()) {
            $allIdentifyComplete = $identifyProcesses->every(fn($p) => $p->completed_at !== null);

            if ($allIdentifyComplete) {
                // Check if we need remaining processes
                $remainingProcesses = $this->taskRun->taskProcesses()
                    ->where('operation', self::OPERATION_PLAN_REMAINING)
                    ->get();

                if ($remainingProcesses->isEmpty()) {
                    // Create remaining processes if needed
                    $createdProcesses = $perObjectService->createRemainingProcesses($this->taskRun);

                    if (empty($createdProcesses)) {
                        // No remaining processes needed - compile plan and create per-page classification
                        static::logDebug('No remaining processes needed - compiling final plan');
                        $finalPlan = $perObjectService->compileFinalPlan($this->taskRun);
                        app(ExtractionPlanningService::class)->cachePlan(
                            $this->taskRun->taskDefinition,
                            $finalPlan
                        );

                        // Resolve all pages from input artifacts
                        $pages = $this->resolvePages();

                        if (!empty($pages)) {
                            // Build and store boolean classification schema
                            $schemaBuilder = app(ClassificationSchemaBuilder::class);
                            $booleanSchema = $schemaBuilder->buildBooleanSchema($finalPlan);
                            $this->storeClassificationSchema($booleanSchema);

                            // Create per-page classification processes
                            $orchestrator->createClassifyProcessesPerPage($this->taskRun, $pages);
                            static::logDebug('Created per-page classification processes', ['pages_count' => count($pages)]);
                        } else {
                            static::logDebug('No pages found to classify');
                        }
                    }

                    return;
                }

                // Check if all remaining processes are complete
                $allRemainingComplete = $remainingProcesses->every(fn($p) => $p->completed_at !== null);

                if ($allRemainingComplete) {
                    // All planning done - compile and create per-page classification
                    static::logDebug('All planning complete - compiling final plan');
                    $finalPlan = $perObjectService->compileFinalPlan($this->taskRun);
                    app(ExtractionPlanningService::class)->cachePlan(
                        $this->taskRun->taskDefinition,
                        $finalPlan
                    );

                    // Resolve all pages from input artifacts
                    $pages = $this->resolvePages();

                    if (!empty($pages)) {
                        // Build and store boolean classification schema
                        $schemaBuilder = app(ClassificationSchemaBuilder::class);
                        $booleanSchema = $schemaBuilder->buildBooleanSchema($finalPlan);
                        $this->storeClassificationSchema($booleanSchema);

                        // Create per-page classification processes
                        $orchestrator->createClassifyProcessesPerPage($this->taskRun, $pages);
                        static::logDebug('Created per-page classification processes', ['pages_count' => count($pages)]);
                    } else {
                        static::logDebug('No pages found to classify');
                    }

                    return;
                }
            }

            return;
        }

        $plan = $this->getExtractionPlan();

        if (empty($plan)) {
            static::logDebug('WARNING: No plan available');

            return;
        }

        // Check if classification just completed
        $hasClassificationProcess = $this->taskRun->taskProcesses()
            ->where('operation', self::OPERATION_CLASSIFY)
            ->exists();

        if ($hasClassificationProcess) {
            // Use orchestrator method to check if ALL classify processes are complete
            $classificationCompleted = $orchestrator->isClassificationComplete($this->taskRun);

            if ($classificationCompleted) {
                static::logDebug('All classification processes completed - ready to create resolve objects process');
                // Check if we've already started level 0 resolution
                $hasResolveProcess = $this->taskRun->taskProcesses()
                    ->where('operation', self::OPERATION_RESOLVE_OBJECTS)
                    ->exists();

                if (!$hasResolveProcess) {
                    static::logDebug('Classification completed - creating resolve objects process for level 0');
                    $orchestrator->createResolveObjectsProcess($this->taskRun, $plan, 0);

                    return;
                }
            }
        }

        $currentLevel  = $orchestrator->getCurrentLevel($this->taskRun);
        $progress      = $orchestrator->getLevelProgress($this->taskRun);
        $levelProgress = $progress[$currentLevel] ?? [];

        // Check what phase just completed and create next processes
        if (!($levelProgress['resolution_complete'] ?? false)) {
            static::logDebug('No phase completed yet at this level', ['level' => $currentLevel]);

            return;
        }

        if (!($levelProgress['extraction_complete'] ?? false)) {
            static::logDebug('Resolution completed - creating extract group processes', ['level' => $currentLevel]);
            $processes = $orchestrator->createExtractGroupProcesses($this->taskRun, $plan, $currentLevel);

            // If no extraction processes were created, mark extraction as complete
            if (empty($processes)) {
                static::logDebug('No extract group processes needed - marking extraction complete', ['level' => $currentLevel]);
                $this->updateLevelProgress($currentLevel, 'extraction_complete', true);
            }

            return;
        }

        // All phases complete for this level - mark extraction complete if not already
        $this->updateLevelProgress($currentLevel, 'extraction_complete', true);

        // Check if current level is fully complete
        if (!$orchestrator->isLevelComplete($this->taskRun, $currentLevel)) {
            static::logDebug('Current level not complete yet', ['level' => $currentLevel]);

            return;
        }

        // Check if all levels are complete
        if ($orchestrator->isAllLevelsComplete($this->taskRun, $plan)) {
            static::logDebug('All levels complete - extraction finished');

            return; // TaskRun will complete naturally
        }

        // Advance to next level
        if ($orchestrator->advanceToNextLevel($this->taskRun)) {
            $nextLevel = $orchestrator->getCurrentLevel($this->taskRun);
            static::logDebug("Advancing to level $nextLevel - creating resolve objects process");

            // Create resolve objects process for next level (classification was already done once)
            $orchestrator->createResolveObjectsProcess($this->taskRun, $plan, $nextLevel);
        }
    }

    /**
     * Get the extraction plan from TaskDefinition.meta only.
     */
    protected function getExtractionPlan(): array
    {
        // Check for cached plan in TaskDefinition.meta
        $taskDefinition = $this->taskRun->taskDefinition;
        $cachedPlan     = $taskDefinition->meta['extraction_plan'] ?? null;

        if ($cachedPlan) {
            static::logDebug('Using cached plan from TaskDefinition');

            return $cachedPlan;
        }

        static::logDebug('No extraction plan found in TaskDefinition');

        return [];
    }

    /**
     * Get source artifacts (non-JSON artifacts) from TaskRun.
     */
    protected function getSourceArtifacts(): Collection
    {
        return $this->taskRun->inputArtifacts()
            ->whereDoesntHave('storedFiles', fn($query) => $query->where('mime', 'application/json'))
            ->get();
    }

    /**
     * Update level progress in TaskRun.meta.
     */
    protected function updateLevelProgress(int $level, string $key, bool $value): void
    {
        app(ExtractionProcessOrchestrator::class)->updateLevelProgress(
            $this->taskRun,
            $level,
            $key,
            $value
        );
    }

    /**
     * Resolve all pages from input artifacts.
     * Returns array of page data with artifact_id, file_id, and page_number.
     */
    protected function resolvePages(): array
    {
        static::logDebug('Resolving pages from input artifacts');

        $artifacts = $this->getSourceArtifacts();
        $pages     = [];

        foreach ($artifacts as $artifact) {
            foreach ($artifact->storedFiles as $file) {
                $pageNumber = $file->page_number ?? $file->position ?? 1;

                $pages[] = [
                    'artifact_id' => $artifact->id,
                    'file_id'     => $file->id,
                    'page_number' => $pageNumber,
                ];

                static::logDebug('Resolved page', [
                    'artifact_id' => $artifact->id,
                    'file_id'     => $file->id,
                    'page_number' => $pageNumber,
                ]);
            }
        }

        static::logDebug('Resolved pages', ['pages_count' => count($pages)]);

        return $pages;
    }

    /**
     * Store classification schema in TaskRun.meta.
     */
    protected function storeClassificationSchema(array $schema): void
    {
        $meta                          = $this->taskRun->meta ?? [];
        $meta['classification_schema'] = $schema;
        $this->taskRun->meta           = $meta;
        $this->taskRun->save();

        static::logDebug('Stored classification schema', [
            'properties_count' => count($schema['properties'] ?? []),
        ]);
    }

    /**
     * Get classification schema from TaskRun.meta.
     */
    protected function getClassificationSchema(): ?array
    {
        return $this->taskRun->meta['classification_schema'] ?? null;
    }
}
