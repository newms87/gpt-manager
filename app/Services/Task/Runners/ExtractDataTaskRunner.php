<?php

namespace App\Services\Task\Runners;

use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use App\Services\JsonSchema\JsonSchemaService;
use App\Services\Task\DataExtraction\ArtifactPreparationService;
use App\Services\Task\DataExtraction\ClassificationExecutorService;
use App\Services\Task\DataExtraction\ClassificationOrchestrator;
use App\Services\Task\DataExtraction\ClassificationSchemaBuilder;
use App\Services\Task\DataExtraction\DuplicateRecordResolver;
use App\Services\Task\DataExtraction\ExtractionPhaseService;
use App\Services\Task\DataExtraction\ExtractionPlanningService;
use App\Services\Task\DataExtraction\ExtractionProcessOrchestrator;
use App\Services\Task\DataExtraction\GroupExtractionService;
use App\Services\Task\DataExtraction\ObjectTypeExtractor;
use App\Services\Task\DataExtraction\PerObjectPlanningService;
use App\Services\Task\DataExtraction\PlanningPhaseService;
use Exception;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

class ExtractDataTaskRunner extends AgentThreadTaskRunner
{
    public const string RUNNER_NAME = 'Extract Data';

    public const string OPERATION_PLAN_IDENTIFY = 'Plan: Identify',
        OPERATION_PLAN_REMAINING                = 'Plan: Remaining',
        OPERATION_CLASSIFY                      = 'Classify',
        OPERATION_EXTRACT_IDENTITY              = 'Extract Identity',
        OPERATION_EXTRACT_REMAINING             = 'Extract Remaining';

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
            self::OPERATION_PLAN_IDENTIFY     => $this->runPlanIdentifyOperation(),
            self::OPERATION_PLAN_REMAINING    => $this->runPlanRemainingOperation(),
            self::OPERATION_CLASSIFY          => $this->runClassificationOperation(),
            self::OPERATION_EXTRACT_IDENTITY  => $this->runExtractIdentityOperation(),
            self::OPERATION_EXTRACT_REMAINING => $this->runExtractRemainingOperation(),
            default                           => $this->runInitializeOperation()
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
            $artifactService = app(ArtifactPreparationService::class);
            $pages           = $artifactService->resolvePages($this->taskRun);

            if (empty($pages)) {
                static::logDebug('No pages found to classify');
                $this->complete();

                return;
            }

            // Build and store boolean classification schema
            $schemaBuilder = app(ClassificationSchemaBuilder::class);
            $booleanSchema = $schemaBuilder->buildBooleanSchema($cachedPlan);

            // Store classification schema in TaskRun.meta
            $meta                          = $this->taskRun->meta ?? [];
            $meta['classification_schema'] = $booleanSchema;
            $this->taskRun->meta           = $meta;
            $this->taskRun->save();

            static::logDebug('Stored classification schema', [
                'properties_count' => count($booleanSchema['properties'] ?? []),
            ]);

            // Create extraction artifacts (parent + children)
            $parentArtifact = $artifactService->createExtractionArtifacts($this->taskRun, $pages);

            // Create per-page classification processes with child artifacts
            $classificationOrchestrator = app(ClassificationOrchestrator::class);
            $classificationOrchestrator->createClassifyProcessesPerPage($this->taskRun, $parentArtifact->children);

            static::logDebug('Created per-page classification processes', ['pages_count' => $parentArtifact->children->count()]);

            // Manually attach parent artifact to TaskProcess and TaskRun
            // (Don't use complete() which would trim children based on output_artifact_levels)
            $this->taskProcess->outputArtifacts()->sync([$parentArtifact->id]);
            $this->taskProcess->updateRelationCounter('outputArtifacts');
            $this->taskRun->outputArtifacts()->syncWithoutDetaching([$parentArtifact->id]);
            $this->taskRun->updateRelationCounter('outputArtifacts');
            $this->taskRun->updateRelationCounter('taskProcesses');
            $this->complete();
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

