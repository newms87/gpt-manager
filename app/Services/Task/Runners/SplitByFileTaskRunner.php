<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Resources\Workflow\ArtifactStoredFileResource;

class SplitByFileTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Split By File';

    public function run(): void
    {
        $outputArtifacts = [];

        foreach ($this->taskProcess->inputArtifacts as $inputArtifact) {
            if ($inputArtifact->storedFiles->isNotEmpty()) {
                foreach ($inputArtifact->storedFiles as $storedFile) {
                    if ($storedFile->transcodes->isNotEmpty()) {
                        $fileList = $storedFile->transcodes;
                    } else {
                        $fileList = [$storedFile];
                    }

                    foreach ($fileList as $fileItem) {
                        $artifact = Artifact::create([
                            'name' => $fileItem->filename,
                            'meta' => [
                                'file' => ArtifactStoredFileResource::make($storedFile),
                                'page' => ArtifactStoredFileResource::make($fileItem),
                            ],
                        ]);
                        $artifact->storedFiles()->attach($fileItem->id);
                        $outputArtifacts[] = $artifact;
                    }
                }
            }
        }

        $this->complete($outputArtifacts);
    }

    /**
     * Override to assign sequential positions after all processes are complete
     */
    public function afterAllProcessesCompleted(): void
    {
        parent::afterAllProcessesCompleted();
        $this->assignSequentialPositions();
    }

    /**
     * Assign sequential positions to all output artifacts based on their source file order
     *
     * This method:
     * - Gets all output artifacts from the task run ordered by ID (FIFO order)
     * - Groups artifacts by their source file (original_stored_file_id)
     * - Tracks the order of first appearance of each source (FIFO order from input)
     * - Sorts each group by original page_number to maintain order within each source
     * - Assigns sequential positions (1, 2, 3, ...) across ALL artifacts in FIFO order
     * - Updates both artifact->position and storedFile->page_number to the same value
     */
    protected function assignSequentialPositions(): void
    {
        static::logDebug('Assigning sequential positions to all output artifacts');

        // Get all output artifacts ordered by ID with storedFiles eager loaded
        $artifacts = $this->taskRun->outputArtifacts()
            ->with('storedFiles')
            ->orderBy('id')
            ->get();

        if ($artifacts->isEmpty()) {
            static::logDebug('No artifacts to assign positions to');

            return;
        }

        // Group artifacts by their source file and track FIFO order
        $sourceOrder       = [];
        $artifactsBySource = [];

        foreach ($artifacts as $artifact) {
            foreach ($artifact->storedFiles as $storedFile) {
                // Use the original file's ID as grouping key
                $sourceKey = $storedFile->original_stored_file_id ?? $storedFile->id;

                // Track first appearance order (FIFO)
                if (!isset($sourceOrder[$sourceKey])) {
                    $sourceOrder[$sourceKey]       = count($sourceOrder);
                    $artifactsBySource[$sourceKey] = [];
                }

                $artifactsBySource[$sourceKey][] = [
                    'artifact'           => $artifact,
                    'storedFile'         => $storedFile,
                    'originalPageNumber' => $storedFile->page_number ?? 0,
                ];
            }
        }

        // Sort each source group by original page number to maintain order within source
        foreach ($artifactsBySource as &$sourceFiles) {
            usort($sourceFiles, function ($a, $b) {
                return $a['originalPageNumber'] <=> $b['originalPageNumber'];
            });
        }
        unset($sourceFiles);

        // Sort sources by FIFO order
        uksort($artifactsBySource, function ($a, $b) use ($sourceOrder) {
            return $sourceOrder[$a] <=> $sourceOrder[$b];
        });

        // Assign sequential positions across all artifacts in FIFO order
        $position = 1;
        foreach ($artifactsBySource as $sourceFiles) {
            foreach ($sourceFiles as $fileData) {
                $artifact   = $fileData['artifact'];
                $storedFile = $fileData['storedFile'];

                // Update both position and page_number to the same value
                $artifact->position = $position;
                $artifact->save();

                $storedFile->page_number = $position;
                $storedFile->save();

                static::logDebug("Assigned position $position to artifact {$artifact->id} and stored file {$storedFile->id}");

                $position++;
            }
        }

        static::logDebug("Sequential positions assigned: Total {$position} positions");
    }
}
