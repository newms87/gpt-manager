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
     * Build and store classification schema in TaskRun meta if not already present.
     * Returns the schema (either existing or newly built).
     *
     * @param  TaskRun  $taskRun  The task run to store the schema in
     * @param  array  $plan  The extraction plan to build the schema from
     * @return array|null The classification schema, or null if no plan provided
     */
    public function ensureClassificationSchema(TaskRun $taskRun, ?array $plan): ?array
    {
        $existingSchema = $taskRun->meta['classification_schema'] ?? null;

        if ($existingSchema) {
            return $existingSchema;
        }

        if (!$plan) {
            return null;
        }

        $schemaBuilder = app(ClassificationSchemaBuilder::class);
        $booleanSchema = $schemaBuilder->buildBooleanSchema($plan);

        $meta                          = $taskRun->meta ?? [];
        $meta['classification_schema'] = $booleanSchema;
        $taskRun->meta                 = $meta;
        $taskRun->save();

        static::logDebug('Stored classification schema', [
            'properties_count' => count($booleanSchema['properties'] ?? []),
        ]);

        return $booleanSchema;
    }

    /**
     * Create classification processes per page.
     * Each page gets its own TaskProcess for parallel classification.
     * Skips artifacts that already have cached classification results.
     *
     * @param  TaskRun  $taskRun  The task run to create processes for
     * @param  Collection  $childArtifacts  Collection of child artifacts (one per page)
     * @param  array  $booleanSchema  The classification schema to check cache for
     * @param  int  $schemaDefinitionId  The schema definition ID for cache storage location
     * @return array Array of created TaskProcess instances
     */
    public function createClassifyProcessesPerPage(
        TaskRun $taskRun,
        Collection $childArtifacts,
        array $booleanSchema,
        int $schemaDefinitionId
    ): array {
        static::logDebug('Creating classify processes per page', [
            'task_run_id'           => $taskRun->id,
            'child_artifacts_count' => $childArtifacts->count(),
            'schema_definition_id'  => $schemaDefinitionId,
        ]);

        $processes               = [];
        $classificationExecutor  = app(ClassificationExecutorService::class);
        $skippedCount            = 0;

        // Compute schema hash once for cache lookups
        $schemaHash = hash('sha256', json_encode($booleanSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        foreach ($childArtifacts as $childArtifact) {
            $pageNumber = $childArtifact->position;

            // Check if this artifact has cached classification (uses schema definition ID + hash)
            if ($classificationExecutor->hasCachedClassification($childArtifact, $schemaDefinitionId, $booleanSchema)) {
                static::logDebug('Skipping page with cached classification', [
                    'artifact_id' => $childArtifact->id,
                    'page_number' => $pageNumber,
                ]);

                // Get cached result and store in artifact meta
                $storedFile   = $childArtifact->storedFiles()->first();
                $cachedResult = $storedFile ? $classificationExecutor->getCachedClassification($storedFile, $schemaDefinitionId, $schemaHash) : null;

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
     * Check if classification is complete (all classify processes finished OR all artifacts have cached classification).
     *
     * @param  TaskRun  $taskRun  The task run to check
     * @return bool True if all classify processes are complete or all artifacts have cached classification, false otherwise
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

        // If processes exist, check if all completed
        if ($totalProcesses > 0) {
            $isComplete = $completedProcesses === $totalProcesses;

            static::logDebug('Classification completion result (via processes)', [
                'is_complete' => $isComplete,
            ]);

            return $isComplete;
        }

        // No processes - check if all child artifacts have classification meta
        // This handles the case where classification was cached from previous runs
        $isComplete = $this->isClassificationCompleteViaArtifactMeta($taskRun);

        static::logDebug('Classification completion result (via artifact meta)', [
            'is_complete' => $isComplete,
        ]);

        return $isComplete;
    }

    /**
     * Check if classification is complete via artifact meta (for cached classification results).
     * Returns true if all child artifacts have classification data in their meta.
     */
    protected function isClassificationCompleteViaArtifactMeta(TaskRun $taskRun): bool
    {
        // Use the most recently created parent artifact
        // This ensures we check the artifacts created by createExtractionArtifacts(), not pre-existing ones
        $parentArtifact = app(ExtractionProcessOrchestrator::class)->getParentOutputArtifact($taskRun);

        if (!$parentArtifact) {
            static::logDebug('No parent artifact found for classification meta check');

            return false;
        }

        $childArtifacts = $parentArtifact->children;

        if ($childArtifacts->isEmpty()) {
            static::logDebug('No child artifacts found for classification meta check');

            return false;
        }

        // Check if ALL child artifacts have classification meta
        foreach ($childArtifacts as $child) {
            if (empty($child->meta['classification'])) {
                static::logDebug('Child artifact missing classification meta', [
                    'artifact_id' => $child->id,
                ]);

                return false;
            }
        }

        static::logDebug('All child artifacts have classification meta', [
            'child_count' => $childArtifacts->count(),
        ]);

        return true;
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
