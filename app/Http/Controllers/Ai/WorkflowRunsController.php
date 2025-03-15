<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowRunRepository;
use App\Resources\Workflow\WorkflowRunResource;
use Newms87\Danx\Http\Controllers\ActionController;
use Newms87\Danx\Requests\PagerRequest;

class WorkflowRunsController extends ActionController
{
    public static string  $repo     = WorkflowRunRepository::class;
    public static ?string $resource = WorkflowRunResource::class;

    public function runStatuses(PagerRequest $request): array
    {
        return app(WorkflowRunRepository::class)->getRunStatuses($request->filter());
    }
}
