<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Models\TeamObject\TeamObjectRelationship;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Str;

/**
 * Handles artifact creation and parent-child linking for extraction operations.
 * Eliminates duplicated artifact creation code from ExtractDataTaskRunner.
 */
class ExtractionArtifactBuilder
{
    use HasDebugLogging;

    /**
     * Build and attach an identity extraction artifact.
     */
    public function buildIdentityArtifact(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        TeamObject $teamObject,
        array $group,
        array $extractionResult,
        int $level,
        ?int $matchId,
        ?int $parentObjectId = null
    ): Artifact {
        $artifact = $this->createArtifact(
            taskRun: $taskRun,
            name: "Identity: {$group['object_type']} - " . ($teamObject->name ?? 'Unknown'),
            jsonContent: $this->buildHierarchicalJson(
                $teamObject,
                $extractionResult['data'] ?? [],
                $group,
                $parentObjectId
            ),
            meta: [
                'operation'       => ExtractDataTaskRunner::OPERATION_EXTRACT_IDENTITY,
                'search_query'    => $extractionResult['search_query'] ?? null,
                'was_existing'    => $matchId !== null,
                'match_id'        => $matchId,
                'task_process_id' => $taskProcess->id,
                'level'           => $level,
                'identity_group'  => $group['name'] ?? $group['object_type'],
            ]
        );

        $this->attachToProcessAndLinkParent($artifact, $taskProcess);

        static::logDebug('Built identity extraction artifact', [
            'artifact_id'  => $artifact->id,
            'object_type'  => $group['object_type'],
            'was_existing' => $matchId !== null,
        ]);

        return $artifact;
    }

    /**
     * Build and attach a remaining extraction artifact.
     */
    public function buildRemainingArtifact(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        TeamObject $teamObject,
        array $group,
        array $extractedData,
        int $level,
        string $searchMode,
        ?int $parentObjectId = null
    ): Artifact {
        $artifact = $this->createArtifact(
            taskRun: $taskRun,
            name: "Remaining: {$group['name']} - " . ($teamObject->name ?? 'Unknown'),
            jsonContent: $this->buildHierarchicalJson(
                $teamObject,
                $extractedData,
                $group,
                $parentObjectId
            ),
            meta: [
                'operation'        => ExtractDataTaskRunner::OPERATION_EXTRACT_REMAINING,
                'extraction_mode'  => $searchMode,
                'task_process_id'  => $taskProcess->id,
                'level'            => $level,
                'extraction_group' => $group['name'] ?? $group['object_type'],
            ]
        );

        $this->attachToProcessAndLinkParent($artifact, $taskProcess);

        static::logDebug('Built remaining extraction artifact', [
            'artifact_id' => $artifact->id,
            'group_name'  => $group['name'] ?? 'Unknown',
            'level'       => $level,
        ]);

        return $artifact;
    }

    /**
     * Get the schema relation key from the fragment selector.
     * Falls back to snake_case of object_type if not found.
     */
    protected function getSchemaRelationKey(array $group): string
    {
        $fragmentSelector = $group['fragment_selector']   ?? [];
        $children         = $fragmentSelector['children'] ?? [];

        // The first key in children is the schema relation key
        if (!empty($children)) {
            return array_key_first($children);
        }

        // Fallback to snake_case of object_type
        return Str::snake($group['object_type'] ?? '');
    }

    /**
     * Check if the schema key represents an array type in the fragment selector.
     */
    protected function isArrayType(array $group, string $schemaKey): bool
    {
        $fragmentSelector = $group['fragment_selector']   ?? [];
        $children         = $fragmentSelector['children'] ?? [];

        if (isset($children[$schemaKey]['type'])) {
            return $children[$schemaKey]['type'] === 'array';
        }

        // Default to array for backwards compatibility with edge cases
        return true;
    }

