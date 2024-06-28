<?php

namespace App\Http\Controllers\Ai;

use App\Repositories\WorkflowAssignmentRepository;
use App\Resources\Workflow\WorkflowAssignmentResource;
use Newms87\Danx\Http\Controllers\ActionController;

class WorkflowAssignmentsController extends ActionController
{
    public static string  $repo     = WorkflowAssignmentRepository::class;
    public static ?string $resource = WorkflowAssignmentResource::class;
}