            $this->taskRun->updateRelationCounter('taskProcesses');
            $this->complete();
        }
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

        // Get child artifact from TaskProcess inputArtifacts
        $childArtifact = $this->taskProcess->inputArtifacts->first();

        if (!$childArtifact) {
            throw new ValidationError('No input artifact found for classification process');
        }

        static::logDebug('Processing page classification', [
            'artifact_id' => $childArtifact->id,
            'page_number' => $childArtifact->position,
        ]);

        // Get the boolean classification schema from TaskRun meta
        $booleanSchema = $this->taskRun->meta['classification_schema'] ?? null;

        if (!$booleanSchema) {
            throw new ValidationError('No classification schema found in TaskRun meta');
        }

        // Run classification on this page
        $classificationService = app(ClassificationExecutorService::class);
        $classificationResult  = $classificationService->classifyPage(
            $this->taskRun,
            $this->taskProcess,
            $booleanSchema,
            $childArtifact
        );

        static::logDebug('Classification completed for page', [
            'artifact_id' => $childArtifact->id,
            'page_number' => $childArtifact->position,
            'result'      => $classificationResult,
        ]);

        // Store classification result in child artifact meta
        $meta                   = $childArtifact->meta ?? [];
        $meta['classification'] = $classificationResult;
        $childArtifact->meta    = $meta;
        $childArtifact->save();

        static::logDebug('Stored classification result in artifact meta');

        $this->complete();
    }

    /**
     * Run the extract identity operation.
     * Extracts identity fields and resolves/creates TeamObjects.
     */
    protected function runExtractIdentityOperation(): void
    {
        $group          = $this->taskProcess->meta['identity_group'];
        $level          = $this->taskProcess->meta['level'];
        $parentObjectId = $this->taskProcess->meta['parent_object_id'] ?? null;

        // Get artifacts from process input (already filtered at creation time)
        $artifacts = $this->taskProcess->inputArtifacts;

        if ($artifacts->isEmpty()) {
            static::logDebug('No input artifacts for identity extraction', [
                'process_id' => $this->taskProcess->id,
                'group'      => $group['name'] ?? 'unknown',
            ]);
            $this->complete();

            return;
        }

        static::logDebug('Running Extract Identity with pre-filtered artifacts', [
            'level'          => $level,
            'group'          => $group['name'] ?? 'unknown',
            'artifact_count' => $artifacts->count(),
        ]);

        // LLM Call #1: Extract identity fields + search query
        $extractionResult = $this->extractIdentityWithSearchQuery($artifacts, $group);

        if (empty($extractionResult)) {
            static::logDebug('No identity data extracted');
            $this->complete();

            return;
        }

        // Use DuplicateRecordResolver to find candidates
        $resolver   = app(DuplicateRecordResolver::class);
        $candidates = $resolver->findCandidates(
            $group['object_type'],
            $extractionResult['search_query'] ?? $extractionResult['data'] ?? [],
            $parentObjectId,
            $this->taskRun->taskDefinition->schema_definition_id
        );

        $matchId = null;
        if ($candidates->isNotEmpty()) {
            // Try quick exact-match first (optimization - avoids LLM Call #2)
            $quickMatch = $resolver->quickMatchCheck($extractionResult['data'] ?? [], $candidates);
            if ($quickMatch) {
                $matchId = $quickMatch->id;
                static::logDebug('Quick exact match found', ['object_id' => $matchId]);
            } else {
                // LLM Call #2: Resolve which candidate (if any) matches
                $result = $resolver->resolveDuplicate(
                    $extractionResult['data'] ?? [],
                    $candidates,
                    $this->taskRun,
                    $this->taskProcess
                );
                if ($result->hasDuplicate()) {
                    $matchId = $result->existingObjectId;
                    static::logDebug('LLM resolution found match', ['object_id' => $matchId]);
                }
            }
        }

        // Create or use existing TeamObject
        $teamObject = $this->resolveOrCreateTeamObject(
            $group['object_type'],
            $extractionResult['data'] ?? [],
            $matchId,
            $parentObjectId
        );

        // Store resolved object ID for dependent processes
        app(ExtractionProcessOrchestrator::class)->storeResolvedObjectId(
            $this->taskRun,
            $group['object_type'],
            $teamObject->id,
            $level
        );

        static::logDebug('Extract Identity completed', [
            'object_type'  => $group['object_type'],
            'object_id'    => $teamObject->id,
            'was_existing' => $matchId !== null,
        ]);

        $this->complete();
    }

    /**
     * Run the extract remaining operation.
     * Extracts additional data points for resolved objects.
     */
    protected function runExtractRemainingOperation(): void
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
     * Extract identity fields with search query using LLM.
     */
    protected function extractIdentityWithSearchQuery(Collection $artifacts, array $group): array
    {
        // Build schema that includes search_query
        $fragmentSelector = $group['fragment_selector'] ?? [];
        $identityFields   = $group['identity_fields']   ?? [];

        // Build search_query schema
        $searchQueryProperties = [];
        foreach ($identityFields as $field) {
            $searchQueryProperties[$field] = [
                'type'        => 'string',
                'description' => "SQL LIKE pattern for searching {$field} (use % wildcards)",
            ];
        }

        $responseSchema = [
            'type'       => 'object',
            'properties' => [
                'data'         => $fragmentSelector,
                'search_query' => [
                    'type'        => 'object',
                    'description' => 'SQL LIKE patterns for finding matching records',
                    'properties'  => $searchQueryProperties,
                ],
            ],
            'required' => ['data', 'search_query'],
        ];

        // Run LLM extraction
        $taskDefinition   = $this->taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent || !$schemaDefinition) {
            return [];
        }

        $jsonSchemaService = app(JsonSchemaService::class);

        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $this->taskRun->team_id)
            ->named('Identity Data Extraction')
            ->withArtifacts($artifacts)
            ->build();

        $timeout = $taskDefinition->task_runner_config['extraction_timeout'] ?? 60;
        $timeout = max(1, min((int)$timeout, 600));

        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($schemaDefinition, null, $jsonSchemaService->setSchema($responseSchema))
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            static::logDebug('Identity extraction thread failed', ['error' => $threadRun->error ?? 'Unknown']);

            return [];
        }

        $data = $threadRun->lastMessage?->getJsonContent();

        return is_array($data) ? $data : [];
    }

    /**
     * Resolve or create TeamObject.
     */
    protected function resolveOrCreateTeamObject(
        string $objectType,
        array $identificationData,
        ?int $existingId,
        ?int $parentObjectId
    ): TeamObject {
        if ($existingId) {
            $teamObject = TeamObject::find($existingId);
            if ($teamObject) {
                // Update existing object with new data
                $mapper = app(JSONSchemaDataToDatabaseMapper::class);
                if ($this->taskRun->taskDefinition->schemaDefinition) {
                    $mapper->setSchemaDefinition($this->taskRun->taskDefinition->schemaDefinition);
                }
                $mapper->updateTeamObject($teamObject, $identificationData);

                return $teamObject;
            }
        }

        // Create new TeamObject
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);

        if ($this->taskRun->taskDefinition->schemaDefinition) {
            $mapper->setSchemaDefinition($this->taskRun->taskDefinition->schemaDefinition);
        }

        if ($parentObjectId) {
            $parentObject = TeamObject::find($parentObjectId);
            if ($parentObject) {
                $mapper->setRootObject($parentObject);
            }
        }

        $name = $identificationData['name'] ?? $identificationData['id'] ?? 'Unknown';

        return $mapper->createTeamObject($objectType, $name, $identificationData);
    }

    /**
     * Called after all parallel processes have completed.
     * Creates next phase of processes based on current state.
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();

        static::logDebug('All processes completed - checking next phase');

        // Try planning phase first
        $planningService = app(PlanningPhaseService::class);
        if ($planningService->handlePlanningPhaseIfActive($this->taskRun)) {
            return;
        }

        // Handle extraction phase
        $extractionService = app(ExtractionPhaseService::class);
        $extractionService->handleExtractionPhase($this->taskRun);
    }
}
