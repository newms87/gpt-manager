<?php

namespace App\Http\Controllers\Ai;

use App\Models\Workflow\WorkflowJob;
use App\Repositories\WorkflowJobRepository;
use App\Resources\Workflow\WorkflowJobResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class WorkflowJobsController extends ActionController
{
    public static string  $repo            = WorkflowJobRepository::class;
    public static ?string $resource        = WorkflowJobResource::class;
    public static ?string $detailsResource = WorkflowJob::class;
}
