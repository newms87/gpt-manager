<?php

namespace App\Services\Task\FileOrganization;

use App\Models\Task\Artifact;
use App\Models\Task\TaskRun;
use App\Traits\HasDebugLogging;
use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;

/**
 * Resolves StoredFiles from input artifacts into a flat, sequential list of page images.
 * Handles PDF transcodes, direct images, and transcoding waits.
 */
class PageResolutionService
{
    use HasDebugLogging;

    protected const int TRANSCODE_POLL_INTERVAL_SECONDS = 5;

    protected const int TRANSCODE_TIMEOUT_SECONDS = 120;

    protected const array IMAGE_MIMES = [
        StoredFile::MIME_PNG,
        StoredFile::MIME_JPEG,
        StoredFile::MIME_GIF,
        StoredFile::MIME_TIFF,
        StoredFile::MIME_WEBP,
        StoredFile::MIME_HEIC,
    ];

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
                $resolvedPages = $this->resolveStoredFile($storedFile);
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
     * Resolve a single StoredFile into page images.
     * Handles transcoding waits, PDF transcodes, and direct images.
     *
     * @return Collection<StoredFile>
     */
    protected function resolveStoredFile(StoredFile $storedFile): Collection
    {
        // Wait for transcoding to complete if in progress
        if ($storedFile->is_transcoding) {
            $this->waitForTranscoding($storedFile);
        }

        // Check for PDF-to-image transcodes
        $pdfTranscodes = $storedFile->transcodes()
            ->where('transcode_name', TranscodeFileService::TRANSCODE_PDF_TO_IMAGES)
            ->orderBy('page_number')
            ->get();

        if ($pdfTranscodes->isNotEmpty()) {
            static::logDebug('Using PDF transcoded pages', [
                'stored_file_id' => $storedFile->id,
                'page_count'     => $pdfTranscodes->count(),
            ]);

            return $pdfTranscodes;
        }

        // Check if file is a direct image
        if (in_array($storedFile->mime, self::IMAGE_MIMES)) {
            static::logDebug('Using direct image as page', [
                'stored_file_id' => $storedFile->id,
                'mime'           => $storedFile->mime,
            ]);

            return collect([$storedFile]);
        }

        // Skip non-image files without PDF transcodes
        static::logDebug('Skipping non-image file without PDF transcodes', [
            'stored_file_id' => $storedFile->id,
            'mime'           => $storedFile->mime,
        ]);

        return collect();
    }

    /**
     * Poll-wait for a StoredFile to finish transcoding.
     *
     * @throws ValidationError If transcoding exceeds timeout
     */
    protected function waitForTranscoding(StoredFile $storedFile): void
    {
        $elapsed = 0;

        static::logDebug('Waiting for transcoding to complete', [
            'stored_file_id' => $storedFile->id,
            'filename'       => $storedFile->filename,
        ]);

        while ($storedFile->is_transcoding) {
            if ($elapsed >= self::TRANSCODE_TIMEOUT_SECONDS) {
                throw new ValidationError(
                    "Transcoding timeout after {$elapsed}s for StoredFile {$storedFile->id} ({$storedFile->filename})"
                );
            }

            static::logDebug('Still transcoding, waiting...', [
                'stored_file_id' => $storedFile->id,
                'elapsed'        => $elapsed,
            ]);

            sleep(self::TRANSCODE_POLL_INTERVAL_SECONDS);
            $elapsed += self::TRANSCODE_POLL_INTERVAL_SECONDS;
            $storedFile->refresh();
        }

        static::logDebug('Transcoding complete', [
            'stored_file_id' => $storedFile->id,
            'elapsed'        => $elapsed,
        ]);
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
