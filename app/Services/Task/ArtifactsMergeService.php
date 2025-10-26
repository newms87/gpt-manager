<?php

namespace App\Services\Task;

use App\Models\Task\Artifact;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Helpers\StringHelper;

/**
 * Merges a group of artifacts into a single artifact by recursively merging the JSON and metadata and appending files
 * and text
 */
class ArtifactsMergeService
{
    public function merge($artifacts): Artifact
    {
        $pages = [];
        foreach ($artifacts as $artifact) {
            $pages[] = $artifact->position;
        }
        sort($pages);
        $mergedArtifact = Artifact::create([
            'name'     => 'Merged Artifact (Pages ' . StringHelper::formatPageRanges($pages) . ')',
            'position' => $pages[0] ?? 0,
        ]);

        foreach ($artifacts as $artifact) {
            if ($artifact->storedFiles->isNotEmpty()) {
                $mergedArtifact->storedFiles()->syncWithoutDetaching($artifact->storedFiles->pluck('id'));
            }

            if ($artifact->text_content) {
                $mergedArtifact->text_content = ($mergedArtifact->text_content ? "$mergedArtifact->text_content\n\n-----\n\n" : '') .
                    "# Page $artifact->position\n\n" . $artifact->text_content;
            }

            if ($artifact->json_content) {
                $mergedArtifact->json_content = ArrayHelper::mergeArraysRecursivelyUnique($mergedArtifact->json_content ?? [], $artifact->json_content);
            }

            if ($artifact->meta) {
                $mergedArtifact->meta = ArrayHelper::mergeArraysRecursivelyUnique($mergedArtifact->meta ?? [], $artifact->meta);
            }
        }

        $mergedArtifact->assignChildren($artifacts);

        return $mergedArtifact;
    }
}
