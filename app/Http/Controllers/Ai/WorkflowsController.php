<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowRepository;
use App\Resources\Workflow\WorkflowDetailsResource;
use App\Resources\Workflow\WorkflowResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;

class WorkflowsController extends ActionController
{
    public static string  $repo            = WorkflowRepository::class;
    public static ?string $resource        = WorkflowResource::class;
    public static ?string $detailsResource = WorkflowDetailsResource::class;
}
