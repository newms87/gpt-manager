<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Traits\HasDebugLogging;

/**
 * Handles extraction phase state transitions for data extraction.
 * Manages transitions from Classification → Resolve Objects → Extract Groups → Next Level.
 */
class ExtractionPhaseService
{
    use HasDebugLogging;

    /**
     * Handle extraction phase completion and transitions.
     * Called when not in planning phase.
     */
    public function handleExtractionPhase(TaskRun $taskRun): void
    {
        $orchestrator = app(ExtractionProcessOrchestrator::class);
        $plan         = $this->getExtractionPlan($taskRun);

        if (empty($plan)) {
            static::logDebug('WARNING: No plan available');

            return;
        }

        // Check if classification just completed
        if ($this->handleClassificationCompletion($taskRun, $orchestrator, $plan)) {
            return;
        }

        // Handle level progression
        $this->handleLevelProgression($taskRun, $orchestrator, $plan);
    }

    /**
     * Check if classification completed and transition to extract identity.
     */
    protected function handleClassificationCompletion(TaskRun $taskRun, ExtractionProcessOrchestrator $orchestrator, array $plan): bool
    {
        // Check if classification just completed
        $hasClassificationProcess = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->exists();

        if (!$hasClassificationProcess) {
            return false;
        }

        // Use classification orchestrator method to check if ALL classify processes are complete
        $classificationOrchestrator = app(ClassificationOrchestrator::class);
        $classificationCompleted    = $classificationOrchestrator->isClassificationComplete($taskRun);

        if (!$classificationCompleted) {
            return false;
        }

        static::logDebug('All classification processes completed - ready to create extract identity processes');

        // Check if we've already started level 0 identity extraction
        $hasIdentityProcess = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY)
            ->exists();

        if (!$hasIdentityProcess) {
            static::logDebug('Classification completed - creating Extract Identity processes for level 0');
            $orchestrator->createExtractIdentityProcesses($taskRun, $plan, 0);

            return true;
        }

        return false;
    }

    /**
     * Handle level progression for extraction.
     */
    protected function handleLevelProgression(TaskRun $taskRun, ExtractionProcessOrchestrator $orchestrator, array $plan): void
    {
        $currentLevel  = $orchestrator->getCurrentLevel($taskRun);
        $progress      = $orchestrator->getLevelProgress($taskRun);
        $levelProgress = $progress[$currentLevel] ?? [];

        // Check if identity is complete
        if ($orchestrator->isIdentityCompleteForLevel($taskRun, $currentLevel)) {
            if (!($levelProgress['identity_complete'] ?? false)) {
                $orchestrator->updateLevelProgress($taskRun, $currentLevel, 'identity_complete', true);

                // Create Extract Remaining processes
                static::logDebug('Identity completed - creating Extract Remaining processes', ['level' => $currentLevel]);
                $processes = $orchestrator->createExtractRemainingProcesses($taskRun, $plan, $currentLevel);
                if (empty($processes)) {
                    // No remaining processes needed - mark extraction complete and continue
                    // to check level progression (don't return early!)
                    static::logDebug('No extract remaining processes needed - marking extraction complete', ['level' => $currentLevel]);
                    $orchestrator->updateLevelProgress($taskRun, $currentLevel, 'extraction_complete', true);
                    // Refresh levelProgress since we just updated it
                    $levelProgress = $orchestrator->getLevelProgress($taskRun)[$currentLevel] ?? [];
                } else {
                    // Remaining processes created - wait for them to complete
                    return;
                }
            }
        }

        // Check if remaining extraction is complete
        if (!($levelProgress['extraction_complete'] ?? false)) {
            // Check if all Extract Remaining processes are done
            $remainingProcesses = $taskRun->taskProcesses()
                ->where('operation', ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING)
                ->where('meta->level', $currentLevel)
                ->get();

            if ($remainingProcesses->isNotEmpty() && $remainingProcesses->every(fn($p) => $p->completed_at !== null)) {
                static::logDebug('Extract Remaining completed - marking extraction complete', ['level' => $currentLevel]);
                $orchestrator->updateLevelProgress($taskRun, $currentLevel, 'extraction_complete', true);
            } else {
                static::logDebug('Extract Remaining not complete yet', ['level' => $currentLevel]);

                return;
            }
        }

        // Check if current level is fully complete
        if (!$orchestrator->isLevelComplete($taskRun, $currentLevel)) {
            static::logDebug('Current level not complete yet', ['level' => $currentLevel]);

            return;
        }

        // Check if all levels are complete
        if ($orchestrator->isAllLevelsComplete($taskRun, $plan)) {
            static::logDebug('All levels complete - extraction finished');

            return; // TaskRun will complete naturally
        }

        // Advance to next level
        if ($orchestrator->advanceToNextLevel($taskRun)) {
            $nextLevel = $orchestrator->getCurrentLevel($taskRun);
            static::logDebug("Advancing to level $nextLevel - creating Extract Identity processes");

            // Create Extract Identity processes for next level
            $orchestrator->createExtractIdentityProcesses($taskRun, $plan, $nextLevel);
        }
    }

    /**
     * Get extraction plan from TaskDefinition.
     */
    protected function getExtractionPlan(TaskRun $taskRun): array
    {
        $cachedPlan = $taskRun->taskDefinition->meta['extraction_plan'] ?? null;

        return $cachedPlan ?? [];
    }
}
