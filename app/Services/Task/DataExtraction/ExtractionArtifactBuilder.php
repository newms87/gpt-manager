<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Services\Task\Runners\ExtractDataTaskRunner;
use App\Traits\HasDebugLogging;

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
        ?int $matchId
    ): Artifact {
        $artifact = $this->createArtifact(
            taskRun: $taskRun,
            name: "Identity: {$group['object_type']} - " . ($teamObject->name ?? 'Unknown'),
            jsonContent: array_merge(
                ['id' => $teamObject->id, 'type' => $group['object_type']],
                $extractionResult['data'] ?? []
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
        string $searchMode
    ): Artifact {
        $artifact = $this->createArtifact(
            taskRun: $taskRun,
            name: "Remaining: {$group['name']} - " . ($teamObject->name ?? 'Unknown'),
            jsonContent: array_merge(
                ['id' => $teamObject->id, 'type' => $group['object_type']],
                $extractedData
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
