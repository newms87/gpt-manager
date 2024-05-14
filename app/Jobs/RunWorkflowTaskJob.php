<?php

namespace App\Jobs;

use App\Models\Workflow\WorkflowTask;
use App\Services\Workflow\WorkflowTaskService;
use Flytedan\DanxLaravel\Jobs\Job;

class RunWorkflowTaskJob extends Job
{
    public WorkflowTask $workflowTask;

    public function __construct(WorkflowTask $workflowTask)
    {
        $this->workflowTask = $workflowTask;
        parent::__construct();
    }

    public function ref(): string
    {
        return 'run-workflow-task:' . $this->workflowTask->id;
    }

    public function run()
    {
        WorkflowTaskService::start($this->workflowTask);
    }
}
