<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Services\Task\TranscodePrerequisiteService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Unified state machine orchestrator for ExtractDataTaskRunner.
 * Handles all phase transitions: initialization, planning, classification, and extraction.
 *
 * Both runInitializeOperation() and afterAllProcessesCompleted() call advanceToNextPhase()
 * to determine and create the next set of processes based on current state.
 */
class ExtractionStateOrchestrator
{
    use HasDebugLogging;

    /**
     * Advance the task run to its next phase based on current state.
     * Creates appropriate processes for the next phase.
     *
     * @param  TaskRun  $taskRun  The task run to advance
     * @param  TaskProcess|null  $taskProcess  Optional task process (for initialization to attach output artifacts)
     */
    public function advanceToNextPhase(TaskRun $taskRun, ?TaskProcess $taskProcess = null): void
    {
        static::logDebug('Advancing to next phase', ['task_run_id' => $taskRun->id]);

        // Check phases in order of progression
        if ($this->needsPlanning($taskRun)) {
            $this->createPlanningProcesses($taskRun);

            return;
        }

        // Get plan for remaining phases
        // Use getCachedPlan() for artifact creation (validates cache), getExtractionPlan() for extraction phases
        $cachedPlan     = app(ExtractionPlanningService::class)->getCachedPlan($taskRun->taskDefinition);
        $extractionPlan = $this->getExtractionPlan($taskRun);

        // Check for artifact creation:
        // - During initialization (with taskProcess)
        // - After planning completes (planning processes exist and are all completed)
        if ($this->needsExtractionArtifacts($taskRun)) {
            $hasPlan               = !empty($cachedPlan) || !empty($extractionPlan);
            $planningJustCompleted = $this->didPlanningJustComplete($taskRun);

            // Create artifacts if: we have a plan AND (initialization OR planning just completed)
            if ($hasPlan && ($taskProcess || $planningJustCompleted)) {
                $this->createExtractionArtifacts($taskRun, $taskProcess, $cachedPlan ?? $extractionPlan);
                // Don't return - continue to check transcoding/classification
            }
        }

        if ($this->needsTranscoding($taskRun)) {
            $this->createTranscodeProcesses($taskRun);

            return;
        }

        if ($this->needsClassification($taskRun)) {
            $this->createClassificationProcesses($taskRun, $cachedPlan ?? $extractionPlan);

            return;
        }

        // Use extractionPlan for extraction phases (doesn't require cache validation)
        if ($this->needsIdentityExtraction($taskRun, $extractionPlan)) {
            $this->createIdentityExtractionProcesses($taskRun, $extractionPlan);

            return;
        }

        if ($this->needsRemainingExtraction($taskRun, $extractionPlan)) {
            $this->createRemainingExtractionProcesses($taskRun, $extractionPlan);

            return;
        }

        if ($this->canAdvanceLevel($taskRun, $extractionPlan)) {
            $this->advanceLevelAndContinue($taskRun, $extractionPlan);

            return;
        }

        static::logDebug('No more phases to advance - extraction complete');
    }

    /**
     * Get extraction plan directly from TaskDefinition.meta.
     * Does not validate cache key - used for extraction phases where plan is already set.
     */
    protected function getExtractionPlan(TaskRun $taskRun): array
    {
        return app(ExtractionProcessOrchestrator::class)->getExtractionPlan($taskRun);
    }

    /**
     * Get all planning processes (PLAN_IDENTIFY and PLAN_REMAINING) for the task run.
     * Helper method to avoid duplicating this query pattern throughout the class.
     */
    protected function getPlanningProcesses(TaskRun $taskRun): Collection
    {
        return $taskRun->taskProcesses()
            ->whereIn('operation', [
                ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY,
                ExtractDataTaskRunner::OPERATION_PLAN_REMAINING,
            ])
            ->get();
    }

    /**
     * Check if planning phase just completed (all planning processes completed, no output artifacts yet).
     */
    protected function didPlanningJustComplete(TaskRun $taskRun): bool
    {
        $planningProcesses = $this->getPlanningProcesses($taskRun);

        // No planning processes = didn't just complete planning
        if ($planningProcesses->isEmpty()) {
            return false;
        }

        // Check if all planning processes are complete
        return $planningProcesses->every(fn($p) => $p->completed_at !== null);
    }

