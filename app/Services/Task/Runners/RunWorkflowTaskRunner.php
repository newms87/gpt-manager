<?php

namespace App\Services\Task\Runners;

use Newms87\Danx\Exceptions\ValidationError;

class RunWorkflowTaskRunner extends BaseTaskRunner
{
    const string RUNNER_NAME = 'Run Workflow';

    public function run(): void
    {
        $config             = $this->taskRun->taskDefinition->task_runner_config;
        $workflowDefinition = team()->workflowDefinitions()->find($config['workflow_definition_id']);

        if (!$workflowDefinition) {
            throw new ValidationError('Workflow definition not found: ' . $config['workflow_definition_id']);
        }

        $inputArtifacts = $this->taskProcess->inputArtifacts;

        $this->activity("Running workflow $workflowDefinition->name w/ " . $inputArtifacts->count() . ' artifacts', 1);

        $this->complete($artifacts);
    }
}
