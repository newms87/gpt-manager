<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Repositories\ThreadRepository;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\Task\TaskAgentThreadBuilderService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;

/**
 * Encapsulates reusable classification execution logic for task runners.
 * Handles running classification on artifacts using given schemas and storing results.
 *
 * Usage Example:
 * ```php
 * $service = app(ClassificationExecutorService::class);
 * $schema = app(ClassificationSchemaBuilder::class)->buildSchemaFromGroups($plan, $level);
 *
 * $results = $service->classifyArtifacts(
 *     $taskRun,
 *     $taskProcess,
 *     $schema,
 *     $artifacts
 * );
 *
 * // Get artifacts for a specific category
 * $categoryArtifacts = $service->getArtifactsForCategory($artifacts, 'category_key');
 * ```
 *
 * Classification results are stored in Artifact.meta['classification']:
 * ```php
 * $artifact->meta['classification'] = [
 *     'category_name' => 'value',
 *     'other_category' => 'value',
 *     // ... other classifications
 * ];
 * ```
 */
class ClassificationExecutorService
{
    use HasDebugLogging;

    /**
     * Classify a single page/artifact using a boolean schema.
     * Returns a boolean map indicating which properties apply to this page.
     *
     * @param  TaskRun  $taskRun  The task run context
     * @param  TaskProcess  $taskProcess  The task process for this classification
     * @param  array  $booleanSchema  Boolean schema mapping property names to descriptions
     * @param  Artifact  $artifact  Single artifact to classify (must have storedFiles attached)
     * @param  int  $schemaDefinitionId  The schema definition ID for cache storage location
     * @return array Boolean map like ['diagnosis_codes' => true, 'billing' => false, ...]
     */
    public function classifyPage(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $booleanSchema,
        Artifact $artifact,
        int $schemaDefinitionId
    ): array {
        $taskDefinition   = $taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent) {
            throw new ValidationError('Agent not found for TaskRun: ' . $taskRun->id);
        }

        if (!$schemaDefinition) {
            throw new ValidationError('SchemaDefinition not found for TaskRun: ' . $taskRun->id);
        }

        // Get the StoredFile for this artifact (page image/PDF)
        $storedFile = $artifact->storedFiles()->first();

        if (!$storedFile) {
            throw new ValidationError('No StoredFile found for Artifact: ' . $artifact->id);
        }

        // Compute schema hash for cache invalidation
        $schemaHash = $this->computeSchemaHash($booleanSchema);

        static::logDebug('Classifying single page', [
            'artifact_id'          => $artifact->id,
            'stored_file_id'       => $storedFile->id,
            'page_number'          => $artifact->position ?? 'unknown',
            'property_count'       => count($booleanSchema['properties'] ?? []),
            'schema_definition_id' => $schemaDefinitionId,
            'schema_hash'          => substr($schemaHash, 0, 12) . '...',
        ]);

        // Check cache before running LLM (uses schema definition ID for lookup, hash for invalidation)
        $cachedResult = $this->getCachedClassification($storedFile, $schemaDefinitionId, $schemaHash);

        if ($cachedResult !== null) {
            static::logDebug('Returning cached classification (skipping LLM call)', [
                'artifact_id'    => $artifact->id,
                'stored_file_id' => $storedFile->id,
            ]);

            return $cachedResult;
        }

        static::logDebug('Cache miss - running LLM classification', [
            'stored_file_id' => $storedFile->id,
        ]);

        // Build classification instructions from boolean schema
        $instructions = $this->buildBooleanSchemaInstructions($booleanSchema);

        // Create filter that only includes files and text (no meta, no json for classification)
        $filter = new ArtifactFilter(
            includeText: true,
            includeFiles: true,
            includeJson: false,
            includeMeta: false
        );

        // Build agent thread with single artifact
        $agentThread = TaskAgentThreadBuilderService::fromTaskDefinition($taskDefinition, $taskRun)
            ->withArtifacts([$artifact], $filter)
            ->includePageNumbers(false)
            ->build();

        // Add classification instructions
        app(ThreadRepository::class)->addMessageToThread($agentThread, $instructions);

