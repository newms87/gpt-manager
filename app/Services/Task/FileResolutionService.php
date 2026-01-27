<?php

namespace App\Services\Task;

use Illuminate\Support\Collection;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Models\Utilities\StoredFile;
use Newms87\Danx\Services\TranscodeFileService;
use Newms87\Danx\Traits\HasDebugLogging;

/**
 * Resolves StoredFiles into page images.
 * Handles PDF transcodes, direct images, and transcoding waits.
 */
class FileResolutionService
{
    use HasDebugLogging;

    public const int TRANSCODE_POLL_INTERVAL_SECONDS = 5;

    public const int TRANSCODE_TIMEOUT_SECONDS = 120;

    public const array IMAGE_MIMES = [
        StoredFile::MIME_PNG,
        StoredFile::MIME_JPEG,
        StoredFile::MIME_GIF,
        StoredFile::MIME_TIFF,
        StoredFile::MIME_WEBP,
        StoredFile::MIME_HEIC,
    ];

    /**
     * Resolve a single StoredFile into page images.
     * Handles transcoding waits, PDF transcodes, and direct images.
     *
     * @return Collection<StoredFile>
     */
    public function resolveStoredFile(StoredFile $storedFile): Collection
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
    public function waitForTranscoding(StoredFile $storedFile): void
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
}
