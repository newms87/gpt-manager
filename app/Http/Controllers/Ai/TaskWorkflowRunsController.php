<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\TaskWorkflowRunRepository;
use App\Resources\TaskWorkflow\TaskWorkflowRunResource;
use Newms87\Danx\Http\Controllers\ActionController;
use Newms87\Danx\Requests\PagerRequest;

class TaskWorkflowRunsController extends ActionController
{
    public static string  $repo     = TaskWorkflowRunRepository::class;
    public static ?string $resource = TaskWorkflowRunResource::class;

    public function runStatuses(PagerRequest $request): array
    {
        return app(TaskWorkflowRunRepository::class)->getRunStatuses($request->filter());
    }
}
