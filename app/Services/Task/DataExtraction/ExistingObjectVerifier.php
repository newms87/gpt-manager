<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\AgentThread\AgentThreadBuilderService;
use App\Services\AgentThread\AgentThreadService;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;

/**
 * Service for verifying that an existing TeamObject matches the source artifacts being processed.
 *
 * When a user provides a JSON artifact indicating an existing object to update (rather than creating new),
 * this service ensures the source artifacts actually contain data for that specific object.
 * This prevents data corruption from accidentally extracting into the wrong record.
 *
 * Usage Example:
 * ```php
 * $verifier = app(ExistingObjectVerifier::class);
 *
 * // Check if JSON artifact references an existing object
 * if ($verifier->hasExistingObjectReference($jsonArtifact)) {
 *     $ref = $verifier->getExistingObjectReference($jsonArtifact);
 *     $existingObject = $verifier->loadExistingObject($ref['id'], $ref['type']);
 *
 *     // Verify the object matches the source artifacts
 *     $result = $verifier->verifyObjectMatchesArtifacts(
 *         $existingObject,
 *         $sourceArtifacts,
 *         $identificationFragment,
 *         $taskRun,
 *         $taskProcess
 *     );
 *
 *     if (!$result->isMatch()) {
 *         throw new ValidationError($result->explanation);
 *     }
 * }
 * ```
 */
class ExistingObjectVerifier
{
    use HasDebugLogging;

    /**
     * Check if a JSON artifact contains a reference to an existing object to update.
     *
     * Supports two JSON structures:
     * 1. {"existing_object": {"id": 123, "type": "Demand"}}
     * 2. {"id": 123, "object_type": "Demand"}
     */
    public function hasExistingObjectReference(Artifact $jsonArtifact): bool
    {
        return $this->getExistingObjectReference($jsonArtifact) !== null;
    }

    /**
     * Get the existing object reference from a JSON artifact.
     *
     * Returns array with 'id' and 'type' keys, or null if no reference found.
     */
    public function getExistingObjectReference(Artifact $jsonArtifact): ?array
    {
        $jsonContent = $jsonArtifact->json_content;

        if (!$jsonContent || !is_array($jsonContent)) {
            return null;
        }

        // Check for nested structure: {"existing_object": {"id": 123, "type": "Demand"}}
        if (isset($jsonContent['existing_object']) && is_array($jsonContent['existing_object'])) {
            $existing = $jsonContent['existing_object'];

            if (isset($existing['id'], $existing['type'])) {
                return [
                    'id'   => (int)$existing['id'],
                    'type' => (string)$existing['type'],
                ];
            }
        }

        // Check for flat structure: {"id": 123, "object_type": "Demand"}
        if (isset($jsonContent['id'], $jsonContent['object_type'])) {
            return [
                'id'   => (int)$jsonContent['id'],
                'type' => (string)$jsonContent['object_type'],
            ];
        }

        return null;
    }

    /**
     * Load an existing TeamObject from the database.
     *
     * @throws ValidationError if object not found or belongs to different team
     */
    public function loadExistingObject(int $objectId, string $objectType): TeamObject
    {
        $currentTeam = team();

        if (!$currentTeam) {
            throw new ValidationError('No team context available for loading existing object');
        }

        $existingObject = TeamObject::where('id', $objectId)
            ->where('type', $objectType)
            ->where('team_id', $currentTeam->id)
            ->first();

        if (!$existingObject) {
            throw new ValidationError(
                "Existing object not found: {$objectType} #{$objectId}. " .
                'Either the object does not exist or you do not have permission to access it.'
            );
        }

        static::logDebug("Loaded existing object: {$existingObject}");

        return $existingObject;
    }

