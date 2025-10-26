<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Resources\Workflow\ArtifactStoredFileResource;
use Illuminate\Support\Collection;

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

        // Check if unique page numbering is enabled
        if ($this->config('unique_page_numbers')) {
            $this->assignUniquePageNumbers(collect($outputArtifacts));
        }

        $this->complete($outputArtifacts);
    }

    /**
     * Assign unique page numbers to all artifacts' stored files
     * This ensures that page numbers are unique across all PDFs while maintaining
     * the original order within each PDF/file
     */
    protected function assignUniquePageNumbers(Collection $artifacts): void
    {
        $this->activity('Assigning unique page numbers', 50);

        $currentPageNumber = 1;

        // Group artifacts by their source file to maintain order within each PDF
        $artifactsBySource = [];

        foreach ($artifacts as $artifact) {
            foreach ($artifact->storedFiles as $storedFile) {
                // Use the original file's ID or path as a grouping key
                $sourceKey = $storedFile->original_stored_file_id ?? $storedFile->id;

                if (!isset($artifactsBySource[$sourceKey])) {
                    $artifactsBySource[$sourceKey] = [];
                }

                $artifactsBySource[$sourceKey][] = [
                    'artifact'           => $artifact,
                    'storedFile'         => $storedFile,
                    'originalPageNumber' => $storedFile->page_number,
                ];
            }
        }

        // Sort each source group by original page number to maintain order
        foreach ($artifactsBySource as $sourceKey => &$sourceFiles) {
            usort($sourceFiles, function ($a, $b) {
                return ($a['originalPageNumber'] ?? 0) <=> ($b['originalPageNumber'] ?? 0);
            });
        }
        unset($sourceFiles);

        // Assign new unique page numbers
        foreach ($artifactsBySource as $sourceFiles) {
            foreach ($sourceFiles as $fileData) {
                $storedFile              = $fileData['storedFile'];
                $storedFile->page_number = $currentPageNumber;
                $storedFile->save();

                $currentPageNumber++;
            }
        }

        $this->activity('Unique page numbers assigned', 75);
    }
}
