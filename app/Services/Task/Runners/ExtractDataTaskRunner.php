<?php

namespace App\Services\Task\Runners;

use App\Services\Task\DataExtraction\ArtifactPreparationService;
use App\Services\Task\DataExtraction\ClassificationExecutorService;
use App\Services\Task\DataExtraction\ClassificationOrchestrator;
use App\Services\Task\DataExtraction\ClassificationSchemaBuilder;
use App\Services\Task\DataExtraction\ExtractionPhaseService;
use App\Services\Task\DataExtraction\ExtractionPlanningService;
use App\Services\Task\DataExtraction\IdentityExtractionService;
use App\Services\Task\DataExtraction\ObjectTypeExtractor;
use App\Services\Task\DataExtraction\PerObjectPlanningService;
use App\Services\Task\DataExtraction\PlanningPhaseService;
use App\Services\Task\DataExtraction\RemainingExtractionService;
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
     * Delegates to IdentityExtractionService for identity field extraction and TeamObject resolution.
     */
    protected function runExtractIdentityOperation(): void
    {
        $level          = $this->taskProcess->meta['level']            ?? 0;
        $group          = $this->taskProcess->meta['identity_group']   ?? [];
        $parentObjectId = $this->taskProcess->meta['parent_object_id'] ?? null;

        if (empty($group)) {
            static::logDebug('No identity group found in task process meta');
            $this->complete();

            return;
        }

        // Delegate to IdentityExtractionService
        app(IdentityExtractionService::class)->execute(
            taskRun: $this->taskRun,
            taskProcess: $this->taskProcess,
            identityGroup: $group,
            level: $level,
            parentObjectId: $parentObjectId
        );

        $this->complete();
    }

    /**
     * Run the extract remaining operation.
     * Delegates to RemainingExtractionService for additional data extraction.
     */
    protected function runExtractRemainingOperation(): void
    {
        $level          = $this->taskProcess->meta['level']            ?? 0;
        $group          = $this->taskProcess->meta['extraction_group'] ?? [];
        $teamObjectId   = $this->taskProcess->meta['object_id']        ?? null;
        $searchMode     = $this->taskProcess->meta['search_mode']      ?? 'exhaustive';
        $parentObjectId = $this->taskProcess->meta['parent_object_id'] ?? null;

        if (empty($group) || !$teamObjectId) {
            static::logDebug('Missing extraction group or object_id in task process meta');
            $this->complete();

            return;
        }

        // Delegate to RemainingExtractionService
        app(RemainingExtractionService::class)->execute(
            taskRun: $this->taskRun,
            taskProcess: $this->taskProcess,
            extractionGroup: $group,
            level: $level,
            teamObjectId: $teamObjectId,
            searchMode: $searchMode,
            parentObjectId: $parentObjectId
        );

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
