<?php

namespace App\Jobs;

use App\Models\Task\TaskRun;
use App\Services\Workflow\WorkflowRunnerService;
use Illuminate\Support\Collection;
use Newms87\Danx\Jobs\Job;

class WorkflowStartNodeJob extends Job
{
    public int $timeout = 300;

    public function __construct(protected TaskRun $taskRun, protected array|Collection $artifacts = [])
    {
        parent::__construct();
    }

    public function ref(): string
    {
        return 'workflow-start-node:' . $this->taskRun->id;
    }

    public function run(): void
    {
        WorkflowRunnerService::startNode($this->taskRun, $this->artifacts);
    }
}