    /**
     * Check if planning phase is active or needed.
     * Returns true if we should handle planning (either create or continue it).
     */
    protected function needsPlanning(TaskRun $taskRun): bool
    {
        // Check if valid cached plan exists
        $cachedPlan = app(ExtractionPlanningService::class)->getCachedPlan($taskRun->taskDefinition);

        if ($cachedPlan) {
            return false;
        }

        // Also check if plan exists directly (without cache validation) - tests may set up plans this way
        $directPlan        = $this->getExtractionPlan($taskRun);
        $planningProcesses = $this->getPlanningProcesses($taskRun);

        if (!empty($directPlan)) {
            // Plan exists - check if there are incomplete planning processes
            $hasIncompleteProcesses = $planningProcesses->whereNull('completed_at')->isNotEmpty();

            // Only need planning if there are incomplete planning processes
            return $hasIncompleteProcesses;
        }

        if ($planningProcesses->isNotEmpty()) {
            // Planning already started - let PlanningPhaseService handle continuation
            return true;
        }

        // No cached plan and no planning processes - need to start planning
        return true;
    }

    /**
     * Check if extraction artifacts need to be created.
     * Returns true if we have a cached plan but no output artifacts yet.
     */
    protected function needsExtractionArtifacts(TaskRun $taskRun): bool
    {
        $hasOutputArtifacts = $taskRun->outputArtifacts()
            ->whereNull('parent_artifact_id')
            ->exists();

        return !$hasOutputArtifacts;
    }

    /**
     * Check if transcoding phase is needed.
     */
    protected function needsTranscoding(TaskRun $taskRun): bool
    {
        // Check if transcode processes exist but aren't complete
        $transcodeProcesses = $taskRun->taskProcesses()
            ->where('operation', TranscodePrerequisiteService::OPERATION_TRANSCODE)
            ->get();

        if ($transcodeProcesses->isNotEmpty()) {
            // Transcode processes exist - check if complete
            return $transcodeProcesses->whereNull('completed_at')->isNotEmpty();
        }

        // No transcode processes yet - check if any artifacts need transcoding
        $parentArtifact = app(ExtractionProcessOrchestrator::class)->getParentOutputArtifact($taskRun);

        if (!$parentArtifact) {
            return false;
        }

        $transcodeService = app(TranscodePrerequisiteService::class);
        $needsTranscode   = $transcodeService->getArtifactsNeedingTranscode($parentArtifact->children);

        return $needsTranscode->isNotEmpty();
    }

    /**
     * Check if classification phase is needed.
     */
    protected function needsClassification(TaskRun $taskRun): bool
    {
        // Check if classification is already complete (via processes OR cached artifact meta)
        $classificationOrchestrator = app(ClassificationOrchestrator::class);
        if ($classificationOrchestrator->isClassificationComplete($taskRun)) {
            return false;
        }

        // Check if classify processes exist but aren't complete
        $classifyProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->get();

        if ($classifyProcesses->isNotEmpty()) {
            // Classify processes exist - check if complete
            return $classifyProcesses->whereNull('completed_at')->isNotEmpty();
        }

        // No classify processes - check if we need to create them
        // (transcode complete, no identity extraction started)
        $hasIdentityProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY)
            ->exists();

        if ($hasIdentityProcesses) {
            return false;
        }

        // Check if all transcoding is complete
        $transcodeProcesses = $taskRun->taskProcesses()
            ->where('operation', TranscodePrerequisiteService::OPERATION_TRANSCODE)
            ->get();

        if ($transcodeProcesses->isNotEmpty() && $transcodeProcesses->whereNull('completed_at')->isNotEmpty()) {
            return false; // Still transcoding
        }

