<?php

namespace App\Services\Task\Runners;

use Exception;

class WorkflowOutputTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Workflow Output';

    public function run(): void
    {
        if (!$this->taskRun->workflowRun) {
            throw new Exception('WorkflowOutputTaskRunner can only be used within a workflow context');
        }

        // Get all input artifacts for this output node
        $inputArtifacts = $this->taskProcess->inputArtifacts;

        $this->step('Attaching ' . $inputArtifacts->count() . ' artifacts to workflow', 75);

        $this->taskRun->workflowRun->addOutputArtifacts($inputArtifacts);

        $this->complete($inputArtifacts);
    }
}
