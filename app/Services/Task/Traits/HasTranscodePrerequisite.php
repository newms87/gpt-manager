<?php

namespace App\Services\Task\Traits;

use App\Services\Task\TranscodePrerequisiteService;

/**
 * Trait for task runners that need text transcodes before processing.
 * Provides common implementation for transcode operation routing and handling.
 */
trait HasTranscodePrerequisite
{
    /**
     * Run the transcode operation for a single artifact.
     */
    protected function runTranscodeOperation(): void
    {
        $artifact = $this->taskProcess->inputArtifacts()->first();

        if (!$artifact) {
            throw new Exception('Transcode process has no input artifact');
        }

        app(TranscodePrerequisiteService::class)->transcodeArtifact($this->taskProcess, $artifact);

        $this->complete();
    }
}
