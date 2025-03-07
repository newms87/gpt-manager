<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;
use Newms87\Danx\Helpers\ArrayHelper;

class MergeArtifactsTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Merge Artifacts';

    public function run(): void
    {
        $config = $this->taskRun->taskDefinition->task_runner_config;

        $outputArtifacts = [];

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            if (!isset($outputArtifacts['default'])) {
                $outputArtifacts['default'] = Artifact::create(['name' => "Merged Artifact (default)"]);
            }
            $mergedArtifact = $outputArtifacts['default'];

            if ($inputArtifact->storedFiles->isNotEmpty()) {
                $mergedArtifact->storedFiles()->syncWithoutDetaching($inputArtifact->storedFiles->pluck('id'));
            }

            if ($inputArtifact->text_content) {
                $mergedArtifact->text_content = ($mergedArtifact->text_content ? "$mergedArtifact->text_content\n\n" : "") .
                    "--- $inputArtifact->name\n\n" . $inputArtifact->text_content;
            }

            if ($inputArtifact->json_content) {
                $mergedArtifact->json_content = ArrayHelper::mergeArraysRecursivelyUnique($mergedArtifact->json_content, $inputArtifact->json_content);
            }
        }

        $this->complete($outputArtifacts);
    }
}
