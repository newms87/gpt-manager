<?php

namespace App\Repositories;

use App\Models\Task\TaskWorkflowRun;
use App\Services\Task\TaskWorkflowRunnerService;
use Newms87\Danx\Exceptions\ValidationError;
use Newms87\Danx\Repositories\ActionRepository;

class TaskWorkflowRunRepository extends ActionRepository
{
    public static string $model = TaskWorkflowRun::class;

    public function applyAction(string $action, $model = null, ?array $data = null)
    {
        return match ($action) {
            'create' => $this->createRun($data),
            'resume' => $this->resumeRun($model),
            'stop' => $this->stopRun($model),
            default => parent::applyAction($action, $model, $data)
        };
    }

    public function createRun(array $data): TaskWorkflowRun
    {
        $taskWorkflow = team()->taskWorkflows()->find($data['task_workflow_id'] ?? null);

        if (!$taskWorkflow) {
            throw new ValidationError('Failed to run workflow: Task Workflow was not found');
        }

        $workflowInput = team()->workflowInputs()->find($data['workflow_input_id'] ?? null);

        return TaskWorkflowRunnerService::start($taskWorkflow, $workflowInput);
    }

    public function resumeRun(TaskWorkflowRun $taskWorkflowRun): TaskWorkflowRun
    {
        TaskWorkflowRunnerService::resume($taskWorkflowRun);

        return $taskWorkflowRun;
    }

    public function stopRun(TaskWorkflowRun $taskWorkflowRun): TaskWorkflowRun
    {
        TaskWorkflowRunnerService::stop($taskWorkflowRun);

        return $taskWorkflowRun;
    }


}
