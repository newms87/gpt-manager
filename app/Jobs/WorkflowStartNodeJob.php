<?php

namespace App\Jobs;

use App\Models\Workflow\WorkflowNode;
use App\Models\Workflow\WorkflowRun;
use App\Services\Workflow\WorkflowRunnerService;
use Illuminate\Support\Collection;
use Newms87\Danx\Jobs\Job;

class WorkflowStartNodeJob extends Job
{
    protected WorkflowRun      $workflowRun;
    protected WorkflowNode     $workflowNode;
    protected array|Collection $artifacts;

    public int $timeout = 300;

    public function __construct(WorkflowRun $workflowRun, WorkflowNode $workflowNode, array|Collection $artifacts = [])
    {
        $this->workflowNode = $workflowNode;
        $this->workflowRun  = $workflowRun;
        $this->artifacts    = $artifacts;
        parent::__construct();
    }

    public static function make(WorkflowRun $workflowRun, WorkflowNode $workflowNode, array|Collection $artifacts = []): static
    {
        return (new static($workflowRun, $workflowNode, $artifacts));
    }

    public function ref(): string
    {
        return 'workflow-start-node:' . $this->workflowNode->id;
    }

    public function run(): void
    {
        WorkflowRunnerService::startNode($this->workflowRun, $this->workflowNode, $this->artifacts);
    }
}
