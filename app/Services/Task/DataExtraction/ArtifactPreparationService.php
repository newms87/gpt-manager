<?php

namespace App\Services\Task\DataExtraction;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use Newms87\Danx\Traits\HasDebugLogging;
use Illuminate\Support\Collection;

/**
 * Handles artifact preparation for data extraction.
 * Creates parent/child artifact structures and resolves pages from input.
 */
class ArtifactPreparationService
{
    use HasDebugLogging;

    /**
     * Create extraction artifacts - parent output artifact with child artifacts (one per page).
     * Returns parent artifact with children accessible via $parentArtifact->children.
     */
    public function createExtractionArtifacts(TaskRun $taskRun, array $pages): Artifact
    {
        static::logDebug('Creating extraction artifacts', ['pages_count' => count($pages)]);

        // Create parent output artifact
        $parentArtifact = Artifact::create([
            'name'               => 'Extraction Output',
            'task_definition_id' => $taskRun->task_definition_id,
            'task_run_id'        => $taskRun->id,
            'team_id'            => $taskRun->taskDefinition->team_id,
        ]);

        // Attach all input files to the parent artifact for easy access to original documents
        $allFileIds = collect($pages)->pluck('file_id')->unique()->toArray();
        $parentArtifact->storedFiles()->sync($allFileIds);

        static::logDebug('Created parent artifact', [
            'artifact_id' => $parentArtifact->id,
            'file_count'  => count($allFileIds),
        ]);

        // Create child artifacts for each page
        foreach ($pages as $index => $page) {
            $pageNumber = $page['page_number'] ?? ($index + 1);
            $fileId     = $page['file_id'];

            $childArtifact = Artifact::create([
                'name'                => "Page $pageNumber",
                'parent_artifact_id'  => $parentArtifact->id,
                'position'            => $pageNumber,
                'task_definition_id'  => $taskRun->task_definition_id,
                'task_run_id'         => $taskRun->id,
                'team_id'             => $taskRun->taskDefinition->team_id,
            ]);

            // Attach stored file to child artifact
            $childArtifact->storedFiles()->sync([$fileId]);

            static::logDebug('Created child artifact', [
                'artifact_id' => $childArtifact->id,
                'page_number' => $pageNumber,
                'file_id'     => $fileId,
            ]);
        }

        // Update parent's children counter
        $parentArtifact->updateRelationCounter('children');

        static::logDebug('Created extraction artifacts', [
            'parent_artifact_id' => $parentArtifact->id,
            'children_count'     => $parentArtifact->children->count(),
        ]);

        return $parentArtifact;
    }

    /**
     * Resolve all pages from input artifacts.
     * Returns array of page data with artifact_id, file_id, and page_number.
     */
    public function resolvePages(TaskRun $taskRun): array
    {
        static::logDebug('Resolving pages from input artifacts');

        $artifacts = $this->getSourceArtifacts($taskRun);
        $pages     = [];

        foreach ($artifacts as $artifact) {
            foreach ($artifact->storedFiles as $file) {
                $pageNumber = $file->page_number ?? $file->position ?? 1;

                $pages[] = [
                    'artifact_id' => $artifact->id,
                    'file_id'     => $file->id,
                    'page_number' => $pageNumber,
                ];

                static::logDebug('Resolved page', [
                    'artifact_id' => $artifact->id,
                    'file_id'     => $file->id,
                    'page_number' => $pageNumber,
                ]);
            }
        }

        static::logDebug('Resolved pages', ['pages_count' => count($pages)]);

        return $pages;
    }

    /**
     * Get source artifacts (non-JSON artifacts) from TaskRun.
     */
    public function getSourceArtifacts(TaskRun $taskRun): Collection
    {
        return $taskRun->inputArtifacts()
            ->whereDoesntHave('storedFiles', fn($query) => $query->where('mime', 'application/json'))
            ->get();
    }
}
