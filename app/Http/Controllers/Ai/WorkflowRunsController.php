<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowRunRepository;
use App\Resources\Workflow\WorkflowRunDetailsResource;
use App\Resources\Workflow\WorkflowRunResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class WorkflowRunsController extends ActionController
{
    public static string  $repo            = WorkflowRunRepository::class;
    public static ?string $resource        = WorkflowRunResource::class;
    public static ?string $detailsResource = WorkflowRunDetailsResource::class;
}
