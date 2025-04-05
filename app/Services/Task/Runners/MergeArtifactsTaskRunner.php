<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use App\Services\Task\ArtifactsToGroupsMapper;
use Newms87\Danx\Helpers\ArrayHelper;
use Newms87\Danx\Helpers\StringHelper;

class MergeArtifactsTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Merge Artifacts';

    public function run(): void
    {
        $fragmentSelector = $this->config('fragment_selector');
        $inputArtifacts   = $this->taskProcess->inputArtifacts;

        if ($fragmentSelector) {
            $groupedArtifacts = app(ArtifactsToGroupsMapper::class)->setFragmentSelector($fragmentSelector)->map($inputArtifacts);
        } else {
            $groupedArtifacts = ['default' => $inputArtifacts];
        }

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
            }

            $outputArtifacts[] = $mergedArtifact;
        }

        $this->complete($outputArtifacts);
    }
}
