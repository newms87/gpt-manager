<?php

namespace App\Services\Task\Runners;

use App\Services\Artifact\ArtifactBatchNamingService;
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

        // Intelligently name artifacts using LLM before attaching to workflow
        if ($inputArtifacts->isNotEmpty()) {
            $workflowDefinition = $this->taskRun->workflowRun->workflowDefinition;
            $contextDescription = $workflowDefinition->name . "\n" . $workflowDefinition->description;

            $this->step('Naming ' . $inputArtifacts->count() . ' artifacts', 50);

            app(ArtifactBatchNamingService::class)->nameArtifacts($inputArtifacts, $contextDescription);
        }

        $this->step('Attaching ' . $inputArtifacts->count() . ' artifacts to workflow', 75);

        $this->taskRun->workflowRun->addOutputArtifacts($inputArtifacts);

        $this->complete($inputArtifacts);
    }
}