    /**
     * Verify that an existing TeamObject matches the source artifacts using LLM comparison.
     *
     * This extracts key identifying fields from the existing object and asks the LLM to
     * verify that those same identifying characteristics appear in the source artifacts.
     *
     * @param  TeamObject  $existingObject  The existing object to verify
     * @param  Collection  $sourceArtifacts  Source artifacts that should contain this object's data
     * @param  array  $identificationFragment  Fragment selector defining which fields identify the object
     * @param  TaskRun  $taskRun  Task run context
     * @param  TaskProcess  $taskProcess  Task process context
     * @return VerificationResult Verification outcome with explanation
     *
     * @throws ValidationError if verification cannot be performed
     */
    public function verifyObjectMatchesArtifacts(
        TeamObject $existingObject,
        Collection $sourceArtifacts,
        array $identificationFragment,
        TaskRun $taskRun,
        TaskProcess $taskProcess
    ): VerificationResult {
        static::logDebug('Verifying existing object against source artifacts', [
            'object_id'     => $existingObject->id,
            'object_type'   => $existingObject->type,
            'artifact_ids'  => $sourceArtifacts->pluck('id')->toArray(),
        ]);

        // Extract identifying fields from existing object
        $identifyingFields = $this->extractIdentifyingFields($existingObject, $identificationFragment);

        if (empty($identifyingFields)) {
            static::logDebug('No identifying fields extracted from existing object');

            return new VerificationResult(
                matches: false,
                explanation: 'Cannot verify: No identifying fields found in existing object',
                mismatchedFields: ['all']
            );
        }

        // Build verification prompt for LLM
        $prompt = $this->buildVerificationPrompt($existingObject, $identifyingFields);

        // Create agent thread with source artifacts
        $thread = $this->buildVerificationThread($taskRun, $taskProcess, $sourceArtifacts, $prompt);

        // Run verification via LLM
        $response = $this->runVerificationThread($thread, $taskProcess);

        // Parse and return result
        return $this->parseVerificationResult($response);
    }

    /**
     * Extract identifying fields from an existing TeamObject.
     *
     * Uses the identification fragment selector to determine which fields are identifying,
     * then extracts those values from the TeamObject's attributes.
     */
    protected function extractIdentifyingFields(TeamObject $existingObject, array $identificationFragment): array
    {
        $identifyingFields = [];

        // Extract basic object properties
        if ($existingObject->name) {
            $identifyingFields['name'] = $existingObject->name;
        }

        if ($existingObject->date) {
            $identifyingFields['date'] = $existingObject->date->format('Y-m-d');
        }

        // Extract attributes based on fragment selector
        foreach ($existingObject->attributes as $attribute) {
            // Check if this attribute's name is in the fragment selector
            if ($this->isFieldInFragmentSelector($attribute->name, $identificationFragment)) {
                $identifyingFields[$attribute->name] = $attribute->value;
            }
        }

        static::logDebug('Extracted identifying fields', [
            'fields' => array_keys($identifyingFields),
        ]);

        return $identifyingFields;
    }

    /**
     * Check if a field name appears in the fragment selector.
     */
    protected function isFieldInFragmentSelector(string $fieldName, array $fragmentSelector): bool
    {
        // Check in children
        if (isset($fragmentSelector['children'])) {
            return isset($fragmentSelector['children'][$fieldName]);
        }

        return false;
    }

    /**
     * Build verification prompt for the LLM.
     */
    protected function buildVerificationPrompt(TeamObject $existingObject, array $identifyingFields): string
    {
        $objectType = $existingObject->type;

        $prompt = "# Existing Object Verification\n\n";
        $prompt .= "You are verifying that the source artifacts contain data for a specific {$objectType}.\n\n";

        $prompt .= "## Existing {$objectType} Information\n\n";
        $prompt .= "The existing {$objectType} has these identifying characteristics:\n\n";

        foreach ($identifyingFields as $fieldName => $fieldValue) {
            $prompt .= "- **{$fieldName}:** {$fieldValue}\n";
        }

        $prompt .= "\n## Your Task\n\n";
        $prompt .= "Examine the source artifacts and determine if they contain data for this specific {$objectType}.\n\n";

        $prompt .= "**Verification Criteria:**\n";
        $prompt .= "1. Do the artifacts mention or contain information about a {$objectType} with these same identifying characteristics?\n";
        $prompt .= "2. Are the key identifying fields (name, date, ID, etc.) consistent between the existing object and the artifacts?\n";
        $prompt .= "3. If there are discrepancies, are they minor (e.g., formatting differences) or major (different entity entirely)?\n\n";

        $prompt .= "**Respond with:**\n";
        $prompt .= "- **MATCH:** if the artifacts clearly contain data for this specific {$objectType}\n";
        $prompt .= "- **MISMATCH:** if the artifacts contain data for a different {$objectType} or no {$objectType} at all\n\n";

        $prompt .= "Provide a clear explanation of your determination, citing specific fields that match or mismatch.\n";

        return $prompt;
    }

