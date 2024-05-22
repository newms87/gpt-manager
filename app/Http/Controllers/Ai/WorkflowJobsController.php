<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowJobRepository;
use App\Resources\Workflow\WorkflowJobResource;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowJobsController extends ActionController
{
    public static string  $repo            = WorkflowJobRepository::class;
    public static ?string $resource        = WorkflowJobResource::class;
    public static ?string $detailsResource = WorkflowJobResource::class;
}
