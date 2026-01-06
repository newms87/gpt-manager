<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Schema\SchemaDefinition;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Services\AgentThread\ArtifactFilter;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use App\Services\JsonSchema\JsonSchemaService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

/**
 * Handles the complete identity extraction workflow for a task process.
 *
 * This service orchestrates:
 * 1. Schema building with fragment selector for identity fields
 * 2. LLM thread execution to extract identity data + search query
 * 3. Duplicate resolution using DuplicateRecordResolver
 * 4. TeamObject creation or resolution
 * 5. Artifact building using ExtractionArtifactBuilder
 *
 * Usage Example:
 * ```php
 * $service = app(IdentityExtractionService::class);
 * $teamObject = $service->execute(
 *     taskRun: $taskRun,
 *     taskProcess: $taskProcess,
 *     identityGroup: ['object_type' => 'Demand', 'identity_fields' => ['client_name']],
 *     level: 0,
 *     parentObjectId: null
 * );
 * ```
 */
class IdentityExtractionService
{
    use HasDebugLogging;

    /**
     * Execute identity extraction for a task process.
     *
     * Returns the created/resolved TeamObject or null if no identity data found.
     */
    public function execute(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identityGroup,
        int $level,
        ?int $parentObjectId = null
    ): ?TeamObject {
        $artifacts = $taskProcess->inputArtifacts;

        if ($artifacts->isEmpty()) {
            static::logDebug('No input artifacts for identity extraction', [
                'process_id' => $taskProcess->id,
                'group'      => $identityGroup['name'] ?? 'unknown',
            ]);

            return null;
        }

        static::logDebug('Running identity extraction', [
            'level'          => $level,
            'group'          => $identityGroup['name'] ?? 'unknown',
            'artifact_count' => $artifacts->count(),
        ]);

        // LLM Call #1: Extract identity fields + search query
        $extractionResult = $this->extractIdentityWithSearchQuery($taskRun, $artifacts, $identityGroup);

        if (empty($extractionResult)) {
            static::logDebug('No identity data extracted');

            return null;
        }

        // Check if extraction found any usable identity data
        $identificationData = $extractionResult['data'] ?? [];

        // Unwrap nested data using the wrapper key from fragment_selector
        // The LLM returns data under the key specified in fragment_selector.children (e.g., 'incidents')
        // which may differ from object_type (e.g., 'Incident' singular vs 'incidents' plural)
        $fragmentSelector  = $identityGroup['fragment_selector'] ?? [];
        $wrapperKey        = array_key_first($fragmentSelector['children'] ?? []);
        $firstChildSchema  = $fragmentSelector['children'][$wrapperKey] ?? [];
        $firstChildType    = $firstChildSchema['type']                  ?? null;

        // Only unwrap if the wrapper key represents an object or array, not a scalar type
        if ($wrapperKey && in_array($firstChildType, ['object', 'array'], true) && isset($identificationData[$wrapperKey])) {
            $unwrapped = $identificationData[$wrapperKey];

            // If it's an array of arrays (fragment_selector says type: "array"), take the first element
            if (is_array($unwrapped) && isset($unwrapped[0]) && is_array($unwrapped[0])) {
                $identificationData = $unwrapped[0];
            } else {
                $identificationData = $unwrapped;
            }
        }

        $identityFields = $identityGroup['identity_fields'] ?? [];
        $name           = $this->resolveObjectName($identificationData, $identityFields);

        // If no name could be resolved, this means no data was found - return null
        if ($name === null) {
            static::logDebug("Identity extraction found no data for {$identityGroup['object_type']} - no identifiable name in response", [
                'identification_data' => $identificationData,
            ]);

            return null;
        }

        // Resolve duplicates using DuplicateRecordResolver
        $matchId = $this->resolveDuplicate(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            identityGroup: $identityGroup,
            extractionResult: $extractionResult,
            parentObjectId: $parentObjectId
        );

        // Create or use existing TeamObject
        $teamObject = $this->resolveOrCreateTeamObject(
            taskRun: $taskRun,
            objectType: $identityGroup['object_type'],
            identificationData: $identificationData,
            name: $name,
            existingId: $matchId,
            parentObjectId: $parentObjectId
        );

        // Store resolved object ID for dependent processes
        app(ExtractionProcessOrchestrator::class)->storeResolvedObjectId(
            $taskRun,
            $identityGroup['object_type'],
            $teamObject->id,
            $level
        );

        static::logDebug('Identity extraction completed', [
            'object_type'  => $identityGroup['object_type'],
            'object_id'    => $teamObject->id,
            'was_existing' => $matchId !== null,
        ]);

        // Build and attach output artifact
        app(ExtractionArtifactBuilder::class)->buildIdentityArtifact(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $identityGroup,
            extractionResult: $extractionResult,
            level: $level,
            matchId: $matchId,
            parentObjectId: $parentObjectId
        );

        return $teamObject;
    }