    /**
     * Build an agent thread for verification.
     */
    protected function buildVerificationThread(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        Collection $sourceArtifacts,
        string $prompt
    ): \App\Models\Agent\AgentThread {
        $taskDefinition = $taskRun->taskDefinition;

        if (!$taskDefinition->agent) {
            throw new ValidationError('Agent not found for TaskRun: ' . $taskRun->id);
        }

        static::logDebug('Building verification agent thread', [
            'agent_id'       => $taskDefinition->agent->id,
            'artifact_count' => $sourceArtifacts->count(),
        ]);

        // Build thread with source artifacts
        $thread = AgentThreadBuilderService::for($taskDefinition->agent, $taskRun->team_id)
            ->named('Existing Object Verification')
            ->withSystemMessage($prompt)
            ->withArtifacts($sourceArtifacts)
            ->build();

        // Associate thread with task process
        $taskProcess->agentThread()->associate($thread)->save();

        return $thread;
    }

    /**
     * Run the verification agent thread and get the response.
     */
    protected function runVerificationThread(\App\Models\Agent\AgentThread $thread, TaskProcess $taskProcess): string
    {
        static::logDebug('Running verification agent thread', [
            'thread_id' => $thread->id,
        ]);

        // Get timeout from config
        $timeout = $taskProcess->taskRun->taskDefinition->task_runner_config['verification_timeout'] ?? 60;
        $timeout = max(1, min((int)$timeout, 600)); // Between 1-600 seconds

        // Run the thread
        $threadRun = app(AgentThreadService::class)
            ->withTimeout($timeout)
            ->run($thread);

        if (!$threadRun->isCompleted()) {
            throw new ValidationError(
                'Verification thread failed: ' . ($threadRun->error ?? 'Unknown error')
            );
        }

        $response = $threadRun->lastMessage?->content ?? '';

        if (empty($response)) {
            throw new ValidationError('No response received from verification agent');
        }

        static::logDebug('Verification response received', [
            'response_length' => strlen($response),
        ]);

        return $response;
    }

    /**
     * Parse verification result from LLM response.
     */
    protected function parseVerificationResult(string $response): VerificationResult
    {
        // Check for clear match/mismatch signals
        $upperResponse = strtoupper($response);

        $hasMatch    = str_contains($upperResponse, 'MATCH') && !str_contains($upperResponse, 'MISMATCH');
        $hasMismatch = str_contains($upperResponse, 'MISMATCH');

        // Determine result
        if ($hasMismatch) {
            // Extract explanation
            $explanation = $this->extractExplanation($response);

            return new VerificationResult(
                matches: false,
                explanation: $explanation ?: 'Source artifacts do not match the existing object',
                mismatchedFields: ['unknown'] // LLM doesn't provide structured field list
            );
        }

        if ($hasMatch) {
            $explanation = $this->extractExplanation($response);

            return new VerificationResult(
                matches: true,
                explanation: $explanation ?: 'Source artifacts match the existing object',
                matchedFields: ['all'] // LLM confirms match but doesn't provide field-by-field breakdown
            );
        }

        // Ambiguous response - be conservative and treat as mismatch
        static::logDebug('Ambiguous verification response', [
            'response' => $response,
        ]);

        return new VerificationResult(
            matches: false,
            explanation: 'Verification result unclear. Response: ' . substr($response, 0, 200),
            mismatchedFields: ['unknown']
        );
    }

    /**
     * Extract explanation from LLM response.
     */
    protected function extractExplanation(string $response): string
    {
        // Remove the MATCH/MISMATCH marker and clean up
        $explanation = preg_replace('/^\*\*(?:MATCH|MISMATCH):\*\*\s*/i', '', $response);
        $explanation = trim($explanation);

        // Limit length
        if (strlen($explanation) > 500) {
            $explanation = substr($explanation, 0, 497) . '...';
        }

        return $explanation;
    }
}
