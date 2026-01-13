<?php

namespace App\Services\Task\Runners;

use App\Services\Task\DataExtraction\ClassificationExecutorService;
use App\Services\Task\DataExtraction\ExtractionStateOrchestrator;
use App\Services\Task\DataExtraction\IdentityExtractionService;
use App\Services\Task\DataExtraction\PerObjectPlanningService;
use App\Services\Task\DataExtraction\RemainingExtractionService;
use App\Services\Task\Traits\HasTranscodePrerequisite;
use App\Services\Task\TranscodePrerequisiteService;
use Newms87\Danx\Exceptions\ValidationError;

class ExtractDataTaskRunner extends AgentThreadTaskRunner
{
    use HasTranscodePrerequisite;

    public const string VERSION = '1.0.0';

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

        static::logDebug('ExtractDataTaskRunner v' . self::VERSION . ' - Preparing extract data task run');

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
        static::logDebug('ExtractDataTaskRunner v' . self::VERSION . " - Running operation: {$this->taskProcess->operation}");

        match ($this->taskProcess->operation) {
            TranscodePrerequisiteService::OPERATION_TRANSCODE => $this->runTranscodeOperation(),
            self::OPERATION_PLAN_IDENTIFY                     => $this->runPlanIdentifyOperation(),
            self::OPERATION_PLAN_REMAINING                    => $this->runPlanRemainingOperation(),
            self::OPERATION_CLASSIFY                          => $this->runClassificationOperation(),
            self::OPERATION_EXTRACT_IDENTITY                  => $this->runExtractIdentityOperation(),
            self::OPERATION_EXTRACT_REMAINING                 => $this->runExtractRemainingOperation(),
            default                                           => $this->runInitializeOperation()
        };
    }

    /**
     * Run the initialize operation (Default Task handler).
     * This is the first process created by TaskRunnerService.
     * Delegates to ExtractionStateOrchestrator to determine and create next phase processes.
     */
    protected function runInitializeOperation(): void
    {
        static::logDebug('Running initialize operation (Default Task)');

        app(ExtractionStateOrchestrator::class)->advanceToNextPhase($this->taskRun, $this->taskProcess);

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
        $level = $this->taskProcess->meta['level']          ?? 0;
        $group = $this->taskProcess->meta['identity_group'] ?? [];

        if (empty($group)) {
            static::logDebug('No identity group found in task process meta');
            $this->complete();

            return;
        }

        // Override search_mode with the resolved value from process meta (respects global override)
        $group['search_mode'] = $this->taskProcess->meta['search_mode'] ?? $group['search_mode'] ?? 'skim';

        // Delegate to IdentityExtractionService
        app(IdentityExtractionService::class)->execute(
            taskRun: $this->taskRun,
            taskProcess: $this->taskProcess,
            identityGroup: $group,
            level: $level
        );

        $this->complete();
    }

    /**
     * Run the extract remaining operation.
     * Delegates to RemainingExtractionService for additional data extraction.
     */
    protected function runExtractRemainingOperation(): void
    {
        $level        = $this->taskProcess->meta['level']            ?? 0;
        $group        = $this->taskProcess->meta['extraction_group'] ?? [];
        $teamObjectId = $this->taskProcess->meta['object_id']        ?? null;
        $searchMode   = $this->taskProcess->meta['search_mode']      ?? 'exhaustive';

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
            searchMode: $searchMode
        );

        $this->complete();
    }

    /**
     * Called after all parallel processes have completed.
     * Delegates to ExtractionStateOrchestrator to determine and create next phase processes.
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();

        static::logDebug('ExtractDataTaskRunner v' . self::VERSION . ' - All processes completed, checking next phase');

        app(ExtractionStateOrchestrator::class)->advanceToNextPhase($this->taskRun);
    }
}
