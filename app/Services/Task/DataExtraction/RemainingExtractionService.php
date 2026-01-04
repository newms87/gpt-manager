<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\TaskProcess;
use App\Models\Task\TaskRun;
use App\Models\TeamObject\TeamObject;
use App\Traits\HasDebugLogging;

/**
 * Handles the remaining field extraction workflow for extraction groups.
 * Orchestrates loading TeamObjects, getting classified artifacts,
 * routing to skim or exhaustive extraction, and building output artifacts.
 */
class RemainingExtractionService
{
    use HasDebugLogging;

    /**
     * Execute remaining field extraction for a task process.
     *
     * Returns the extracted data array, or empty array if extraction failed.
     */
    public function execute(
        TaskRun $taskRun,
        TaskProcess $taskProcess,
        array $extractionGroup,
        int $level,
        int $teamObjectId,
        string $searchMode = 'exhaustive'
    ): array {
        static::logDebug('Starting remaining extraction', [
            'team_object_id' => $teamObjectId,
            'group_name'     => $extractionGroup['name'] ?? $extractionGroup['object_type'],
            'search_mode'    => $searchMode,
            'level'          => $level,
        ]);

        // Load the TeamObject to update
        $teamObject = TeamObject::find($teamObjectId);

        if (!$teamObject) {
            static::logDebug('TeamObject not found for remaining extraction', [
                'team_object_id' => $teamObjectId,
            ]);

            return [];
        }

        // Get classified artifacts for this extraction group
        $groupExtractionService = app(GroupExtractionService::class);
        $artifacts              = $groupExtractionService->getClassifiedArtifactsForGroup($taskRun, $extractionGroup);

        if ($artifacts->isEmpty()) {
            static::logDebug('No classified artifacts found for extraction group', [
                'group' => $extractionGroup['name'] ?? $extractionGroup['object_type'],
            ]);

            return [];
        }

        // Route to appropriate extraction mode
        $extractedData = match ($searchMode) {
            'skim' => $groupExtractionService->extractWithSkimMode(
                $taskRun,
                $taskProcess,
                $extractionGroup,
                $artifacts,
                $teamObject
            ),
            default => $groupExtractionService->extractExhaustive(
                $taskRun,
                $taskProcess,
                $extractionGroup,
                $artifacts,
                $teamObject
            ),
        };

        if (empty($extractedData)) {
            static::logDebug('Extraction returned no data');

            return [];
        }

        // Update TeamObject with extracted data
        $groupExtractionService->updateTeamObjectWithExtractedData(
            $taskRun,
            $teamObject,
            $extractedData,
            $extractionGroup
        );

        // Build and attach output artifact
        app(ExtractionArtifactBuilder::class)->buildRemainingArtifact(
            taskRun: $taskRun,
            taskProcess: $taskProcess,
            teamObject: $teamObject,
            group: $extractionGroup,
            extractedData: $extractedData,
            level: $level,
            searchMode: $searchMode
        );

        static::logDebug('Remaining extraction completed', [
            'team_object_id' => $teamObject->id,
            'fields_count'   => count($extractedData),
        ]);

        return $extractedData;
    }
}
