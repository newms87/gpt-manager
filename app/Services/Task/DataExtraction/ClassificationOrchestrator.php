<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskRun;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

/**
 * Orchestrates classification process creation and completion checking.
 * Handles per-page classification of artifacts using boolean schemas.
 */
class ClassificationOrchestrator
{
    use HasDebugLogging;

    /**
     * Create classification processes per page.
     * Each page gets its own TaskProcess for parallel classification.
     * Skips artifacts that already have cached classification results.
     *
     * @param  TaskRun  $taskRun  The task run to create processes for
     * @param  Collection  $childArtifacts  Collection of child artifacts (one per page)
     * @param  array  $booleanSchema  The classification schema to check cache for
     * @return array Array of created TaskProcess instances
     */
    public function createClassifyProcessesPerPage(
        TaskRun $taskRun,
        Collection $childArtifacts,
        array $booleanSchema
    ): array {
        static::logDebug('Creating classify processes per page', [
            'task_run_id'           => $taskRun->id,
            'child_artifacts_count' => $childArtifacts->count(),
        ]);

        $processes               = [];
        $classificationExecutor  = app(ClassificationExecutorService::class);
        $skippedCount            = 0;

        foreach ($childArtifacts as $childArtifact) {
            $pageNumber = $childArtifact->position;

            // Check if this artifact has cached classification
            if ($classificationExecutor->hasCachedClassification($childArtifact, $booleanSchema)) {
                static::logDebug('Skipping page with cached classification', [
                    'artifact_id' => $childArtifact->id,
                    'page_number' => $pageNumber,
                ]);

                // Get cached result and store in artifact meta
                $storedFile   = $childArtifact->storedFiles()->first();
                $cachedResult = $storedFile ? $classificationExecutor->getCachedClassification($storedFile, $booleanSchema) : null;

                if ($cachedResult) {
                    $meta                   = $childArtifact->meta ?? [];
                    $meta['classification'] = $cachedResult;
                    $childArtifact->meta    = $meta;
                    $childArtifact->save();

                    static::logDebug('Stored cached classification in artifact meta', [
                        'artifact_id' => $childArtifact->id,
                        'result'      => $cachedResult,
                    ]);
                }

                $skippedCount++;

                continue;
            }

            $process = $taskRun->taskProcesses()->create([
                'name'      => "Classify Page $pageNumber",
                'operation' => ExtractDataTaskRunner::OPERATION_CLASSIFY,
                'activity'  => "Classifying page $pageNumber",
                'meta'      => [
                    'child_artifact_id' => $childArtifact->id,
                ],
                'is_ready'  => true,
            ]);

            // Attach child artifact as input to process
            $process->inputArtifacts()->attach($childArtifact->id);
            $process->updateRelationCounter('inputArtifacts');

            $processes[] = $process;

            static::logDebug('Created classify process for page', [
                'process_id'        => $process->id,
                'page_number'       => $pageNumber,
                'child_artifact_id' => $childArtifact->id,
            ]);
        }

        static::logDebug('Created classify processes per page', [
            'processes_count' => count($processes),
            'skipped_count'   => $skippedCount,
        ]);

        return $processes;
    }

    /**
     * Check if classification is complete (all classify processes finished).
     *
     * @param  TaskRun  $taskRun  The task run to check
     * @return bool True if all classify processes are complete, false otherwise
     */
    public function isClassificationComplete(TaskRun $taskRun): bool
    {
        $classifyProcesses = $taskRun->taskProcesses()
            ->where('operation', ExtractDataTaskRunner::OPERATION_CLASSIFY)
            ->get();

        $totalProcesses     = $classifyProcesses->count();
        $completedProcesses = $classifyProcesses->whereNotNull('completed_at')->count();

        static::logDebug('Checking classification completion', [
            'task_run_id'         => $taskRun->id,
            'total_processes'     => $totalProcesses,
            'completed_processes' => $completedProcesses,
        ]);

        // If no classify processes exist, classification is not complete
        if ($totalProcesses === 0) {
            static::logDebug('No classify processes found, classification not complete');

            return false;
        }

        $isComplete = $completedProcesses === $totalProcesses;

        static::logDebug('Classification completion result', [
            'is_complete' => $isComplete,
        ]);

        return $isComplete;
    }

    /**
     * Get all groups at a specific level from the plan.
     * Returns combined identities and remaining groups.
     * Used for building classification schemas.
     */
    public function getGroupsAtLevel(array $plan, int $level): array
    {
        $levelData = $plan['levels'][$level] ?? null;

        if (!$levelData) {
            return [];
        }

        // New structure: combine identities and remaining
        $identities = $levelData['identities'] ?? [];
        $remaining  = $levelData['remaining']  ?? [];

        return array_merge($identities, $remaining);
    }
}
