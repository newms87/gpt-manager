<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Services\JsonSchema\JSONSchemaDataToDatabaseMapper;
use App\Services\JsonSchema\JsonSchemaService;
use App\Traits\HasDebugLogging;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Newms87\Danx\Exceptions\ValidationError;

class ObjectResolutionService
{
    use HasDebugLogging;

    /**
     * Verify existing object if JSON artifact references one.
     */
    public function verifyExistingObjectIfProvided(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $plan,
        int $level
    ): void {
        // Find JSON artifacts in input
        $jsonArtifacts = $taskRun->inputArtifacts()
            ->whereNotNull('json_content')
            ->get();

        if ($jsonArtifacts->isEmpty()) {
            return;
        }

        $verifier = app(ExistingObjectVerifier::class);

        foreach ($jsonArtifacts as $jsonArtifact) {
            if (!$verifier->hasExistingObjectReference($jsonArtifact)) {
                continue;
            }

            static::logDebug('Found existing object reference in JSON artifact', [
                'artifact_id' => $jsonArtifact->id,
            ]);

            // Get reference and load object
            $ref            = $verifier->getExistingObjectReference($jsonArtifact);
            $existingObject = $verifier->loadExistingObject($ref['id'], $ref['type']);

            // Get identities for this level
            $identities = $this->getIdentificationGroupsAtLevel($plan, $level);

            if (empty($identities)) {
                static::logDebug('No identities to verify against');

                return;
            }

            // Get first identity (assuming one object type per level)
            $identity = $identities[0];

            // Get fragment selector for verification
            $fragmentSelector = $identity['fragment_selector'] ?? null;

            if (!$fragmentSelector) {
                static::logDebug('No fragment selector found for verification');

                return;
            }

            // Get source artifacts (non-JSON artifacts)
            $sourceArtifacts = $taskRun->inputArtifacts()
                ->whereNull('json_content')
                ->get();

            // Verify object matches source artifacts
            $result = $verifier->verifyObjectMatchesArtifacts(
                $existingObject,
                $sourceArtifacts,
                $fragmentSelector,
                $taskRun,
                $taskProcess
            );

            if (!$result->isMatch()) {
                throw new ValidationError(
                    'Existing object verification failed: ' . $result->explanation
                );
            }

            static::logDebug('Existing object verified successfully', [
                'object_id'   => $existingObject->id,
                'object_type' => $existingObject->type,
            ]);

            // Store the verified object ID
            app(ExtractionProcessOrchestrator::class)->storeResolvedObjectId(
                $taskRun,
                $existingObject->type,
                $existingObject->id,
                $level
            );
        }
    }

    /**
     * Get identification groups at a specific level from the plan.
     * Now reads directly from the 'identities' array.
     */
    public function getIdentificationGroupsAtLevel(array $plan, int $level): array
    {
        $levelData = $plan['levels'][$level] ?? null;

        if (!$levelData) {
            return [];
        }

        // Identities array contains all identification items
        return $levelData['identities'] ?? [];
    }

    /**
     * Resolve objects for a specific identity item.
     */
    public function resolveObjectsForGroup(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identity,
        int $level
    ): void {
        $objectType       = $identity['object_type']       ?? null;
        $fragmentSelector = $identity['fragment_selector'] ?? null;

        if (!$objectType || !$fragmentSelector) {
            static::logDebug('Missing object_type or fragment_selector', [
                'object_type'  => $objectType,
                'has_fragment' => !empty($fragmentSelector),
            ]);

            return;
        }

        // Get the classification key for this identity
        $groupKey = Str::snake("{$objectType} Identification");

        // Get classified artifacts for this identity
        $classificationService = app(ClassificationExecutorService::class);
        $allInputArtifacts     = $taskRun->inputArtifacts()->get();
        $groupArtifacts        = $classificationService->getArtifactsForCategory($allInputArtifacts, $groupKey);

        if ($groupArtifacts->isEmpty()) {
            static::logDebug('No artifacts classified for this identity', ['group_key' => $groupKey]);

            return;
        }

        static::logDebug('Found artifacts for identity', [
            'group_key'      => $groupKey,
            'object_type'    => $objectType,
            'artifact_count' => $groupArtifacts->count(),
        ]);

        // Extract identification data using LLM
        $identificationData = $this->extractIdentificationData(
            $taskRun,
            $taskProcess,
            $identity,
            $groupArtifacts,
            $objectType,
            $fragmentSelector
        );

        if (empty($identificationData)) {
            static::logDebug('No identification data extracted', ['object_type' => $objectType]);

            return;
        }

        // Check for duplicates before creating
        $objectId = $this->resolveOrCreateObject($taskRun, $taskProcess, $objectType, $identificationData, $level);

        if ($objectId) {
            static::logDebug('Resolved or created object', [
                'object_type' => $objectType,
                'object_id'   => $objectId,
            ]);

            // Store resolved object ID
            app(ExtractionProcessOrchestrator::class)->storeResolvedObjectId(
                $taskRun,
                $objectType,
                $objectId,
                $level
            );
        }
    }