        // Associate thread with task process
        $taskProcess->agentThread()->associate($agentThread)->save();

        // Get timeout from config
        $timeout = $taskDefinition->task_runner_config['timeout'] ?? null;
        if ($timeout !== null) {
            $timeout = (int)$timeout;
            $timeout = max(1, min($timeout, 600)); // Between 1-600 seconds
        }

        static::logDebug("Running page classification thread with agent: {$taskDefinition->agent->name}");

        // Create a temporary in-memory SchemaDefinition with the boolean schema
        // This allows us to pass a custom schema to withResponseFormat() without persisting to the database
        $tempSchemaDefinition = new SchemaDefinition([
            'name'   => 'page-classification',
            'schema' => $booleanSchema,
        ]);

        // Run the thread with the boolean schema as response format
        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($tempSchemaDefinition)
            ->withTimeout($timeout)
            ->run($agentThread);

        if (!$threadRun->isCompleted()) {
            throw new ValidationError('Page classification thread failed: ' . ($threadRun->error ?? 'Unknown error'));
        }

        // Get JSON content from response
        $classificationResults = $threadRun->lastMessage?->getJsonContent();

        if (!$classificationResults || !is_array($classificationResults)) {
            throw new ValidationError('No valid JSON content returned from page classification thread');
        }

        static::logDebug('Page classification completed', [
            'artifact_id' => $artifact->id,
            'results'     => $classificationResults,
        ]);

        // Store result in cache (keyed by schema definition ID, includes hash for invalidation)
        $this->storeCachedClassification($storedFile, $schemaDefinitionId, $schemaHash, $classificationResults);

