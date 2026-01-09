<?php

namespace App\Http\Controllers\Ai;

use App\Models\Workflow\WorkflowRun;
use App\Repositories\WorkflowRunRepository;
use App\Resources\Workflow\WorkflowRunResource;
use App\Services\Task\TaskProcessDispatcherService;
use Newms87\Danx\Http\Controllers\ActionController;
use Newms87\Danx\Requests\PagerRequest;
use Newms87\Danx\Resources\Audit\ErrorLogEntryResource;
use Newms87\Danx\Resources\Job\JobDispatchResource;

class WorkflowRunsController extends ActionController
{
    public static ?string $repo     = WorkflowRunRepository::class;

    public static ?string $resource = WorkflowRunResource::class;

    public function runStatuses(PagerRequest $request): array
    {
        return app(WorkflowRunRepository::class)->getRunStatuses($request->filter());
    }

    public function activeJobDispatches(WorkflowRun $workflowRun)
    {
        return JobDispatchResource::collection($workflowRun->activeWorkers);
    }

    public function dispatchWorkers(WorkflowRun $workflowRun)
    {
        TaskProcessDispatcherService::dispatchForWorkflowRun($workflowRun);

        return WorkflowRunResource::make($workflowRun);
    }

    public function errors(WorkflowRun $workflowRun)
    {
        return ErrorLogEntryResource::collection($workflowRun->getErrorLogEntries());
    }
}