        // Ready for classification
        return true;
    }

    /**
     * Check if identity extraction is needed for current level.
     */
    protected function needsIdentityExtraction(TaskRun $taskRun, ?array $plan): bool
    {
        if (!$plan) {
            return false;
        }

        // Check if classification is complete
        $classificationOrchestrator = app(ClassificationOrchestrator::class);
        if (!$classificationOrchestrator->isClassificationComplete($taskRun)) {
            return false;
        }

        $orchestrator = app(ExtractionProcessOrchestrator::class);
        $currentLevel = $orchestrator->getCurrentLevel($taskRun);

        // Check if identity is already marked complete in level_progress
        $levelProgress    = $orchestrator->getLevelProgress($taskRun);
        $progress         = $levelProgress[$currentLevel]  ?? [];
        $identityComplete = $progress['identity_complete'] ?? false;

        if ($identityComplete) {
            return false; // Identity already complete for current level
        }

        // Check if identity processes exist for current level
        $hasIdentityProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY)
            ->where('meta->level', $currentLevel)
            ->exists();

        if ($hasIdentityProcesses) {
            // Processes exist - don't create more, just let them complete
            // Return false because we don't need to CREATE identity extraction,
            // we just need to wait for existing processes to finish
            return false;
        }

        // No identity processes for this level yet
        return true;
    }

    /**
     * Check if remaining extraction is needed for current level.
     */
    protected function needsRemainingExtraction(TaskRun $taskRun, ?array $plan): bool
    {
        if (!$plan) {
            return false;
        }

        $orchestrator = app(ExtractionProcessOrchestrator::class);
        $currentLevel = $orchestrator->getCurrentLevel($taskRun);

        // Identity must be complete first
        if (!$orchestrator->isIdentityCompleteForLevel($taskRun, $currentLevel)) {
            return false;
        }

        // Check level progress
        $levelProgress = $orchestrator->getLevelProgress($taskRun);
        $progress      = $levelProgress[$currentLevel] ?? [];

        if ($progress['identity_complete'] ?? false) {
            // Identity marked complete - check if remaining processes exist or extraction is complete
            if ($progress['extraction_complete'] ?? false) {
                return false;
            }

            // Check if remaining processes exist for this level
            $remainingProcesses = $taskRun->taskProcesses()
                ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING)
                ->where('meta->level', $currentLevel)
                ->get();

            if ($remainingProcesses->isNotEmpty()) {
                // Remaining processes exist - check if complete
                return $remainingProcesses->whereNull('completed_at')->isNotEmpty();
            }

            // No remaining processes yet - need to create them
            return true;
        }

        // Identity not marked complete in progress - need to update
        $orchestrator->updateLevelProgress($taskRun, $currentLevel, 'identity_complete', true);

        return true;
    }

    /**
     * Check if we can advance to the next level.
     */
    protected function canAdvanceLevel(TaskRun $taskRun, ?array $plan): bool
    {
        if (!$plan) {
            return false;
        }

        $orchestrator = app(ExtractionProcessOrchestrator::class);
        $currentLevel = $orchestrator->getCurrentLevel($taskRun);

        // Check if current level is complete
        if (!$orchestrator->isLevelComplete($taskRun, $currentLevel)) {
            // Check if remaining extraction just completed and we need to mark it
            $remainingProcesses = $taskRun->taskProcesses()
                ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING)
                ->where('meta->level', $currentLevel)
                ->get();

            if ($remainingProcesses->isNotEmpty() && $remainingProcesses->every(fn($p) => $p->completed_at !== null)) {
                $orchestrator->updateLevelProgress($taskRun, $currentLevel, 'extraction_complete', true);
            } else {
                return false;
            }
        }

        // Check if all levels are complete
        return !$orchestrator->isAllLevelsComplete($taskRun, $plan);
    }

    /**
     * Handle planning phase: create new processes or continue existing planning.
     */
    protected function createPlanningProcesses(TaskRun $taskRun): void
    {
        // Check if planning processes already exist
        if ($this->getPlanningProcesses($taskRun)->isNotEmpty()) {
            // Planning already started - delegate to PlanningPhaseService
            app(PlanningPhaseService::class)->handlePlanningPhaseIfActive($taskRun);

            return;
        }

        // No planning processes - create identity planning processes
        static::logDebug('Creating identity planning processes');

        $objectExtractor = app(ObjectTypeExtractor::class);
        $objectTypes     = $objectExtractor->extractObjectTypes(
            $taskRun->taskDefinition->schemaDefinition
        );

        $planningService = app(PerObjectPlanningService::class);
        $planningService->createIdentityPlanningProcesses($taskRun, $objectTypes);

        static::logDebug('Created identity planning processes', ['count' => count($objectTypes)]);

        $taskRun->updateRelationCounter('taskProcesses');
    }

    /**
     * Create extraction artifacts (parent + children) from input pages.
     */
    protected function createExtractionArtifacts(TaskRun $taskRun, ?TaskProcess $taskProcess, ?array $cachedPlan): void
    {
        static::logDebug('Creating extraction artifacts');

        $artifactService = app(ArtifactPreparationService::class);
        $pages           = $artifactService->resolvePages($taskRun);

        if (empty($pages)) {
            throw new ValidationError(
                'No input pages found for data extraction. Please ensure the task has input artifacts with files attached (images or PDFs).'
            );
        }

        // Build and store classification schema using the centralized method
        app(ClassificationOrchestrator::class)->ensureClassificationSchema($taskRun, $cachedPlan);

        // Create parent + child artifacts
        $parentArtifact = $artifactService->createExtractionArtifacts($taskRun, $pages);

        // Attach parent artifact to TaskRun
        $taskRun->outputArtifacts()->syncWithoutDetaching([$parentArtifact->id]);
        $taskRun->updateRelationCounter('outputArtifacts');

        // If called from initialization, attach to TaskProcess too
        if ($taskProcess) {
            $taskProcess->outputArtifacts()->sync([$parentArtifact->id]);
            $taskProcess->updateRelationCounter('outputArtifacts');
        }

        static::logDebug('Created extraction artifacts', [
            'parent_artifact_id' => $parentArtifact->id,
            'children_count'     => $parentArtifact->children->count(),
        ]);
    }

    /**
     * Create transcode processes for artifacts needing transcoding.
     */
    protected function createTranscodeProcesses(TaskRun $taskRun): void
    {
        static::logDebug('Creating transcode processes');

        $parentArtifact = app(ExtractionProcessOrchestrator::class)->getParentOutputArtifact($taskRun);

        if (!$parentArtifact) {
            return;
        }

        $transcodeService = app(TranscodePrerequisiteService::class);
        $needsTranscode   = $transcodeService->getArtifactsNeedingTranscode($parentArtifact->children);

        if ($needsTranscode->isNotEmpty()) {
            $transcodeService->createTranscodeProcesses($taskRun, $needsTranscode);
            static::logDebug('Created transcode processes', ['count' => $needsTranscode->count()]);
        }
    }

    /**
     * Create classification processes for each page.
     */
    protected function createClassificationProcesses(TaskRun $taskRun, ?array $cachedPlan): void
    {
        static::logDebug('Creating classification processes');

        $parentArtifact = app(ExtractionProcessOrchestrator::class)->getParentOutputArtifact($taskRun);

        if (!$parentArtifact) {
            static::logDebug('No parent artifact found for classification');

            return;
        }

        $classificationOrchestrator = app(ClassificationOrchestrator::class);
        $booleanSchema              = $classificationOrchestrator->ensureClassificationSchema($taskRun, $cachedPlan);

        if (!$booleanSchema) {
            static::logDebug('No classification schema found');

            return;
        }

        $classificationOrchestrator->createClassifyProcessesPerPage(
            $taskRun,
            $parentArtifact->children,
            $booleanSchema
        );

        static::logDebug('Created classification processes', [
            'pages_count' => $parentArtifact->children->count(),
        ]);
    }

    /**
     * Create identity extraction processes for current level.
     */
    protected function createIdentityExtractionProcesses(TaskRun $taskRun, array $plan): void
    {
        $orchestrator = app(ExtractionProcessOrchestrator::class);
        $currentLevel = $orchestrator->getCurrentLevel($taskRun);

        static::logDebug('Creating identity extraction processes', ['level' => $currentLevel]);

        $orchestrator->createExtractIdentityProcesses($taskRun, $plan, $currentLevel);
    }

    /**
     * Create remaining extraction processes for current level.
     */
    protected function createRemainingExtractionProcesses(TaskRun $taskRun, array $plan): void
    {
        $orchestrator = app(ExtractionProcessOrchestrator::class);
        $currentLevel = $orchestrator->getCurrentLevel($taskRun);

        static::logDebug('Creating remaining extraction processes', ['level' => $currentLevel]);

        $processes = $orchestrator->createExtractRemainingProcesses($taskRun, $plan, $currentLevel);

        if (empty($processes)) {
            // No remaining processes needed - mark extraction complete
            static::logDebug('No remaining processes needed - marking extraction complete', ['level' => $currentLevel]);
            $orchestrator->updateLevelProgress($taskRun, $currentLevel, 'extraction_complete', true);

            // Recursively advance to check for level progression
            $this->advanceToNextPhase($taskRun);
        }
    }

    /**
     * Advance to next level and create identity processes.
     */
    protected function advanceLevelAndContinue(TaskRun $taskRun, array $plan): void
    {
        $orchestrator = app(ExtractionProcessOrchestrator::class);

        if ($orchestrator->advanceToNextLevel($taskRun)) {
            $nextLevel = $orchestrator->getCurrentLevel($taskRun);
            static::logDebug("Advanced to level $nextLevel - creating identity processes");

            $orchestrator->createExtractIdentityProcesses($taskRun, $plan, $nextLevel);
        }
    }
}