        return $classificationResults;
    }

    /**
     * Build classification instructions from a boolean schema.
     * Returns the agent role description - property details are in the JSON schema itself.
     *
     * @param  array  $booleanSchema  Boolean schema with properties and descriptions
     * @return string Agent role description for the LLM
     */
    protected function buildBooleanSchemaInstructions(array $booleanSchema): string
    {
        return file_get_contents(resource_path('prompts/extract-data/page-classification.md'));
    }

    /**
     * Check if an artifact has cached classification result.
     * Returns true if the artifact's StoredFile has a cached result for the given schema definition
     * AND the cached schema hash matches the current schema hash (cache not busted).
     *
     * @param  Artifact  $artifact  The artifact to check
     * @param  int  $schemaDefinitionId  The schema definition ID for cache lookup
     * @param  array  $booleanSchema  The schema to check cache hash against
     * @return bool True if valid cached result exists, false otherwise
     */
    public function hasCachedClassification(Artifact $artifact, int $schemaDefinitionId, array $booleanSchema): bool
    {
        $storedFile = $artifact->storedFiles()->first();

        if (!$storedFile) {
            return false;
        }

        $schemaHash   = $this->computeSchemaHash($booleanSchema);
        $cachedResult = $this->getCachedClassification($storedFile, $schemaDefinitionId, $schemaHash);

        return $cachedResult !== null;
    }

    /**
     * Get artifacts classified for a specific category/group.
     * Filters artifacts based on their classification metadata.
     *
     * @param  Collection  $artifacts  Artifacts to filter
     * @param  string  $categoryKey  Category key to filter by (snake_case group name)
     * @return Collection Artifacts where the category is classified as true
     */
    public function getArtifactsForCategory(Collection $artifacts, string $categoryKey): Collection
    {
        static::logDebug('Getting artifacts for category', [
            'category'        => $categoryKey,
            'artifacts_count' => $artifacts->count(),
        ]);

        $filtered = $artifacts->filter(function ($artifact) use ($categoryKey) {
            $classification = $artifact->meta['classification'] ?? null;

            if (!$classification || !is_array($classification)) {
                return false;
            }

            // Boolean schema: check if category is true
            return ($classification[$categoryKey] ?? false) === true;
        });

        static::logDebug('Filtered artifacts for category', [
            'category'       => $categoryKey,
            'filtered_count' => $filtered->count(),
        ]);

        return $filtered;
    }

    /**
     * Compute SHA-256 hash of a classification schema.
     * Uses normalized JSON encoding to ensure consistent hashing.
     *
     * @param  array  $schema  The schema to hash
     * @return string SHA-256 hash of the schema
     */
    protected function computeSchemaHash(array $schema): string
    {
        // Normalize JSON encoding for consistent hashing
        $normalized = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $normalized);
    }

    /**
     * Get cached classification result for a file with a specific schema definition.
     * Uses schema definition ID for storage location lookup and schema hash for cache invalidation.
     * Returns null if no cache exists or if the cached schema hash differs from the current hash (cache busted).
     *
     * @param  StoredFile  $storedFile  The file to check cache for
     * @param  int  $schemaDefinitionId  The schema definition ID for cache lookup
     * @param  string  $schemaHash  The current schema hash for cache invalidation
     * @return array|null Cached classification result or null if no cache exists or cache is busted
     */
    public function getCachedClassification(StoredFile $storedFile, int $schemaDefinitionId, string $schemaHash): ?array
    {
        $classifications = $storedFile->meta['classifications'] ?? [];

        // Look up by schema definition ID (stored as string key)
        $cacheKey    = (string)$schemaDefinitionId;
        $cachedEntry = $classifications[$cacheKey] ?? null;

        if (!$cachedEntry || !isset($cachedEntry['result']) || !is_array($cachedEntry['result'])) {
            static::logDebug('No cached classification found', [
                'stored_file_id'       => $storedFile->id,
                'schema_definition_id' => $schemaDefinitionId,
            ]);

            return null;
        }

        // Check if cached schema hash matches current hash (cache invalidation)
        $cachedHash = $cachedEntry['schema_hash'] ?? null;

        if ($cachedHash !== $schemaHash) {
            static::logDebug('Cached classification hash mismatch (cache busted)', [
                'stored_file_id'       => $storedFile->id,
                'schema_definition_id' => $schemaDefinitionId,
                'cached_hash'          => $cachedHash ? substr($cachedHash, 0, 12) . '...' : 'none',
                'current_hash'         => substr($schemaHash, 0, 12) . '...',
            ]);

            return null;
        }

        static::logDebug('Using cached classification', [
            'stored_file_id'       => $storedFile->id,
            'schema_definition_id' => $schemaDefinitionId,
            'schema_hash'          => substr($schemaHash, 0, 12) . '...',
            'classified_at'        => $cachedEntry['classified_at'] ?? 'unknown',
        ]);

        return $cachedEntry['result'];
    }

    /**
     * Store classification result in cache on the StoredFile.
     * Uses schema definition ID as the storage key (overwrites any existing entry for that ID).
     * Includes schema hash for cache invalidation on future lookups.
     *
     * @param  StoredFile  $storedFile  The file to cache results for
     * @param  int  $schemaDefinitionId  The schema definition ID for cache storage location
     * @param  string  $schemaHash  The schema hash for cache invalidation
     * @param  array  $classificationResult  The classification results to cache
     */
    protected function storeCachedClassification(
        StoredFile $storedFile,
        int $schemaDefinitionId,
        string $schemaHash,
        array $classificationResult
    ): void {
        // Get existing classifications or create empty array
        $classifications = $storedFile->meta['classifications'] ?? [];

        // Store classification keyed by schema definition ID (as string key)
        // This will overwrite any existing entry for this schema definition
        $cacheKey                    = (string)$schemaDefinitionId;
        $classifications[$cacheKey] = [
            'schema_definition_id' => $schemaDefinitionId,
            'schema_hash'          => $schemaHash,
            'classified_at'        => now()->toIso8601String(),
            'result'               => $classificationResult,
        ];

        // Update meta field
        $meta                    = $storedFile->meta ?? [];
        $meta['classifications'] = $classifications;
        $storedFile->meta        = $meta;
        $storedFile->save();

        static::logDebug('Stored classification in cache', [
            'stored_file_id'       => $storedFile->id,
            'schema_definition_id' => $schemaDefinitionId,
            'schema_hash'          => substr($schemaHash, 0, 12) . '...',
        ]);
    }
}
