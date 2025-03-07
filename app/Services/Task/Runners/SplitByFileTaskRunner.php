<?php

namespace App\Services\Task\Runners;

use App\Models\Task\Artifact;

class SplitByFileTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Split By File';

    public function run(): void
    {
        $config = $this->taskRun->taskDefinition->task_runner_config;

        $outputArtifacts = [];

        foreach($this->taskProcess->inputArtifacts as $inputArtifact) {
            if ($inputArtifact->storedFiles->isNotEmpty()) {
                foreach($inputArtifact->storedFiles as $storedFile) {
                    if ($storedFile->transcodes->isNotEmpty()) {
                        $fileList = $storedFile->transcodes;
                    } else {
                        $fileList = [$storedFile];
                    }

                    foreach($fileList as $fileItem) {
                        $artifact = Artifact::create(['name' => $fileItem->filename]);
                        $artifact->storedFiles()->attach($fileItem->id);
                        $outputArtifacts[] = $artifact;
                    }
                }
            }
        }

        $this->complete($outputArtifacts);
    }
}