    /**
     * Extract identification data from artifacts using LLM.
     */
    public function extractIdentificationData(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $identity,
        Collection $artifacts,
        string $objectType,
        array $fragmentSelector
    ): array {
        $taskDefinition   = $taskRun->taskDefinition;
        $schemaDefinition = $taskDefinition->schemaDefinition;

        if (!$taskDefinition->agent) {
            throw new Exception('Agent not found for TaskRun: ' . $taskRun->id);
        }

        if (!$schemaDefinition) {
            throw new Exception('SchemaDefinition not found for TaskRun: ' . $taskRun->id);
        }

        static::logDebug('Extracting identification data', [
            'object_type'    => $objectType,
            'artifact_count' => $artifacts->count(),
        ]);

        // Build schema from fragment selector
        $jsonSchemaService = app(JsonSchemaService::class);
        $fragmentSchema    = $jsonSchemaService->applyFragmentSelector(
            $schemaDefinition->schema,
            $fragmentSelector
        );

        // Create agent thread with classified artifacts
        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Identification Data Extraction')
            ->withArtifacts($artifacts)
            ->build();

        // Get timeout from config
        $timeout = $taskDefinition->task_runner_config['extraction_timeout'] ?? 60;
        $timeout = max(1, min((int)$timeout, 600)); // Between 1-600 seconds

        // Run extraction
        $threadRun = app(AgentThreadService::class)
            ->withResponseFormat($schemaDefinition, null, $jsonSchemaService->setSchema($fragmentSchema))
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            static::logDebug('Thread run failed', [
                'error' => $threadRun->error ?? 'Unknown error',
            ]);

            return [];
        }

        // Parse response using getJsonContent() method
        $data = $threadRun->lastMessage?->getJsonContent();

        if (!$data || !is_array($data)) {
            static::logDebug('Failed to get JSON content from response');

            return [];
        }

        return $data;
    }

    /**
     * Resolve existing object or create new one.
     */
    public function resolveOrCreateObject(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        string $objectType,
        array $identificationData,
        int $level
    ): ?int {
        $resolver = app(DuplicateRecordResolver::class);

        // Get parent object ID if this is a nested level
        $parentObjectId = null;
        if ($level > 0) {
            $orchestrator   = app(ExtractionProcessOrchestrator::class);
            $parentObjects  = $orchestrator->getParentObjectIds($taskRun, $level);
            $parentObjectId = $parentObjects[0] ?? null;
        }

        // Get schema definition ID
        $schemaDefinitionId = $taskRun->taskDefinition->schemaDefinition?->id;

        // Find potential duplicates
        $candidates = $resolver->findCandidates(
            $objectType,
            $identificationData,
            $parentObjectId,
            $schemaDefinitionId
        );

        if ($candidates->isNotEmpty()) {
            static::logDebug('Found duplicate candidates', [
                'object_type'     => $objectType,
                'candidate_count' => $candidates->count(),
            ]);

            // Try quick exact match first
            $quickMatch = $resolver->quickMatchCheck($identificationData, $candidates);

            if ($quickMatch) {
                static::logDebug('Quick exact match found', [
                    'object_id' => $quickMatch->id,
                ]);

                return $quickMatch->id;
            }

            // Use LLM for fuzzy comparison
            $result = $resolver->resolveDuplicate(
                $identificationData,
                $candidates,
                $taskRun,
                $taskProcess
            );

            if ($result->hasDuplicate()) {
                static::logDebug('LLM found duplicate', [
                    'object_id'  => $result->existingObjectId,
                    'confidence' => $result->getConfidence(),
                ]);

                return $result->existingObjectId;
            }
        }

        // No duplicate found - create new object
        static::logDebug('Creating new object', [
            'object_type' => $objectType,
        ]);

        $mapper = app(JSONSchemaDataToDatabaseMapper::class);

        // Set context
        if ($schemaDefinitionId) {
            $mapper->setSchemaDefinition($taskRun->taskDefinition->schemaDefinition);
        }

        if ($parentObjectId) {
            $parentObject = TeamObject::find($parentObjectId);
            if ($parentObject) {
                $mapper->setRootObject($parentObject);
            }
        }

        // Create TeamObject
        $name       = $identificationData['name'] ?? $identificationData['id'] ?? 'Unknown';
        $teamObject = $mapper->createTeamObject($objectType, $name, $identificationData);

        return $teamObject->id;
    }
}
