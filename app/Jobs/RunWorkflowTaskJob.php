<?php

namespace App\Jobs;

use App\Models\Workflow\WorkflowTask;
use App\Services\Workflow\WorkflowTaskService;
use Illuminate\Support\Facades\Log;
use Newms87\Danx\Jobs\Job;

class RunWorkflowTaskJob extends Job
{
    public WorkflowTask $workflowTask;

    public int $timeout = 610;

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
        if ($this->workflowTask->deleted_at) {
            Log::debug("Skipping deleted workflow task {$this->workflowTask->id}");

            return;
        }

        WorkflowTaskService::start($this->workflowTask);
    }
}
