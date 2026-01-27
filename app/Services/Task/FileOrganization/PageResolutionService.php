<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Services\Task\FileResolutionService;
use Illuminate\Support\Collection;
use Newms87\Danx\Traits\HasDebugLogging;

/**
 * Resolves StoredFiles from input artifacts into a flat, sequential list of page images.
 * Orchestrates the resolution process for task runs and manages artifact replacement.
 */
class PageResolutionService
{
    use HasDebugLogging;

    /**
     * Resolve all input artifact stored files into a flat, sequential list of page images.
     * Assigns sequential page_numbers starting from 1.
     * Replaces task run input artifacts with a single "Resolved Pages" artifact.
     *
     * @return Collection<StoredFile> Resolved page StoredFiles
     */
    public function resolvePages(TaskRun $taskRun): Collection
    {
        static::logDebug('Resolving pages for task run', ['task_run_id' => $taskRun->id]);

        $pages = collect();

        $inputArtifacts = $taskRun->inputArtifacts()->orderBy('position')->get();

        static::logDebug('Found input artifacts', ['count' => $inputArtifacts->count()]);

        foreach ($inputArtifacts as $artifact) {
            $storedFiles = $artifact->storedFiles()->orderBy('id')->get();

            foreach ($storedFiles as $storedFile) {
                $resolvedPages = app(FileResolutionService::class)->resolveStoredFile($storedFile);
                $pages = $pages->merge($resolvedPages);
            }
        }

        // Assign sequential page numbers starting from 1
        $pageNumber = 1;
        foreach ($pages as $page) {
            $page->page_number = $pageNumber++;
            $page->save();
        }

        static::logDebug('Resolved pages', ['total_pages' => $pages->count()]);

        // Replace task run input artifacts with a single "Resolved Pages" artifact
        // Even if empty, this marks the phase as complete to prevent re-entry
        $this->replaceInputArtifacts($taskRun, $pages);

        return $pages;
    }

    /**
     * Replace all existing input artifacts with a single "Resolved Pages" artifact
     * containing all resolved page StoredFiles.
     */
    protected function replaceInputArtifacts(TaskRun $taskRun, Collection $pages): void
    {
        static::logDebug('Replacing input artifacts with Resolved Pages artifact');

        // Detach all existing input artifacts
        $taskRun->inputArtifacts()->detach();

        // Create the new resolved pages artifact
        $artifact = Artifact::create([
            'team_id' => $taskRun->taskDefinition->team_id,
            'name'    => 'Resolved Pages',
        ]);

        // Attach all resolved page StoredFiles to the artifact
        $artifact->storedFiles()->attach($pages->pluck('id'));

        // Attach the artifact as input to the task run
        $taskRun->inputArtifacts()->attach($artifact->id);
        $taskRun->updateRelationCounter('inputArtifacts');

        static::logDebug('Created Resolved Pages artifact', [
            'artifact_id' => $artifact->id,
            'page_count'  => $pages->count(),
        ]);
    }
}
