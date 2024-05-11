<?php

namespace App\Http\Controllers\Ai;

use App\Resources\Workflow\WorkflowAssignmentResource;
use Flytedan\DanxLaravel\Http\Controllers\ActionController;
use Flytedan\DanxLaravel\Repositories\ActionRepository;

class WorkflowAssignmentsController extends ActionController
{
    public static string  $repo            = ActionRepository::class;
    public static ?string $resource        = WorkflowAssignmentResource::class;
    public static ?string $detailsResource = WorkflowAssignmentResource::class;
}
