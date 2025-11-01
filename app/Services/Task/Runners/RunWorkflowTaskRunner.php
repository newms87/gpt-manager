<?php

namespace App\Services\Task\Runners;

use App\Models\Task\TaskProcessListener;
use App\Models\Workflow\WorkflowRun;
use App\Models\Workflow\WorkflowStatesContract;
use App\Services\Workflow\WorkflowRunnerService;
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

        $workflowRun = WorkflowRunnerService::start($workflowDefinition, $inputArtifacts);

        $this->taskProcess->taskProcessListeners()->create([
            'event_type' => WorkflowRun::class,
            'event_id'   => $workflowRun->id,
        ]);
    }

    public function eventTriggered(TaskProcessListener $taskProcessListener): void
    {
        static::logDebug("Handling Event $taskProcessListener");

        $workflowRun = $taskProcessListener->getEventObject();

        if (!($workflowRun instanceof WorkflowRun)) {
            static::logDebug('Ignoring event: event was not a WorkflowRun');

            return;
        }

        if ($workflowRun->isCompleted()) {
            $this->complete($workflowRun->collectFinalOutputArtifacts());
            $this->taskProcess->stopped_at = null;
            $this->taskProcess->failed_at  = null;
            $this->taskProcess->timeout_at = null;
        } elseif ($workflowRun->isStopped()) {
            $this->taskProcess->failed_at  = null;
            $this->taskProcess->timeout_at = null;
            $this->taskProcess->stopped_at = now();
        } elseif ($workflowRun->isFailed()) {
            $this->taskProcess->timeout_at = null;
            $this->taskProcess->stopped_at = null;
            $this->taskProcess->failed_at  = now();
        } else {
            $this->taskProcess->stopped_at = null;
            $this->taskProcess->failed_at  = null;
            $this->taskProcess->timeout_at = null;
            $totalTasks                    = $workflowRun->workflowDefinition->workflowNodes()->count();
            $runningTasks                  = $workflowRun->taskRuns()->where('status', WorkflowStatesContract::STATUS_RUNNING)->count();
            $completedTasks                = $workflowRun->taskRuns()->where('status', WorkflowStatesContract::STATUS_COMPLETED)->count();

            $percentComplete = $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0;

            $this->activity("$runningTasks Running and $completedTasks Completed of $totalTasks total tasks", $percentComplete);
        }

        $this->taskProcess->save();
    }
}
