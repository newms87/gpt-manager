<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Helpers\StringHelper;

class MergeArtifactsTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Merge Artifacts';

    public function run(): void
    {
        $groupedArtifacts = $this->groupArtifacts();

        $outputArtifacts = [];

        foreach($groupedArtifacts as $artifactsInGroup) {
            $pages = [];
            foreach($artifactsInGroup as $inputArtifact) {
                $pages[] = $inputArtifact->position;
            }
            sort($pages);
            $mergedArtifact = Artifact::create([
                'name'     => "Merged Artifact (Pages " . StringHelper::formatPageRanges($pages) . ")",
                'position' => $pages[0] ?? 0,
            ]);

            foreach($artifactsInGroup as $inputArtifact) {
                if ($inputArtifact->storedFiles->isNotEmpty()) {
                    $mergedArtifact->storedFiles()->syncWithoutDetaching($inputArtifact->storedFiles->pluck('id'));
                }

                if ($inputArtifact->text_content) {
                    $mergedArtifact->text_content = ($mergedArtifact->text_content ? "$mergedArtifact->text_content\n\n-----\n\n" : "") .
                        "# Page $inputArtifact->position\n\n" . $inputArtifact->text_content;
                }

                if ($inputArtifact->json_content) {
                    $mergedArtifact->json_content = ArrayHelper::mergeArraysRecursivelyUnique($mergedArtifact->json_content ?? [], $inputArtifact->json_content);
                }

                if ($inputArtifact->meta) {
                    $mergedArtifact->meta = ArrayHelper::mergeArraysRecursivelyUnique($mergedArtifact->meta ?? [], $inputArtifact->meta);
                }
            }
            
            $mergedArtifact->children()->saveMany($artifactsInGroup);
            $mergedArtifact->updateRelationCounter('children');

            $outputArtifacts[] = $mergedArtifact;
        }

        $this->complete($outputArtifacts);
    }

    /**
     * Group artifacts by their JSON content and meta fragment values.
     */
    public function groupArtifacts(): array
    {
        $jsonContentFragmentSelector = $this->config('json_content_fragment_selector') ?: [];
        $metaFragmentSelector        = $this->config('meta_fragment_selector') ?: [];

        if (!$metaFragmentSelector && !$jsonContentFragmentSelector) {
            return ['default' => $this->taskProcess->inputArtifacts];
        }

        $artifactsByGroup = [];

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            $jsonContentKey = $inputArtifact->getFlattenedJsonFragmentValuesString($jsonContentFragmentSelector);
            $metaKey        = $inputArtifact->getFlattenedMetaFragmentValuesString($metaFragmentSelector);
            $groupKey       = "$jsonContentKey;$metaKey";

            $artifactsByGroup[$groupKey][] = $inputArtifact;
        }

        return $artifactsByGroup;
    }
}
