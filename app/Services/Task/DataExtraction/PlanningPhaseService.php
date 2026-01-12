<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Traits\HasDebugLogging;

/**
 * Handles planning phase state transitions for data extraction.
 * Manages transitions from Plan:Identify → Plan:Remaining → Classification.
 */
class PlanningPhaseService
{
    use HasDebugLogging;

    /**
     * Check if planning phase is active and handle its completion.
     * Returns true if planning phase was handled, false if not in planning phase.
     */
    public function handlePlanningPhaseIfActive(TaskRun $taskRun): bool
    {
        // Check if identity planning phase just completed
        $identifyProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_PLAN_IDENTIFY)
            ->get();

        if ($identifyProcesses->isEmpty()) {
            return false; // Not in planning phase
        }

        $allIdentifyComplete = $identifyProcesses->every(fn($p) => $p->completed_at !== null);

        if (!$allIdentifyComplete) {
            return true; // Still in planning phase, but not complete
        }

        // Handle transition from identity planning
        return $this->handleIdentityPlanningComplete($taskRun);
    }

    /**
     * Handle completion of identity planning phase.
     * Creates remaining processes or transitions to classification.
     *
     * Returns true if planning is still active (more planning work to do),
     * false if planning is complete and classification already exists.
     */
    protected function handleIdentityPlanningComplete(TaskRun $taskRun): bool
    {
        $perObjectService = app(PerObjectPlanningService::class);

        // Check if we need remaining processes
        $remainingProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_PLAN_REMAINING)
            ->get();

        if ($remainingProcesses->isEmpty()) {
            // Create remaining processes if needed
            $createdProcesses = $perObjectService->createRemainingProcesses($taskRun);

            if (empty($createdProcesses)) {
                // No remaining processes needed - compile plan and create per-page classification
                static::logDebug('No remaining processes needed - compiling final plan');
                $createdClassification = $this->compilePlanAndTransitionToClassification($taskRun);

                // If classification was created, planning phase handled it.
                // If not (already exists), planning phase is effectively done - return false
                // so extraction phase can be handled.
                return $createdClassification;
            }

            return true;
        }

        // Check if all remaining processes are complete
        $allRemainingComplete = $remainingProcesses->every(fn($p) => $p->completed_at !== null);

        if ($allRemainingComplete) {
            // All planning done - compile and create per-page classification
            static::logDebug('All planning complete - compiling final plan');
            $createdClassification = $this->compilePlanAndTransitionToClassification($taskRun);

            // Same logic: return false if classification already existed
            return $createdClassification;
        }

        return true;
    }

    /**
     * Compile final plan and transition to classification phase.
     *
     * @return bool True if classification processes were created, false if they already existed
     */
    protected function compilePlanAndTransitionToClassification(TaskRun $taskRun): bool
    {
        $perObjectService = app(PerObjectPlanningService::class);

        // Compile final plan
        $finalPlan = $perObjectService->compileFinalPlan($taskRun);
        app(ExtractionPlanningService::class)->cachePlan(
            $taskRun->taskDefinition,
            $finalPlan
        );

        // Check if classification processes already exist
        $hasClassifyProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->exists();

        if ($hasClassifyProcesses) {
            static::logDebug('Classification processes already exist - skipping creation');

            return false;
        }

        // Resolve all pages from input artifacts
        $artifactService = app(ArtifactPreparationService::class);
        $pages           = $artifactService->resolvePages($taskRun);

        if (!empty($pages)) {
            $this->transitionToClassification($taskRun, $finalPlan, $pages);

            return true;
        }

        static::logDebug('No pages found to classify');

        return true;
    }

    /**
     * Transition from planning to classification phase.
     */
    public function transitionToClassification(TaskRun $taskRun, array $finalPlan, array $pages): void
    {
        // Build and store boolean classification schema
        $schemaBuilder = app(ClassificationSchemaBuilder::class);
        $booleanSchema = $schemaBuilder->buildBooleanSchema($finalPlan);

        // Store schema in TaskRun meta
        $meta                          = $taskRun->meta ?? [];
        $meta['classification_schema'] = $booleanSchema;
        $taskRun->meta                 = $meta;
        $taskRun->save();

        static::logDebug('Stored classification schema', [
            'properties_count' => count($booleanSchema['properties'] ?? []),
        ]);

        // Create extraction artifacts
        $artifactService = app(ArtifactPreparationService::class);
        $parentArtifact  = $artifactService->createExtractionArtifacts($taskRun, $pages);

        // Attach parent artifact to TaskRun
        $taskRun->outputArtifacts()->syncWithoutDetaching([$parentArtifact->id]);
        $taskRun->updateRelationCounter('outputArtifacts');

        // Create per-page classification processes
        $classificationOrchestrator = app(ClassificationOrchestrator::class);
        $classificationOrchestrator->createClassifyProcessesPerPage(
            $taskRun,
            $parentArtifact->children,
            $booleanSchema
        );

        static::logDebug('Transitioned to classification phase', [
            'pages_count' => $parentArtifact->children->count(),
        ]);
    }
}
