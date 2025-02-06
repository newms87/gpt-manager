<?php

namespace App\Http\Controllers\Ai;

use App\Models\Workflow\WorkflowRun;
use App\Repositories\WorkflowRunRepository;
use App\Resources\Workflow\WorkflowRunResource;
use Newms87\Danx\Http\Controllers\ActionController;
use Newms87\Danx\Requests\PagerRequest;

class WorkflowRunsController extends ActionController
{
    public static string  $repo     = WorkflowRunRepository::class;
    public static ?string $resource = WorkflowRunResource::class;

    /**
     * @param WorkflowRun $model
     * @return mixed
     */
    public function details($model): mixed
    {
        if ($model) {
            // The details are called regularly when a user views a page with a workflow run visible.
            // So we can check for timeouts here to be sure we're up-to-date instead of running a cron job.
            app(WorkflowRunRepository::class)->checkForTimeouts($model);
        }

        return parent::details($model);
    }

    public function runStatuses(PagerRequest $request): array
    {
        return app(WorkflowRunRepository::class)->getRunStatuses($request->filter());
    }
}