    /**
     * Build hierarchical JSON structure for artifact content.
     * Level 0: flat structure (object is root)
     * Level 1+: traverse up to root and nest under full ancestor chain
     */
    protected function buildHierarchicalJson(
        TeamObject $teamObject,
        array $extractedData,
        array $group,
        ?int $parentObjectId
    ): array {
        $objectType = $group['object_type'] ?? '';
        $schemaKey  = $this->getSchemaRelationKey($group);

        // Unwrap extracted data if it's nested under the schema key
        if (isset($extractedData[$schemaKey]) && is_array($extractedData[$schemaKey])) {
            $unwrapped = $extractedData[$schemaKey];
            if (isset($unwrapped[0]) && is_array($unwrapped[0])) {
                $extractedData = $unwrapped[0];
            } elseif (!isset($unwrapped[0])) {
                // It's a single object, not an array
                $extractedData = $unwrapped;
            }
        }

        $currentData = array_merge(
            ['id' => $teamObject->id, 'type' => $objectType],
            $extractedData
        );

        // Level 0: current object IS the root
        if (!$parentObjectId) {
            return $currentData;
        }

        // Build ancestor chain by traversing relationships up to root
        // Each entry: ['object' => TeamObject, 'relationshipName' => string]
        $ancestors = [];
        $currentId = $parentObjectId;

        while ($currentId) {
            $parentObject = TeamObject::find($currentId);
            if (!$parentObject) {
                break;
            }

            // Find how this object relates to its parent (if any)
            $relationship = TeamObjectRelationship::where('related_team_object_id', $currentId)->first();

            // Prepend to ancestors (so root is first)
            array_unshift($ancestors, [
                'object'           => $parentObject,
                'relationshipName' => $relationship?->relationship_name,
            ]);

            // Move up to next parent
            $currentId = $relationship?->team_object_id;
        }

        // If no ancestors found, return flat structure
        if (empty($ancestors)) {
            return $currentData;
        }

        // Build nested structure from root down
        // Each level wraps its child in an array under the schema key

        // Start with current data (deepest level - the extracted object)
        $result = $currentData;

        // Work backwards through ancestors (from immediate parent to root)
        // ancestors[0] is root, ancestors[last] is immediate parent
        for ($i = count($ancestors) - 1; $i >= 0; $i--) {
            $ancestor       = $ancestors[$i];
            $ancestorObject = $ancestor['object'];

            // Determine the relationship key for nesting this level under the parent
            if ($i === count($ancestors) - 1) {
                // Immediate parent - nest current under schema key from fragment_selector
                $nestKey = $schemaKey;
            } else {
                // Higher ancestor - use the next level's object type
                $nextAncestor = $ancestors[$i + 1];
                $nestKey      = Str::snake($nextAncestor['object']->type);
            }

            // Nest under the parent - wrap in array only if fragment_selector type is 'array'
            $isArray = $this->isArrayType($group, $nestKey);
            $result  = [
                'id'     => $ancestorObject->id,
                'type'   => $ancestorObject->type,
                $nestKey => $isArray ? [$result] : $result,
            ];
        }

        return $result;
    }

    /**
     * Create the artifact with common fields.
     */
    protected function createArtifact(
        TaskRun $taskRun,
        string $name,
        array $jsonContent,
        array $meta
    ): Artifact {
        return Artifact::create([
            'name'               => $name,
            'task_definition_id' => $taskRun->task_definition_id,
            'task_run_id'        => $taskRun->id,
            'team_id'            => $taskRun->taskDefinition->team_id,
            'json_content'       => $jsonContent,
            'meta'               => $meta,
        ]);
    }

    /**
     * Attach artifact to process outputs and link as child of input artifact.
     */
    protected function attachToProcessAndLinkParent(Artifact $artifact, TaskProcess $taskProcess): void
    {
        // Attach to task process output artifacts
        $taskProcess->outputArtifacts()->attach($artifact->id);
        $taskProcess->updateRelationCounter('outputArtifacts');

        // Link as child of input artifact (page artifact)
        $inputArtifact = $taskProcess->inputArtifacts()->first();

        if (!$inputArtifact) {
            return;
        }

        $artifact->parent_artifact_id = $inputArtifact->id;
        $artifact->save();

        // Update parent artifact's child count
        $inputArtifact->updateRelationCounter('children');
    }
}