    /**
     * Extract identity fields with search query using LLM.
     */
    protected function extractIdentityWithSearchQuery(
        TaskRun $taskRun,
        Collection $artifacts,
        array $group
    ): array {
        $taskDefinition   = $taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent || !$schemaDefinition) {
            static::logDebug('Missing agent or schema definition for identity extraction');

            return [];
        }

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

        // Use applyFragmentSelector to convert fragment_selector (with 'children')
        // to proper JSON Schema (with 'properties')
        $jsonSchemaService = app(JsonSchemaService::class);
        $dataSchema        = $jsonSchemaService->applyFragmentSelector(
            $schemaDefinition->schema,
            $fragmentSelector
        );

        $responseSchema = [
            'type'       => 'object',
            'properties' => [
                'data'         => $dataSchema,
                'search_query' => [
                    'type'        => 'object',
                    'description' => 'SQL LIKE patterns for finding matching records',
                    'properties'  => $searchQueryProperties,
                ],
            ],
            'required' => ['data', 'search_query'],
        ];

        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Identity Data Extraction')
            ->withArtifacts($artifacts, new ArtifactFilter(
                includeFiles: false,
                includeJson: false,
                includeMeta: false
            ))
            ->build();

        $timeout = $taskDefinition->task_runner_config['extraction_timeout'] ?? 60;
        $timeout = max(1, min((int)$timeout, 600));

        // Create a temporary in-memory SchemaDefinition for the identity extraction response
        $identitySchema = $this->createIdentityExtractionSchema($responseSchema);

        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($identitySchema, null, app(JsonSchemaService::class))
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            static::logDebug('Identity extraction thread failed', [
                'error' => $threadRun->error ?? 'Unknown',
            ]);

            return [];
        }

        $data = $threadRun->lastMessage?->getJsonContent();

        return is_array($data) ? $data : [];
    }

    /**
     * Resolve duplicate using DuplicateRecordResolver.
     *
     * Performs quick exact-match first (optimization), then falls back to LLM resolution.
     */
    protected function resolveDuplicate(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identityGroup,
        array $extractionResult,
        ?int $parentObjectId
    ): ?int {
        $extractedData = $extractionResult['data']         ?? [];
        $searchQuery   = $extractionResult['search_query'] ?? $extractedData;

        if (empty($extractedData)) {
            return null;
        }

        $resolver   = app(DuplicateRecordResolver::class);
        $candidates = $resolver->findCandidates(
            objectType: $identityGroup['object_type'],
            extractedData: $searchQuery,
            parentObjectId: $parentObjectId,
            schemaDefinitionId: $taskRun->taskDefinition->schema_definition_id
        );

        if ($candidates->isEmpty()) {
            return null;
        }

        // Try quick exact-match first (optimization - avoids LLM call)
        $identityFields = $identityGroup['identity_fields'] ?? [];
        $quickMatch     = $resolver->quickMatchCheck($extractedData, $candidates, $identityFields);
        if ($quickMatch) {
            static::logDebug('Quick exact match found', ['object_id' => $quickMatch->id]);

            return $quickMatch->id;
        }

        // LLM Call #2: Resolve which candidate (if any) matches
        $result = $resolver->resolveDuplicate(
            extractedData: $extractedData,
            candidates: $candidates,
            taskRun: $taskRun,
            taskProcess: $taskProcess
        );

        if ($result->hasDuplicate()) {
            static::logDebug('LLM resolution found match', ['object_id' => $result->existingObjectId]);

            return $result->existingObjectId;
        }

        return null;
    }

    /**
     * Resolve existing or create new TeamObject.
     */
    protected function resolveOrCreateTeamObject(
        TaskRun $taskRun,
        string $objectType,
        array $identificationData,
        string $name,
        ?int $existingId,
        ?int $parentObjectId
    ): TeamObject {
        $mapper = app(JSONSchemaDataToDatabaseMapper::class);

        if ($taskRun->taskDefinition->schemaDefinition) {
            $mapper->setSchemaDefinition($taskRun->taskDefinition->schemaDefinition);
        }

        if ($existingId) {
            $teamObject = TeamObject::find($existingId);
            if ($teamObject) {
                // Update existing object with new data
                $mapper->updateTeamObject($teamObject, $identificationData);
                $this->saveIdentityFieldsAsAttributes($mapper, $teamObject, $identificationData);

                return $teamObject;
            }
        }

        // Set up parent object context if needed
        if ($parentObjectId) {
            $parentObject = TeamObject::find($parentObjectId);
            if ($parentObject) {
                $mapper->setRootObject($parentObject);
            }
        }

        // Ensure the identificationData has the resolved name to prevent empty string from being
        // filled by updateTeamObject (which is called by createTeamObject)
        $identificationData['name'] = $name;

        $teamObject = $mapper->createTeamObject($objectType, $name, $identificationData);

        $this->saveIdentityFieldsAsAttributes($mapper, $teamObject, $identificationData);

        return $teamObject;
    }

    /**
     * Save identity fields that are not TeamObject columns as attributes.
     *
     * Fields that match TeamObject columns (name, date, description, url) are already
     * saved directly on the model by updateTeamObject/createTeamObject. This method
     * saves all other identity fields as team_object_attributes.
     */
    protected function saveIdentityFieldsAsAttributes(
        JSONSchemaDataToDatabaseMapper $mapper,
        TeamObject $teamObject,
        array $identificationData
    ): void {
        // Fields that are columns on TeamObject model (already handled by updateTeamObject)
        $teamObjectColumns = ['name', 'date', 'description', 'url'];

        foreach ($identificationData as $fieldName => $fieldValue) {
            // Skip if this is a TeamObject column (already saved on model)
            if (in_array($fieldName, $teamObjectColumns, true)) {
                continue;
            }

            // Skip null/empty values
            if ($fieldValue === null || $fieldValue === '') {
                continue;
            }

            static::logDebug("Saving identity field as attribute: {$fieldName}", [
                'team_object_id' => $teamObject->id,
                'value'          => $fieldValue,
            ]);

            $mapper->saveTeamObjectAttribute($teamObject, $fieldName, [
                'value' => $fieldValue,
            ]);
        }
    }

    /**
     * Resolve a name for the TeamObject from identification data.
     * Returns null if no usable name can be found (indicating no data was extracted).
     *
     * Only checks fields specified in $identityFields - no hardcoded fallbacks.
     */
    protected function resolveObjectName(array $identificationData, array $identityFields): ?string
    {
        foreach ($identityFields as $field) {
            if (!empty($identificationData[$field]) && is_string($identificationData[$field])) {
                return $identificationData[$field];
            }
        }

        return null;
    }

    /**
     * Create a transient SchemaDefinition for identity extraction response format.
     */
    protected function createIdentityExtractionSchema(array $responseSchema): SchemaDefinition
    {
        $schemaDefinition         = new SchemaDefinition;
        $schemaDefinition->schema = $responseSchema;
        $schemaDefinition->name   = 'IdentityExtractionResponse';
        $schemaDefinition->type   = SchemaDefinition::TYPE_AGENT_RESPONSE;

        return $schemaDefinition;
    }
}
